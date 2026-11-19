<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTransition;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentEventService;
use App\Services\DocumentFileService;
use App\Services\DocumentWorkflowService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DocumentVersioningTest extends TestCase
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

        Storage::fake('documentos');
        config(['documents.disk' => 'documentos']);
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

    public function test_returned_document_can_prepare_complete_version_two_without_changing_version_one_or_code(): void
    {
        [$document, $creator, , , $oldFile] = $this->returnedContext();
        $originalCode = $document->code;
        $originalHash = $oldFile->sha256;
        $contents = "%PDF-1.4\nversión corregida";

        $this->prepare($creator, $document, 'informe_corregido.pdf', $contents)
            ->assertRedirect()
            ->assertSessionHas('status');

        $document->refresh();
        $version = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('version_number', 2)
            ->sole();
        $newFile = $version->files()->sole();

        $this->assertSame(DocumentStatus::RETURNED, $document->general_status);
        $this->assertSame($originalCode, $document->code);
        $this->assertSame(2, $document->versions()->count());
        $this->assertSame($originalHash, $oldFile->fresh()->sha256);
        $this->assertSame(hash('sha256', $contents), $newFile->sha256);
        $this->assertSame('application/pdf', $newFile->detected_mime_type);
        $this->assertStringContainsString('/version-2/', str_replace('\\', '/', $newFile->path));
        Storage::disk('documentos')->assertExists($oldFile->path);
        Storage::disk('documentos')->assertExists($newFile->path);
        $this->assertDatabaseHas('eventos_documentos', [
            'document_id' => $document->id,
            'event_type' => DocumentEventType::DOCUMENT_VERSION_CREATED->value,
            'actor_id' => $creator->id,
        ]);
    }

    public function test_only_returned_documents_with_a_formal_return_can_prepare_a_version(): void
    {
        foreach ([DocumentStatus::APPROVED, DocumentStatus::REJECTED, DocumentStatus::IN_REVIEW, DocumentStatus::SENT] as $status) {
            [$document, $creator] = $this->returnedContext();
            $document->forceFill(['general_status' => $status])->save();

            $this->prepare($creator, $document, 'no_permitido.pdf', "%PDF-1.4\nno")
                ->assertForbidden();
        }

        [$withoutFormalReturn, $creator] = $this->returnedContext();
        $withoutFormalReturn->recipients()->update([
            'status' => DocumentRecipientStatus::APPROVED->value,
            'decision_reason' => null,
        ]);

        try {
            app(DocumentFileService::class)->prepareCorrection(
                $creator,
                $withoutFormalReturn,
                [$this->uploadedPdf('sin_devolucion.pdf', "%PDF-1.4\nsin devolución")],
                $withoutFormalReturn->lock_version,
            );
            $this->fail('Se creó una versión sin devolución formal pendiente.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
    }

    public function test_origin_roles_and_global_supervisors_can_correct_but_destination_cannot(): void
    {
        [$document, $creator, $destinationUser] = $this->returnedContext();

        $this->prepare($destinationUser, $document, 'destino.pdf', "%PDF-1.4\ndestino")
            ->assertForbidden();

        $originHead = $this->userWithRole(RoleName::CAREER_HEAD, $document->originUnit);
        $this->prepare($originHead, $document, 'jefatura.pdf', "%PDF-1.4\njefatura")
            ->assertRedirect();

        [$adminDocument] = $this->returnedContext();
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $this->prepare($administrator, $adminDocument, 'supervision.pdf', "%PDF-1.4\nsupervisión")
            ->assertRedirect();

        $this->assertNotNull($creator->fresh());
    }

    public function test_only_one_pending_version_is_created_and_stale_lock_is_rejected(): void
    {
        [$document, $creator] = $this->returnedContext();
        $otherUnit = $this->career();
        $otherActor = $this->userWithRole(RoleName::SECRETARY, $otherUnit);
        $otherRecipient = $this->recipient(
            $document,
            $otherUnit,
            $otherActor,
            true,
            DocumentRecipientStatus::SENT,
        );
        $staleVersion = $document->lock_version;
        $this->prepare($creator, $document, 'version2.pdf', "%PDF-1.4\nversión 2", $staleVersion)
            ->assertRedirect();

        $this->prepare($creator, $document, 'duplicada.pdf', "%PDF-1.4\nduplicada", $staleVersion)
            ->assertSessionHasErrors('document_lock_version');

        $this->prepare($creator, $document, 'otra.pdf', "%PDF-1.4\notra", $document->fresh()->lock_version)
            ->assertSessionHasErrors('document');

        try {
            app(DocumentWorkflowService::class)->transition(
                $otherActor,
                $document,
                $otherRecipient,
                DocumentTransition::RECEIVE,
                $staleVersion,
                $otherRecipient->lock_version,
            );
            $this->fail('Una transición concurrente aceptó una versión de control obsoleta.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_lock_version', $exception->errors());
        }

        $this->assertSame([1, 2], $document->versions()->orderBy('version_number')->pluck('version_number')->all());
    }

    public function test_sending_correction_reopens_only_returned_formal_recipients_and_preserves_history(): void
    {
        [$document, $creator, , $returned] = $this->returnedContext();
        $approvedActor = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $approved = $this->recipient($document, $approvedActor->organizationalUnit, $approvedActor, true, DocumentRecipientStatus::APPROVED);
        $copyActor = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $copy = $this->recipient($document, $copyActor->organizationalUnit, $copyActor, false, DocumentRecipientStatus::RECEIVED);
        $document->forceFill(['due_at' => now()->addDays(5)->endOfDay()])->save();
        $code = $document->code;
        $sentAt = $document->sent_at;
        $dueAt = $document->due_at;

        DB::table('contadores_documentos')->insert([
            'origin_unit_id' => $document->origin_unit_id,
            'year' => now()->year,
            'last_number' => 17,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->prepare($creator, $document, 'version2.pdf', "%PDF-1.4\nversión 2")->assertRedirect();
        $document->refresh();

        $this->actingAs($creator)->post(route('documents.correction.send', $document), [
            'document_lock_version' => $document->lock_version,
        ])->assertRedirect()->assertSessionHas('status');

        $document->refresh();
        $returned->refresh();

        $this->assertSame(DocumentRecipientStatus::SENT, $returned->status);
        $this->assertNull($returned->received_by);
        $this->assertNull($returned->received_at);
        $this->assertNull($returned->current_handler_id);
        $this->assertNull($returned->decided_by);
        $this->assertNull($returned->decided_at);
        $this->assertNull($returned->decision_reason);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $approved->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::RECEIVED, $copy->fresh()->status);
        $this->assertSame(DocumentStatus::SENT, $document->general_status);
        $this->assertSame($code, $document->code);
        $this->assertTrue($sentAt->equalTo($document->sent_at));
        $this->assertTrue($dueAt->equalTo($document->due_at));
        $this->assertSame(17, DB::table('contadores_documentos')->value('last_number'));
        $this->assertDatabaseHas('eventos_documentos', [
            'document_id' => $document->id,
            'event_type' => DocumentEventType::DOCUMENT_RETURNED->value,
            'reason' => 'Falta firma de Jefatura.',
        ]);
        $this->assertDatabaseHas('eventos_documentos', [
            'document_id' => $document->id,
            'event_type' => DocumentEventType::CORRECTION_SENT->value,
            'from_status' => DocumentStatus::RETURNED->value,
            'to_status' => DocumentStatus::SENT->value,
        ]);
        $this->assertNull(app(DocumentFileService::class)->pendingCorrectionVersion($document));

        $this->actingAs($creator)->post(route('documents.correction.send', $document), [
            'document_lock_version' => $document->lock_version,
        ])->assertForbidden();
        $this->assertSame(1, $document->events()
            ->where('event_type', DocumentEventType::CORRECTION_SENT->value)
            ->count());
    }

    public function test_version_one_remains_downloadable_and_historical_files_cannot_be_changed(): void
    {
        [$document, $creator, , , $oldFile] = $this->returnedContext();
        $this->prepare($creator, $document, 'version2.pdf', "%PDF-1.4\nversión 2")->assertRedirect();

        $this->actingAs($creator)->get(route('documents.files.download', [$document, $oldFile]))
            ->assertOk()
            ->assertDownload('informe.pdf');

        $this->actingAs($creator)->delete(route('documents.files.destroy', [$document, $oldFile]), [
            'lock_version' => $document->fresh()->lock_version,
        ])->assertForbidden();

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->uploadedPdf('reemplazo.pdf', "%PDF-1.4\nreemplazo")],
            'lock_version' => $document->fresh()->lock_version,
        ])->assertForbidden();

        $this->assertNotNull($oldFile->fresh());
        Storage::disk('documentos')->assertExists($oldFile->path);
    }

    public function test_a_second_return_can_create_version_three(): void
    {
        [$document, $creator, $destinationUser, $recipient] = $this->returnedContext();
        $this->prepare($creator, $document, 'version2.pdf', "%PDF-1.4\nversión 2");
        app(DocumentWorkflowService::class)->sendCorrection($creator, $document, $document->fresh()->lock_version);

        $workflow = app(DocumentWorkflowService::class);
        $workflow->transition(
            $destinationUser,
            $document,
            $recipient,
            DocumentTransition::RECEIVE,
            $document->fresh()->lock_version,
            $recipient->fresh()->lock_version,
        );
        $workflow->transition(
            $destinationUser,
            $document,
            $recipient,
            DocumentTransition::START_REVIEW,
            $document->fresh()->lock_version,
            $recipient->fresh()->lock_version,
        );
        $workflow->transition(
            $destinationUser,
            $document,
            $recipient,
            DocumentTransition::RETURN,
            $document->fresh()->lock_version,
            $recipient->fresh()->lock_version,
            'Aún falta la firma final.',
        );

        $this->prepare($creator, $document, 'version3.pdf', "%PDF-1.4\nversión 3", $document->fresh()->lock_version)
            ->assertRedirect();

        $this->assertSame([1, 2, 3], $document->versions()->orderBy('version_number')->pluck('version_number')->all());
    }

    public function test_database_failure_compensates_files_and_rolls_back_version_event_and_lock(): void
    {
        [$document, $creator] = $this->returnedContext();
        $originalLock = $document->lock_version;
        $originalFiles = Storage::disk('documentos')->allFiles();
        $event = 'eloquent.creating: '.DocumentFile::class;

        Event::listen($event, function (): never {
            throw new RuntimeException('Fallo de base de datos simulado.');
        });

        try {
            app(DocumentFileService::class)->prepareCorrection(
                $creator,
                $document,
                [$this->uploadedPdf('fallida.pdf', "%PDF-1.4\nfallida")],
                $originalLock,
            );
            $this->fail('La prueba no provocó el fallo esperado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de base de datos simulado.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertSame(1, $document->versions()->count());
        $this->assertSame($originalFiles, Storage::disk('documentos')->allFiles());
        $this->assertSame($originalLock, $document->fresh()->lock_version);
        $this->assertDatabaseMissing('eventos_documentos', [
            'document_id' => $document->id,
            'event_type' => DocumentEventType::DOCUMENT_VERSION_CREATED->value,
        ]);
    }

    public function test_detail_shows_return_reason_current_version_and_versions_newest_first(): void
    {
        [$document, $creator] = $this->returnedContext();
        $this->prepare($creator, $document, 'informe_corregido.pdf', "%PDF-1.4\nversión 2");

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Falta firma de Jefatura.')
            ->assertSee('Versión 2 preparada')
            ->assertSee('Enviar corrección')
            ->assertSeeInOrder(['Versión 2', 'informe_corregido.pdf', 'Versión 1', 'informe.pdf']);
    }

    private function prepare(
        User $actor,
        Document $document,
        string $name,
        string $contents,
        ?int $lockVersion = null,
    ): TestResponse {
        return $this->actingAs($actor)->post(route('documents.versions.correction.store', $document), [
            'files' => [$this->uploadedPdf($name, $contents)],
            'document_lock_version' => $lockVersion ?? $document->fresh()->lock_version,
        ]);
    }

    /** @return array{Document, User, User, DocumentRecipient, DocumentFile} */
    private function returnedContext(): array
    {
        $origin = $this->career();
        $destination = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $destinationUser = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'sender_user_id' => $creator->id,
            'subject' => 'Documento devuelto '.Str::random(6),
            'general_status' => DocumentStatus::RETURNED,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'code' => 'FTEC-'.$origin->acronym.'-'.now()->year.'-000125',
            'sent_at' => now()->subDay(),
            'lock_version' => 4,
        ]);
        $document->forceFill(['sent_at' => now()->subDay()])->save();
        $recipient = $this->recipient(
            $document,
            $destination,
            $destinationUser,
            true,
            DocumentRecipientStatus::RETURNED,
        );
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $creator->id,
        ]);
        $oldContents = "%PDF-1.4\nversión original";
        $path = $document->public_id.'/version-1/'.Str::uuid().'.pdf';
        Storage::disk('documentos')->put($path, $oldContents);
        $file = DocumentFile::query()->create([
            'document_version_id' => $version->id,
            'disk' => 'documentos',
            'path' => $path,
            'original_name' => 'informe.pdf',
            'stored_name' => basename($path),
            'extension' => 'pdf',
            'detected_mime_type' => 'application/pdf',
            'size_bytes' => strlen($oldContents),
            'sha256' => hash('sha256', $oldContents),
            'uploaded_by' => $creator->id,
        ]);

        app(DocumentEventService::class)->record(
            $document,
            DocumentEventType::DOCUMENT_RETURNED,
            $destinationUser,
            $recipient,
            DocumentRecipientStatus::IN_REVIEW->value,
            DocumentRecipientStatus::RETURNED->value,
            'Falta firma de Jefatura.',
        );

        return [$document, $creator, $destinationUser, $recipient, $file];
    }

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        User $actor,
        bool $requiresResponse,
        DocumentRecipientStatus $status,
    ): DocumentRecipient {
        $decided = in_array($status, [
            DocumentRecipientStatus::APPROVED,
            DocumentRecipientStatus::RETURNED,
            DocumentRecipientStatus::REJECTED,
        ], true);
        $received = $status !== DocumentRecipientStatus::SENT;

        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'requires_response' => $requiresResponse,
            'status' => $status,
            'received_by' => $received ? $actor->id : null,
            'received_at' => $received ? now()->subHours(3) : null,
            'current_handler_id' => $requiresResponse && $received ? $actor->id : null,
            'decided_by' => $decided ? $actor->id : null,
            'decided_at' => $decided ? now()->subHour() : null,
            'decision_reason' => $status === DocumentRecipientStatus::RETURNED
                ? 'Falta firma de Jefatura.'
                : ($status === DocumentRecipientStatus::REJECTED ? 'Rechazo formal.' : null),
            'lock_version' => 3,
        ]);
    }

    private function uploadedPdf(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'version-test-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
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
            'name' => 'Carrera Versiones '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }
}
