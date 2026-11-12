<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ProjectAcceptanceTest extends TestCase
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

    public function test_complete_acceptance_flow_returns_corrects_and_approves_a_document(): void
    {
        $origin = $this->career('Carrera de Prueba Alfa', 'PTA');
        $destination = $this->career('Carrera de Prueba Beta', 'PTB');
        $users = $this->acceptanceUsers($origin);
        $destinationHead = $this->user(RoleName::CAREER_HEAD, $destination, 'Destino de Prueba');

        $this->actingAs($users['secretary'])->post(route('documents.store'), [
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'subject' => 'Flujo integral de aceptación',
            'description' => 'Datos ficticios para validar el cierre técnico.',
            'priority' => DocumentPriority::IMPORTANT->value,
            'confidentiality' => DocumentConfidentiality::NORMAL->value,
            'due_at' => now()->addDays(5)->toDateString(),
        ])->assertRedirect();

        $document = Document::query()->where('subject', 'Flujo integral de aceptación')->sole();

        $this->actingAs($users['secretary'])->post(route('documents.recipients.store', $document), [
            'recipient_type' => 'unit',
            'unit_id' => $destination->id,
            'requires_response' => true,
            'lock_version' => $document->lock_version,
        ])->assertRedirect();

        $recipient = $document->recipients()->sole();

        $this->actingAs($users['secretary'])->post(route('documents.files.store', $document), [
            'files' => [$this->file('pdf', 'informe-original.pdf')],
            'lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect();

        $this->send($users['secretary'], $document);
        $originalCode = $document->fresh()->code;
        $originalSentAt = $document->fresh()->sent_at;

        $this->transition($destinationHead, $document, $recipient, 'receive');
        $this->transition($destinationHead, $document, $recipient, 'start_review');
        $this->transition(
            $destinationHead,
            $document,
            $recipient,
            'return',
            'Debe corregirse el contenido de prueba.',
        );

        $this->actingAs($users['secretary'])->post(route('documents.versions.correction.store', $document), [
            'files' => [$this->file('pdf', 'informe-corregido.pdf')],
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect();

        $this->actingAs($users['secretary'])->post(route('documents.correction.send', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect();

        $this->transition($destinationHead, $document, $recipient, 'receive');
        $this->transition($destinationHead, $document, $recipient, 'start_review');
        $this->transition($destinationHead, $document, $recipient, 'approve');

        $document->refresh();
        $recipient->refresh();

        $this->assertSame(DocumentStatus::APPROVED, $document->general_status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipient->status);
        $this->assertSame($originalCode, $document->code);
        $this->assertNotNull($document->code);
        $this->assertNotNull($document->sent_at);
        $this->assertTrue($originalSentAt->equalTo($document->sent_at));
        $this->assertNotNull($recipient->received_at);
        $this->assertNotNull($recipient->decided_at);
        $this->assertSame([1, 2], $document->versions()->orderBy('version_number')->pluck('version_number')->all());
        $this->assertSame(2, DocumentFile::query()
            ->whereHas('documentVersion', fn ($query) => $query->where('document_id', $document->id))
            ->count());

        $eventTypes = $document->events()
            ->orderBy('id')
            ->get()
            ->map(fn ($event): string => $event->event_type->value)
            ->all();

        foreach ([
            DocumentEventType::DOCUMENT_CREATED->value,
            DocumentEventType::RECIPIENT_ADDED->value,
            DocumentEventType::FILE_ADDED->value,
            DocumentEventType::DOCUMENT_SENT->value,
            DocumentEventType::DOCUMENT_RECEIVED->value,
            DocumentEventType::REVIEW_STARTED->value,
            DocumentEventType::DOCUMENT_RETURNED->value,
            DocumentEventType::DOCUMENT_VERSION_CREATED->value,
            DocumentEventType::CORRECTION_SENT->value,
            DocumentEventType::DOCUMENT_APPROVED->value,
        ] as $eventType) {
            $this->assertContains($eventType, $eventTypes);
        }

        $this->assertDatabaseHas('registros_auditoria', [
            'action' => AuditAction::DOCUMENT_SENT->value,
            'auditable_id' => (string) $document->id,
        ]);
        $this->assertDatabaseHas('notificaciones', ['notifiable_id' => $destinationHead->id]);
        $this->assertDatabaseHas('notificaciones', ['notifiable_id' => $users['secretary']->id]);
        $this->assertCount(6, collect($users)->filter(fn (User $user): bool => $user->roles()->exists()));
    }

    public function test_multidestination_correction_reopens_only_returned_recipient(): void
    {
        $origin = $this->career('Carrera de Prueba Origen', 'PTO');
        $creator = $this->user(RoleName::SECRETARY, $origin, 'Secretaria Origen');
        $actors = collect([
            'A' => ['unit' => $this->career('Carrera de Prueba A', 'PRA')],
            'B' => ['unit' => $this->career('Carrera de Prueba B', 'PRB')],
            'C' => ['unit' => $this->career('Carrera de Prueba C', 'PRC')],
        ])->map(fn (array $data, string $key): array => [
            ...$data,
            'actor' => $this->user(RoleName::CAREER_HEAD, $data['unit'], "Destino {$key}"),
        ]);

        $document = $this->draft($creator, $origin, 'Aceptación multidestino');
        $recipients = $actors->map(fn (array $data): DocumentRecipient => DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $data['unit']->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::PREPARED,
            'lock_version' => 0,
        ]));

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->file('pdf', 'multidestino-original.pdf')],
            'lock_version' => $document->lock_version,
        ])->assertRedirect();

        $this->send($creator, $document);
        $this->receiveReviewDecide($actors['A']['actor'], $document, $recipients['A'], 'approve');
        $this->receiveReviewDecide(
            $actors['B']['actor'],
            $document,
            $recipients['B'],
            'return',
            'Corrección exclusiva para B.',
        );
        $this->receiveReviewDecide($actors['C']['actor'], $document, $recipients['C'], 'approve');

        $this->assertSame(DocumentStatus::RETURNED, $document->fresh()->general_status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['A']->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::RETURNED, $recipients['B']->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['C']->fresh()->status);

        $this->actingAs($creator)->post(route('documents.versions.correction.store', $document), [
            'files' => [$this->file('pdf', 'correccion-b.pdf')],
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect();
        $this->actingAs($creator)->post(route('documents.correction.send', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect();

        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['A']->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::SENT, $recipients['B']->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['C']->fresh()->status);

        $this->receiveReviewDecide($actors['B']['actor'], $document, $recipients['B'], 'approve');

        $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->general_status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['A']->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['B']->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipients['C']->fresh()->status);
        $this->assertSame(3, $document->events()
            ->where('event_type', DocumentEventType::DOCUMENT_APPROVED->value)
            ->count());
        $this->assertSame(1, $document->events()
            ->where('event_type', DocumentEventType::DOCUMENT_RETURNED->value)
            ->count());
        $this->assertSame([1, 2], $document->versions()->orderBy('version_number')->pluck('version_number')->all());
    }

    #[DataProvider('supportedFileFormats')]
    public function test_acceptance_uploads_each_supported_real_file_format(string $extension): void
    {
        $origin = $this->career('Carrera de Prueba Archivos', 'PTF');
        $creator = $this->user(RoleName::SECRETARY, $origin, 'Secretaria Archivos');
        $document = $this->draft($creator, $origin, "Archivo {$extension}");

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [$this->file($extension, "archivo.{$extension}")],
            'lock_version' => $document->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stored = DocumentFile::query()->sole();

        $this->assertSame($extension, $stored->extension);
        $this->assertSame("archivo.{$extension}", $stored->original_name);
        $this->assertNotSame('application/octet-stream', $stored->detected_mime_type);
        Storage::disk('documentos')->assertExists($stored->path);
    }

    public function test_production_seeder_does_not_create_acceptance_users(): void
    {
        User::query()->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('usuarios', 0);
    }

    /** @return array<string, array{string}> */
    public static function supportedFileFormats(): array
    {
        return [
            'PDF' => ['pdf'],
            'DOCX' => ['docx'],
            'XLSX' => ['xlsx'],
            'PPTX' => ['pptx'],
            'JPG' => ['jpg'],
            'PNG' => ['png'],
            'ZIP' => ['zip'],
        ];
    }

    /** @return array<string, User> */
    private function acceptanceUsers(OrganizationalUnit $career): array
    {
        return [
            'administrator' => $this->user(RoleName::ADMINISTRATOR, null, 'Administrador de Aceptación'),
            'co_administrator' => $this->user(RoleName::CO_ADMINISTRATOR, null, 'Co-administrador de Aceptación'),
            'head' => $this->user(RoleName::CAREER_HEAD, $career, 'Jefe de Aceptación'),
            'secretary' => $this->user(RoleName::SECRETARY, $career, 'Secretaria de Aceptación'),
            'assistant' => $this->user(RoleName::SECRETARY_ASSISTANT, $career, 'Auxiliar de Aceptación'),
            'extra' => $this->user(RoleName::EXTRA, $career, 'Extra de Aceptación'),
        ];
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'organizational_unit_id' => $unit?->id,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function career(string $name, string $acronym): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->sole();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => $name,
            'acronym' => $acronym,
            'is_active' => true,
        ]);
    }

    private function draft(User $creator, OrganizationalUnit $origin, string $subject): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => $subject,
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
    }

    private function send(User $actor, Document $document): void
    {
        $this->actingAs($actor)->post(route('documents.send', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function transition(
        User $actor,
        Document $document,
        DocumentRecipient $recipient,
        string $transition,
        ?string $reason = null,
    ): void {
        $this->actingAs($actor)->patch(
            route('documents.recipients.transition', [$document, $recipient, $transition]),
            [
                'document_lock_version' => $document->fresh()->lock_version,
                'recipient_lock_version' => $recipient->fresh()->lock_version,
                'reason' => $reason,
            ],
        )->assertRedirect()->assertSessionHasNoErrors();
    }

    private function receiveReviewDecide(
        User $actor,
        Document $document,
        DocumentRecipient $recipient,
        string $decision,
        ?string $reason = null,
    ): void {
        $this->transition($actor, $document, $recipient, 'receive');
        $this->transition($actor, $document, $recipient, 'start_review');
        $this->transition($actor, $document, $recipient, $decision, $reason);
    }

    private function file(string $extension, string $name): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'acceptance-'.Str::uuid()->toString().'.'.$extension;

        $this->temporaryFiles[] = $path;

        if ($extension === 'pdf') {
            file_put_contents($path, "%PDF-1.4\n% Archivo de aceptación\n%%EOF");
        } elseif (in_array($extension, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
            $zipPath = storage_path('framework/testing/acceptance-'.Str::uuid()->toString().'.zip');
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
            $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');

            $entry = match ($extension) {
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/workbook.xml',
                'pptx' => 'ppt/presentation.xml',
                default => 'README.txt',
            };
            $archive->addFromString($entry, $extension === 'zip' ? 'Archivo ZIP de aceptación' : '<?xml version="1.0"?><root/>');
            $archive->close();
            file_put_contents($path, file_get_contents($zipPath));
            unlink($zipPath);
        } elseif (in_array($extension, ['jpg', 'png'], true)) {
            $image = imagecreatetruecolor(2, 2);
            $this->assertNotFalse($image);

            if ($extension === 'jpg') {
                imagejpeg($image, $path);
            } else {
                imagepng($image, $path);
            }

            imagedestroy($image);
        }

        return new UploadedFile($path, $name, null, null, true);
    }
}
