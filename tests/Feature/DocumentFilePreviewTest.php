<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTransition;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentFileService;
use App\Services\DocumentReviewService;
use App\Services\DocumentWorkflowService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentFilePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, InstitutionalStructureSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake('documentos');
    }

    public function test_head_and_assigned_reviewers_can_preview_pdf_without_changing_the_workflow(): void
    {
        [$document, $creator, $destination] = $this->context();
        $file = $this->file($document, $creator);
        $recipient = DocumentRecipient::query()->create([
            'document_id' => $document->id, 'unit_id' => $destination->id,
            'requires_response' => true, 'status' => 'prepared', 'lock_version' => 0,
        ]);
        $head = $this->user(RoleName::CAREER_HEAD, $destination);
        $secretary = $this->user(RoleName::SECRETARY, $destination);
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $destination);
        $workflow = app(DocumentWorkflowService::class);
        $workflow->send($creator, $document, 0);
        $workflow->transition($head, $document, $recipient, DocumentTransition::RECEIVE, 1, 1);

        foreach ([$secretary, $assistant] as $reviewer) {
            $this->actingAs($reviewer)->get($this->inlineUrl($document, $file))->assertForbidden();
            app(DocumentReviewService::class)->assign($head, $document, $recipient, [
                'assigned_to' => $reviewer->id,
                'instructions' => 'Revisar el PDF.',
                'assignment_key' => (string) Str::uuid(),
                'recipient_lock_version' => $recipient->fresh()->lock_version,
            ]);
        }

        $before = $document->fresh()->getAttributes();
        $eventCount = $document->events()->count();

        foreach ([$head, $secretary, $assistant] as $actor) {
            $this->actingAs($actor)->get($this->inlineUrl($document, $file))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'")
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
                ->assertHeader('Content-Disposition', 'inline; filename=informe.pdf')
                ->assertStreamedContent("%PDF-1.4\ncontenido privado");
        }

        $outsider = $this->user(RoleName::SECRETARY, $this->career());
        $this->actingAs($outsider)->get($this->inlineUrl($document, $file))
            ->assertForbidden()->assertHeader('X-Frame-Options', 'DENY');
        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertSame('received', $recipient->fresh()->status->value);
        $this->assertSame($eventCount, $document->events()->count());
        $this->assertSame(3, AuditLog::query()->where('action', AuditAction::FILE_DOWNLOADED->value)->count());
        $this->assertStringNotContainsString($file->path, AuditLog::query()->get()->toJson());
    }

    #[DataProvider('inlineImages')]
    public function test_validated_image_metadata_is_served_inline_with_exact_mime(string $extension, string $mime): void
    {
        [$document, $creator] = $this->context();
        $upload = UploadedFile::fake()->image('imagen.'.$extension, 2, 2);
        $contents = file_get_contents($upload->getRealPath());
        [$file] = app(DocumentFileService::class)->upload($creator, $document, [$upload], 0);
        $this->actingAs($creator)->get($this->inlineUrl($document, $file))
            ->assertOk()->assertHeader('Content-Type', $mime)
            ->assertHeader('Content-Disposition', 'inline; filename=imagen.'.$extension)
            ->assertStreamedContent($contents);
    }

    public static function inlineImages(): array
    {
        return [['jpg', 'image/jpeg'], ['jpeg', 'image/jpeg'], ['png', 'image/png']];
    }

    #[DataProvider('unsupportedFiles')]
    public function test_unsupported_types_show_fallback_without_serving_bytes(string $extension, string $mime): void
    {
        [$document, $creator] = $this->context();
        $file = $this->file($document, $creator, $extension, $mime, 'CONTENIDO-PRIVADO-NO-INLINE');
        $this->actingAs($creator)->get($this->inlineUrl($document, $file))
            ->assertStatus(415)->assertSee('Este archivo no puede visualizarse directamente en el sistema.')
            ->assertSee('Descargar archivo')->assertDontSee('CONTENIDO-PRIVADO-NO-INLINE')
            ->assertDontSee($file->path)->assertHeader('X-Frame-Options', 'DENY');
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FILE_DOWNLOADED->value)->count());
        $this->actingAs($creator)->get(route('documents.files.download', [$document, $file]))
            ->assertOk()->assertDownload('informe.'.$extension)
            ->assertHeader('Content-Type', $mime)->assertHeader('X-Frame-Options', 'DENY')
            ->assertStreamedContent('CONTENIDO-PRIVADO-NO-INLINE');
    }

    public static function unsupportedFiles(): array
    {
        return [
            ['doc', 'application/msword'],
            ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['xls', 'application/vnd.ms-excel'],
            ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['ppt', 'application/vnd.ms-powerpoint'],
            ['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ['zip', 'application/zip'],
        ];
    }

    public function test_extra_view_grant_does_not_deliver_bytes_and_download_grant_is_rechecked(): void
    {
        [$document, $creator, $destination] = $this->context();
        $document->update(['confidentiality' => DocumentConfidentiality::RESTRICTED]);
        $file = $this->file($document, $creator);
        $extra = $this->user(RoleName::EXTRA, $destination);
        $grant = DocumentAccessGrant::query()->create([
            'document_id' => $document->id, 'user_id' => $extra->id, 'granted_by' => $creator->id,
            'permission' => 'view', 'starts_at' => now()->subMinute(),
            'reason' => 'Acceso temporal de prueba al documento restringido.',
        ]);
        $this->actingAs($extra)->get(route('documents.show', $document))
            ->assertOk()->assertDontSee('data-file-viewer-open', false)->assertDontSee('<dialog', false);
        $this->actingAs($extra)->get($this->inlineUrl($document, $file))->assertForbidden();
        $this->actingAs($extra)->get(route('documents.files.download', [$document, $file]))->assertForbidden();

        $grant->update(['permission' => 'download']);
        $this->actingAs($extra)->get($this->inlineUrl($document, $file))->assertOk();
        $this->actingAs($extra)->get(route('documents.files.download', [$document, $file]))->assertDownload('informe.pdf');
        $grant->update(['ends_at' => now()->subSecond()]);
        $this->actingAs($extra)->get($this->inlineUrl($document, $file))->assertForbidden();
        $grant->update(['ends_at' => null, 'revoked_at' => now(), 'revoked_by' => $creator->id]);
        $this->actingAs($extra)->get($this->inlineUrl($document, $file))->assertForbidden();
    }

    public function test_presentation_is_controlled_and_framing_exception_cannot_be_requested_elsewhere(): void
    {
        [$document, $creator] = $this->context();
        $file = $this->file($document, $creator);
        foreach (['invalid', 'inline; filename=otra.html', ['inline'], ''] as $presentation) {
            $this->actingAs($creator)->getJson(route('documents.files.download', [$document, $file, 'presentation' => $presentation]))
                ->assertUnprocessable()->assertHeader('X-Frame-Options', 'DENY');
        }
        $this->actingAs($creator)->get(route('dashboard', ['presentation' => 'inline']))
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->actingAs($creator)->get(route('documents.files.download', [$document, $file]))
            ->assertDownload('informe.pdf')->assertHeader('X-Frame-Options', 'DENY');

        $other = $this->draft($creator, $document->originUnit);
        $this->actingAs($creator)->get($this->inlineUrl($other, $file))->assertNotFound();
    }

    public function test_missing_file_is_friendly_even_in_debug_mode_and_does_not_disclose_storage_path(): void
    {
        [$document, $creator] = $this->context();
        $file = $this->file($document, $creator);
        Storage::disk('documentos')->delete($file->path);
        config(['app.debug' => true]);

        foreach (['inline', 'attachment'] as $presentation) {
            $this->actingAs($creator)->get(route('documents.files.download', [$document, $file, 'presentation' => $presentation]))
                ->assertNotFound()->assertSee('Archivo no disponible')->assertSee('Informe al responsable')
                ->assertDontSee($file->path)->assertDontSee('Stack trace')
                ->assertHeader('X-Frame-Options', 'DENY');
        }
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FILE_DOWNLOADED->value)->count());
    }

    public function test_draft_summary_includes_each_file_before_send_and_names_are_escaped(): void
    {
        [$document, $creator] = $this->context();
        $file = $this->file($document, $creator);
        $other = $this->file($document, $creator, 'png', 'image/png', 'imagen');
        $file->update(['original_name' => 'informe <img onerror="alert(1)">.pdf']);
        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()->assertSeeInOrder(['Listo para enviar', 'Archivos que enviará', $file->original_name, 'Visualizar archivo', $other->original_name, 'Volver y corregir', 'Enviar documento'])
            ->assertSee('data-file-viewer', false)->assertDontSee('<img onerror="alert(1)">', false)
            ->assertDontSee($file->path)->assertSee('Cerrar visor')->assertSee('Siguiente');
    }

    private function context(): array
    {
        $origin = $this->career();
        $creator = $this->user(RoleName::SECRETARY, $origin);

        return [$this->draft($creator, $origin), $creator, $this->career()];
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id, 'created_by' => $creator->id,
            'subject' => 'Prueba de visor privado', 'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL, 'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
    }

    private function career(): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create([
            'parent_id' => OrganizationalUnit::query()->where('type', 'faculty')->sole()->id,
            'type' => 'career', 'name' => 'Carrera visor '.Str::random(6),
            'acronym' => Str::upper(Str::random(6)), 'is_active' => true,
        ]);
    }

    private function user(RoleName $role, OrganizationalUnit $unit): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function file(Document $document, User $uploader, string $extension = 'pdf', string $mime = 'application/pdf', string $contents = "%PDF-1.4\ncontenido privado"): DocumentFile
    {
        $version = DocumentVersion::query()->firstOrCreate(
            ['document_id' => $document->id, 'version_number' => 1],
            ['uploaded_by' => $uploader->id],
        );
        $path = 'tests/privado/'.Str::uuid().'.'.$extension;
        Storage::disk('documentos')->put($path, $contents);

        return DocumentFile::query()->create([
            'document_version_id' => $version->id, 'disk' => 'documentos', 'path' => $path,
            'original_name' => 'informe.'.$extension, 'stored_name' => basename($path),
            'extension' => $extension, 'detected_mime_type' => $mime, 'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents), 'uploaded_by' => $uploader->id,
        ]);
    }

    private function inlineUrl(Document $document, DocumentFile $file): string
    {
        return route('documents.files.download', [$document, $file, 'presentation' => 'inline']);
    }
}
