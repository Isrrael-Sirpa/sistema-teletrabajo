<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Events\DocumentNotificationRequested;
use App\Listeners\SendDocumentLifecycleNotifications;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Notifications\DocumentSentNotification;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);
    }

    public function test_send_notifies_direct_person_and_active_responsible_of_the_unit_without_duplicates(): void
    {
        [$document, $origin, $actor] = $this->documentContext();
        $destination = $this->career();
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $inactiveSecretary = $this->userWithRole(RoleName::SECRETARY, $destination, false);
        $extra = $this->userWithRole(RoleName::EXTRA, $destination);
        $direct = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $unitRecipient = $this->recipient($document, $destination);
        $directRecipient = $this->recipient($document, $direct->organizationalUnit, $direct);
        $duplicateRecipient = $this->recipient($document, $destination, $head);

        $this->dispatchLifecycle(
            $document,
            $actor,
            DocumentEventType::DOCUMENT_SENT,
            [$unitRecipient->id, $directRecipient->id, $duplicateRecipient->id],
        );

        $this->assertSame(1, $head->notifications()->count());
        $this->assertSame(0, $secretary->notifications()->count());
        $this->assertSame(1, $direct->notifications()->count());
        $this->assertSame(0, $inactiveSecretary->notifications()->count());
        $this->assertSame(0, $extra->notifications()->count());
        $this->assertSame(0, $actor->notifications()->count());
        $this->assertDatabaseCount('notificaciones', 2);
        $this->assertSame($origin->id, $document->origin_unit_id);
    }

    public function test_receive_review_and_decisions_notify_the_real_origin_participants(): void
    {
        [$document, $origin, $creator] = $this->documentContext();
        $sender = $this->userWithRole(RoleName::SECRETARY, $origin);
        $originHead = $this->userWithRole(RoleName::CAREER_HEAD, $origin);
        $originSecretary = $this->userWithRole(RoleName::SECRETARY, $origin);
        $actor = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $document->forceFill(['sender_user_id' => $sender->id])->save();

        foreach ([DocumentEventType::DOCUMENT_RECEIVED, DocumentEventType::REVIEW_STARTED] as $eventType) {
            $this->dispatchLifecycle($document, $actor, $eventType);
        }

        $this->assertSame(2, $creator->notifications()->count());
        $this->assertSame(2, $sender->notifications()->count());
        $this->assertSame(0, $originHead->notifications()->count());
        $this->assertSame(0, $originSecretary->notifications()->count());

        foreach ([
            DocumentEventType::DOCUMENT_APPROVED,
            DocumentEventType::DOCUMENT_RETURNED,
            DocumentEventType::DOCUMENT_REJECTED,
        ] as $eventType) {
            $this->dispatchLifecycle($document, $actor, $eventType, reason: 'Motivo institucional.');
        }

        $this->assertSame(5, $creator->notifications()->count());
        $this->assertSame(5, $sender->notifications()->count());
        $this->assertSame(3, $originHead->notifications()->count());
        $this->assertSame(3, $originSecretary->notifications()->count());
        $returnNotification = $creator->notifications()->get()
            ->first(fn ($notification): bool => $notification->data['event_type'] === 'document_returned');
        $rejectionNotification = $creator->notifications()->get()
            ->first(fn ($notification): bool => $notification->data['event_type'] === 'document_rejected');
        $approvalNotification = $creator->notifications()->get()
            ->first(fn ($notification): bool => $notification->data['event_type'] === 'document_approved');
        $this->assertNotNull($approvalNotification);
        $this->assertNotNull($returnNotification);
        $this->assertNotNull($rejectionNotification);
        $this->assertSame('Motivo institucional.', $returnNotification->data['reason']);
        $this->assertStringContainsString('Motivo: Motivo institucional.', $returnNotification->data['message']);
        $this->assertDatabaseHas('notificaciones', [
            'notifiable_id' => $creator->id,
            'type' => 'App\\Notifications\\DocumentDecisionNotification',
        ]);
    }

    public function test_correction_notifies_current_recipient_and_preserves_version_context(): void
    {
        [$document, , $actor] = $this->documentContext();
        $destination = $this->career();
        $recipientUser = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination, $recipientUser);

        $this->dispatchLifecycle(
            $document,
            $actor,
            DocumentEventType::CORRECTION_SENT,
            [$recipient->id],
            version: 2,
        );

        $notification = $recipientUser->notifications()->sole();
        $this->assertSame('correction_sent', $notification->data['event_type']);
        $this->assertStringContainsString('versión 2', $notification->data['message']);
    }

    public function test_restricted_notifications_do_not_persist_sensitive_context(): void
    {
        [$document, , $actor] = $this->documentContext(DocumentConfidentiality::RESTRICTED);
        $destination = $this->career();
        $recipientUser = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination, $recipientUser);

        $this->dispatchLifecycle(
            $document,
            $actor,
            DocumentEventType::DOCUMENT_SENT,
            [$recipient->id],
        );

        $data = $recipientUser->notifications()->sole()->data;
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($document->subject, $encoded);
        $this->assertStringNotContainsString((string) $document->code, $encoded);
        $this->assertNull($data['document_code']);
        $this->assertSame('Tiene un nuevo documento confidencial.', $data['message']);
    }

    public function test_notification_center_marks_one_or_all_and_never_modifies_another_users_notification(): void
    {
        [$document] = $this->documentContext();
        $user = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $other = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $this->notify($user, $document, 2);
        $this->notify($other, $document);
        $first = $user->notifications()->firstOrFail();
        $foreign = $other->notifications()->sole();

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notificaciones internas')
            ->assertSee('aria-label="Notificaciones: 2 sin leer"', false);

        $this->actingAs($user)->patch(route('notifications.read', $first->id))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)->patch(route('notifications.read', $foreign->id))->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);

        $this->actingAs($user)->patch(route('notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(2, $user->readNotifications()->count());
    }

    public function test_notification_does_not_grant_document_access(): void
    {
        [$document] = $this->documentContext();
        $unrelated = $this->userWithRole(RoleName::EXTRA, $this->career());
        $this->notify($unrelated, $document);

        $this->actingAs($unrelated)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Abrir documento');
        $this->actingAs($unrelated)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_notification_center_paginates_unread_and_read_items_separately(): void
    {
        config()->set('documents.notifications.per_page', 5);
        [$document] = $this->documentContext();
        $user = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $this->notify($user, $document, 7);
        $user->notifications()->limit(6)->update(['read_at' => now()]);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('unreadNotifications', fn ($items): bool => $items->total() === 1 && $items->perPage() === 5)
            ->assertViewHas('readNotifications', fn ($items): bool => $items->total() === 6 && $items->perPage() === 5);
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function documentContext(DocumentConfidentiality $confidentiality = DocumentConfidentiality::NORMAL): array
    {
        $origin = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Asunto reservado '.Str::random(8),
            'general_status' => DocumentStatus::SENT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => $confidentiality,
            'code' => 'FTEC-'.$origin->acronym.'-'.now()->year.'-009999',
            'lock_version' => 1,
        ]);

        return [$document, $origin, $creator];
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Notificaciones '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function userWithRole(RoleName $role, OrganizationalUnit $unit, bool $active = true): User
    {
        $user = User::factory()->create([
            'organizational_unit_id' => $unit->id,
            'status' => $active ? 'active' : 'inactive',
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function recipient(Document $document, OrganizationalUnit $unit, ?User $user = null): DocumentRecipient
    {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => $user?->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::SENT,
            'lock_version' => 1,
        ]);
    }

    /** @param array<int, int> $recipientIds */
    private function dispatchLifecycle(
        Document $document,
        User $actor,
        DocumentEventType $eventType,
        array $recipientIds = [],
        ?string $reason = null,
        ?int $version = null,
    ): void {
        app(SendDocumentLifecycleNotifications::class)->handle(new DocumentNotificationRequested(
            documentId: $document->id,
            actorId: $actor->id,
            eventType: $eventType,
            recipientIds: $recipientIds,
            reason: $reason,
            version: $version,
            occurredAt: now()->toIso8601String(),
        ));
    }

    private function notify(User $user, Document $document, int $times = 1): void
    {
        foreach (range(1, $times) as $index) {
            $user->notify(new DocumentSentNotification($document, now()->addSeconds($index)->toIso8601String()));
        }
    }
}
