<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentFileService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DocumentFileManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        Storage::fake('documents');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_creator_can_upload_pdf_with_private_metadata_and_no_send_side_effects(): void
    {
        [$document, , $creator] = $this->documentContext();
        $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile('informe final.pdf', $contents)],
            'lock_version' => 0,
        ])->assertRedirect()->assertSessionHas('status');

        $version = DocumentVersion::query()->with('files')->sole();
        $file = $version->files->sole();
        $document->refresh();

        $this->assertSame(1, $version->version_number);
        $this->assertSame($creator->id, $version->uploaded_by);
        $this->assertSame('informe final.pdf', $file->original_name);
        $this->assertSame('pdf', $file->extension);
        $this->assertSame('application/pdf', $file->detected_mime_type);
        $this->assertSame(strlen($contents), $file->size_bytes);
        $this->assertSame(hash('sha256', $contents), $file->sha256);
        $this->assertSame('documents', $file->disk);
        $this->assertStringStartsWith($document->public_id.'/version-1/', $file->path);
        $this->assertStringNotContainsString('informe final', $file->path);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', $file->stored_name);
        Storage::disk('documents')->assertExists($file->path);
        $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertSame(1, $document->lock_version);
        $this->assertNull($document->code);
        $this->assertNull($document->sent_at);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('document_events', [
            'document_id' => $document->id,
            'event_type' => 'file_added',
            'actor_id' => $creator->id,
        ]);
    }

    public function test_multiple_files_share_initial_version_and_second_upload_reuses_it(): void
    {
        [$document, , $creator] = $this->documentContext();

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [
                $this->uploadedFile('uno.pdf', "%PDF-1.4\nuno"),
                $this->officeFile('plan.docx', 'word/document.xml'),
                $this->officeFile('datos.xlsx', 'xl/workbook.xml'),
            ],
            'lock_version' => 0,
        ])->assertRedirect();

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile('dos.pdf', "%PDF-1.4\ndos")],
            'lock_version' => 1,
        ])->assertRedirect();

        $this->assertDatabaseCount('document_versions', 1);
        $this->assertDatabaseCount('document_files', 4);
        $this->assertSame([1], DocumentVersion::query()->pluck('version_number')->all());
        $this->assertSame(2, $document->fresh()->lock_version);
    }

    public function test_png_and_zip_are_validated_and_zip_is_not_extracted(): void
    {
        [$document, , $creator] = $this->documentContext();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [
                $this->uploadedFile('imagen.png', $png),
                $this->zipFile('respaldo.zip', ['carpeta/nota.txt' => 'contenido']),
            ],
            'lock_version' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('document_files', ['extension' => 'png', 'detected_mime_type' => 'image/png']);
        $this->assertDatabaseHas('document_files', ['extension' => 'zip']);
        $this->assertCount(2, Storage::disk('documents')->allFiles());
    }

    public function test_invalid_extension_mime_and_oversized_file_are_rejected_without_orphans(): void
    {
        [$document, , $creator] = $this->documentContext();

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile('malware.exe', 'MZcontenido')],
            'lock_version' => 0,
        ])->assertSessionHasErrors('files');

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile('falso.pdf', 'esto es texto plano')],
            'lock_version' => 0,
        ])->assertSessionHasErrors('files');

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [UploadedFile::fake()->create('grande.pdf', 20_481, 'application/pdf')],
            'lock_version' => 0,
        ])->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('document_versions', 0);
        $this->assertDatabaseCount('document_files', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame(0, $document->fresh()->lock_version);
    }

    public function test_unrelated_and_role_without_permission_cannot_upload_or_download(): void
    {
        [$document, $career, $creator] = $this->documentContext();
        $other = $this->userWithRole(RoleName::SECRETARY, $career);
        $extra = $this->userWithRole(RoleName::EXTRA, $career);
        $file = $this->uploadPdf($creator, $document);

        foreach ([$other, $extra] as $user) {
            $this->actingAs($user)->post(route('documents.files.store', $document), [
                'files' => [$this->uploadedFile('otro.pdf', "%PDF-1.4\notro")],
                'lock_version' => 1,
            ])->assertForbidden();

            $this->actingAs($user)->get(route('documents.files.download', [$document, $file]))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('document_files', 1);
    }

    public function test_authorized_download_uses_original_name_and_file_cannot_cross_documents(): void
    {
        [$document, $career, $creator] = $this->documentContext();
        $file = $this->uploadPdf($creator, $document, 'resolucion.pdf');
        $otherDocument = $this->draft($creator, $career);

        $this->actingAs($creator)->get(route('documents.files.download', [$document, $file]))
            ->assertOk()
            ->assertDownload('resolucion.pdf');

        $this->actingAs($creator)->get(route('documents.files.download', [$otherDocument, $file]))
            ->assertNotFound();
    }

    public function test_creator_can_remove_file_from_draft_and_empty_version_is_removed(): void
    {
        [$document, , $creator] = $this->documentContext();
        $file = $this->uploadPdf($creator, $document);

        $this->actingAs($creator)->delete(route('documents.files.destroy', [$document, $file]), [
            'lock_version' => 1,
        ])->assertRedirect()->assertSessionHas('status');

        Storage::disk('documents')->assertMissing($file->path);
        $this->assertDatabaseCount('document_files', 0);
        $this->assertDatabaseCount('document_versions', 0);
        $this->assertSame(2, $document->fresh()->lock_version);
    }

    public function test_sent_document_rejects_upload_and_removal_but_authorized_download_remains_available(): void
    {
        [$document, , $creator] = $this->documentContext();
        $file = $this->uploadPdf($creator, $document);
        $document->forceFill([
            'general_status' => DocumentStatus::SENT,
            'code' => 'DOC-2026-0010',
            'sender_user_id' => $creator->id,
            'sent_at' => now(),
        ])->save();

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile('nuevo.pdf', "%PDF-1.4\nnuevo")],
            'lock_version' => 1,
        ])->assertForbidden();

        $this->actingAs($creator)->delete(route('documents.files.destroy', [$document, $file]), [
            'lock_version' => 1,
        ])->assertForbidden();

        $this->actingAs($creator)->get(route('documents.files.download', [$document, $file]))
            ->assertOk();
        Storage::disk('documents')->assertExists($file->path);
    }

    public function test_stale_upload_is_rejected_without_creating_file_or_empty_version(): void
    {
        [$document, , $creator] = $this->documentContext();

        $this->uploadPdf($creator, $document, 'primero.pdf');

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile('obsoleto.pdf', "%PDF-1.4\nobsoleto")],
            'lock_version' => 0,
        ])->assertSessionHasErrors('lock_version');

        $this->assertDatabaseCount('document_versions', 1);
        $this->assertDatabaseCount('document_files', 1);
        $this->assertCount(1, Storage::disk('documents')->allFiles());
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_database_failure_rolls_back_metadata_lock_and_physical_file(): void
    {
        [$document, , $creator] = $this->documentContext();
        $event = 'eloquent.creating: '.DocumentFile::class;
        Event::listen($event, function (): never {
            throw new RuntimeException('Fallo de base de datos simulado.');
        });

        try {
            app(DocumentFileService::class)->upload($creator, $document, [
                $this->uploadedFile('compensacion.pdf', "%PDF-1.4\ncompensacion"),
            ], 0);
            $this->fail('La prueba no provocó el fallo de persistencia esperado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de base de datos simulado.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('document_versions', 0);
        $this->assertDatabaseCount('document_files', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame(0, $document->fresh()->lock_version);
    }

    public function test_document_view_lists_private_files_and_only_draft_manager_sees_upload_control(): void
    {
        [$document, $career, $creator] = $this->documentContext();
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $career);
        $this->uploadPdf($creator, $document, 'acta.pdf');

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()->assertSee('Archivos adjuntos')->assertSee('acta.pdf')->assertSee('Adjuntar archivos');

        $this->actingAs($head)->get(route('documents.show', $document))
            ->assertOk()->assertSee('acta.pdf')->assertDontSee('Adjuntar archivos');
    }

    private function uploadPdf(User $creator, Document $document, string $name = 'archivo.pdf'): DocumentFile
    {
        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedFile($name, "%PDF-1.4\ncontenido")],
            'lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect();

        return DocumentFile::query()->latest('id')->firstOrFail();
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'document-test-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function officeFile(string $name, string $requiredEntry): UploadedFile
    {
        return $this->zipFile($name, [
            '[Content_Types].xml' => '<?xml version="1.0"?><Types></Types>',
            $requiredEntry => '<?xml version="1.0"?><root></root>',
        ]);
    }

    /** @param array<string, string> $entries */
    private function zipFile(string $name, array $entries): UploadedFile
    {
        $path = storage_path('framework/testing/document-'.Str::uuid().'.zip');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $contents) {
            $archive->addFromString($entry, $contents);
        }

        $archive->close();
        $contents = file_get_contents($path);
        unlink($path);

        return $this->uploadedFile($name, $contents);
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function documentContext(): array
    {
        $career = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);

        return [$this->draft($creator, $career), $career, $creator];
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Borrador con archivos '.Str::random(6),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
    }

    private function userWithRole(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->sole();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Archivos '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }
}
