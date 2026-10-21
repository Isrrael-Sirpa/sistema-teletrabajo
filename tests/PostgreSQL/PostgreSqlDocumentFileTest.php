<?php

namespace Tests\PostgreSQL;

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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostgreSqlDocumentFileTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_postgresql_service_persists_private_metadata_and_rejects_stale_upload(): void
    {
        [$document, $creator] = $this->authorizedDocumentContext();
        $service = app(DocumentFileService::class);
        $contents = "%PDF-1.4\nPostgreSQL";

        [$file] = $service->upload($creator, $document, [
            $this->uploadedPdf('integracion.pdf', $contents),
        ], 0);

        $this->assertSame(1, $file->documentVersion->version_number);
        $this->assertSame(hash('sha256', $contents), $file->sha256);
        $this->assertSame(strlen($contents), $file->size_bytes);
        Storage::disk('documents')->assertExists($file->path);

        try {
            $service->upload($creator, $document, [
                $this->uploadedPdf('obsoleto.pdf', "%PDF-1.4\nobsoleto"),
            ], 0);
            $this->fail('PostgreSQL aceptó una carga con lock_version obsoleto.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lock_version', $exception->errors());
        }

        $this->assertSame(1, DocumentVersion::query()->where('document_id', $document->id)->count());
        $this->assertSame(1, DocumentFile::query()->whereHas('documentVersion', fn ($query) => $query->where('document_id', $document->id))->count());
        $this->assertCount(1, Storage::disk('documents')->allFiles());
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_postgresql_private_preview_is_authorized_and_audited_without_changing_document(): void
    {
        [$document, $creator] = $this->authorizedDocumentContext();
        [$file] = app(DocumentFileService::class)->upload($creator, $document, [
            $this->uploadedPdf('visor.pdf', "%PDF-1.4\nVisor PostgreSQL"),
        ], 0);
        $before = $document->fresh()->getAttributes();

        $this->actingAs($creator)->get(route('documents.files.download', [$document, $file, 'presentation' => 'inline']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Disposition', 'inline; filename=visor.pdf')
            ->assertStreamedContent("%PDF-1.4\nVisor PostgreSQL");
        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $creator->id, 'action' => 'files.downloaded', 'auditable_id' => $file->id,
            'auditable_type' => DocumentFile::class,
        ]);
    }

    public function test_postgresql_enforces_version_number_size_and_hash_checks(): void
    {
        [$document, $creator] = $this->authorizedDocumentContext();

        $this->assertConstraintViolation(fn () => DB::table('document_versions')->insert([
            'document_id' => $document->id,
            'version_number' => 0,
            'uploaded_by' => $creator->id,
            'created_at' => now(),
        ]), '23514');

        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $creator->id,
        ]);

        $this->assertConstraintViolation(fn () => $this->insertFile($version, $creator, [
            'size_bytes' => 0,
        ]), '23514');

        $this->assertConstraintViolation(fn () => $this->insertFile($version, $creator, [
            'sha256' => str_repeat('G', 64),
        ]), '23514');
    }

    public function test_postgresql_prevents_duplicate_initial_version_and_storage_path(): void
    {
        [$document, $creator] = $this->authorizedDocumentContext();
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $creator->id,
        ]);

        $this->assertConstraintViolation(fn () => DB::table('document_versions')->insert([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $creator->id,
            'created_at' => now(),
        ]), '23505');

        $this->insertFile($version, $creator, ['path' => 'documento/version-1/unico.pdf']);

        $this->assertConstraintViolation(fn () => $this->insertFile($version, $creator, [
            'path' => 'documento/version-1/unico.pdf',
        ]), '23505');
    }

    /** @return array{Document, User} */
    private function authorizedDocumentContext(): array
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $career = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Archivos PG '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $creator = User::factory()->create(['organizational_unit_id' => $career->id]);
        $creator->assignRole(RoleName::SECRETARY->value);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $career->id,
            'created_by' => $creator->id,
            'subject' => 'Archivos PostgreSQL '.Str::random(6),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);

        return [$document, $creator];
    }

    /** @param array<string, mixed> $overrides */
    private function insertFile(DocumentVersion $version, User $creator, array $overrides = []): void
    {
        DB::table('document_files')->insert([
            'public_id' => Str::uuid()->toString(),
            'document_version_id' => $version->id,
            'disk' => 'documents',
            'path' => 'documento/version-1/'.Str::uuid().'.pdf',
            'original_name' => 'archivo.pdf',
            'stored_name' => Str::uuid().'.pdf',
            'extension' => 'pdf',
            'detected_mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'sha256' => hash('sha256', 'contenido'),
            'uploaded_by' => $creator->id,
            'created_at' => now(),
            ...$overrides,
        ]);
    }

    private function uploadedPdf(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'document-pg-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL aceptó metadatos de archivo inválidos.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }
}
