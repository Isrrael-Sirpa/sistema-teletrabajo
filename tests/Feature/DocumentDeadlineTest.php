<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentDeadlineStatus;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Notifications\DocumentDeadlineNotification;
use App\Services\DocumentDeadlineReminderService;
use App\Services\DocumentDeadlineService;
use Carbon\CarbonImmutable;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-11 08:00:00', 'America/La_Paz');
        CarbonImmutable::setTestNow($this->now);
        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_deadline_status_is_independent_from_document_status(): void
    {
        $service = app(DocumentDeadlineService::class);
        [$withoutDate] = $this->documentContext(DocumentStatus::SENT);
        [$current] = $this->documentContext(DocumentStatus::IN_REVIEW, $this->now->addDays(4)->endOfDay());
        [$dueSoon] = $this->documentContext(DocumentStatus::IN_REVIEW, $this->now->addDays(3)->endOfDay());
        [$overdue] = $this->documentContext(DocumentStatus::RECEIVED, $this->now->subMinute());
        [$returned] = $this->documentContext(DocumentStatus::RETURNED, $this->now->subDay());

        $this->assertSame(DocumentDeadlineStatus::NO_DUE_DATE, $service->status($withoutDate, $this->now));
        $this->assertSame(DocumentDeadlineStatus::CURRENT, $service->status($current, $this->now));
        $this->assertSame(DocumentDeadlineStatus::DUE_SOON, $service->status($dueSoon, $this->now));
        $this->assertSame(DocumentDeadlineStatus::OVERDUE, $service->status($overdue, $this->now));
        $this->assertSame(DocumentDeadlineStatus::OVERDUE, $service->status($returned, $this->now));
        $this->assertSame(DocumentStatus::IN_REVIEW, $dueSoon->general_status);
        $this->assertSame(DocumentStatus::RECEIVED, $overdue->general_status);
    }

    public function test_drafts_and_closed_documents_do_not_have_active_deadline_alerts_or_reminders(): void
    {
        $service = app(DocumentDeadlineService::class);

        foreach ([
            DocumentStatus::DRAFT,
            DocumentStatus::APPROVED,
            DocumentStatus::REJECTED,
            DocumentStatus::ANNULLED,
        ] as $status) {
            [$document] = $this->documentContext($status, $this->now->subDay());
            $this->assertSame(DocumentDeadlineStatus::NOT_APPLICABLE, $service->status($document, $this->now));
        }

        $result = app(DocumentDeadlineReminderService::class)->process($this->now);
        $this->assertSame(0, $result['evaluated']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_document_without_due_date_never_enters_queries_or_generates_reminder(): void
    {
        [$document] = $this->documentContext(DocumentStatus::SENT);

        $this->assertFalse(Document::query()->pendingWithDeadline()->whereKey($document->id)->exists());
        $this->assertSame(
            ['evaluated' => 0, 'generated' => 0, 'omitted' => 0],
            app(DocumentDeadlineReminderService::class)->process($this->now),
        );
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_due_soon_and_overdue_scopes_filter_in_postgresql_ready_queries(): void
    {
        [$far] = $this->documentContext(DocumentStatus::SENT, $this->now->addDays(8));
        [$soon] = $this->documentContext(DocumentStatus::IN_REVIEW, $this->now->addDays(2));
        [$late] = $this->documentContext(DocumentStatus::RETURNED, $this->now->subHour());
        [$closed] = $this->documentContext(DocumentStatus::APPROVED, $this->now->subDay());
        $service = app(DocumentDeadlineService::class);

        $this->assertSame(
            [$soon->id],
            Document::query()->dueSoon($this->now, $service->dueSoonCutoff($this->now))->pluck('id')->all(),
        );
        $this->assertSame(
            [$late->id],
            Document::query()->overdue($this->now)->pluck('id')->all(),
        );
        $this->assertFalse(Document::query()->dueSoon($this->now, $service->dueSoonCutoff($this->now))->whereKey($far->id)->exists());
        $this->assertFalse(Document::query()->overdue($this->now)->whereKey($closed->id)->exists());
    }

    public function test_due_soon_reminder_targets_real_active_destination_users_without_duplicates(): void
    {
        [$document, , $originCreator] = $this->documentContext(
            DocumentStatus::SENT,
            $this->now->addDays(3)->endOfDay(),
        );
        $destination = $this->career();
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $inactive = $this->userWithRole(RoleName::SECRETARY, $destination, false);
        $extra = $this->userWithRole(RoleName::EXTRA, $destination);
        $this->recipient($document, $destination);
        $this->recipient($document, $destination, $head);

        $result = app(DocumentDeadlineReminderService::class)->process($this->now);

        $this->assertSame(['evaluated' => 1, 'generated' => 2, 'omitted' => 0], $result);
        $this->assertSame(1, $head->notifications()->count());
        $this->assertSame(1, $secretary->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $extra->notifications()->count());
        $this->assertSame(0, $originCreator->notifications()->count());
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('due_soon', $head->notifications()->sole()->data['deadline_milestone']);
    }

    public function test_personal_destination_receives_reminder_and_repeated_runs_are_idempotent(): void
    {
        [$document] = $this->documentContext(DocumentStatus::SENT, $this->now->addDays(2));
        $destination = $this->career();
        $person = $this->userWithRole(RoleName::SECRETARY, $destination);
        $this->recipient($document, $destination, $person);
        $service = app(DocumentDeadlineReminderService::class);

        $this->assertSame(1, $service->process($this->now)['generated']);
        $this->assertSame(['evaluated' => 1, 'generated' => 0, 'omitted' => 1], $service->process($this->now));
        $this->assertSame(1, $person->notifications()->count());

        $overdueNow = CarbonImmutable::instance($document->due_at)->addSecond();
        $this->assertSame(2, $service->process($overdueNow)['generated']);
        $this->assertSame(2, $person->notifications()->count());
        $this->assertSame(1, $document->creator->notifications()->count());
        $this->assertSame(0, $service->process($overdueNow)['generated']);
        $this->assertSame(['due_soon', 'overdue'], $person->notifications()
            ->get()->pluck('data.deadline_milestone')->sort()->values()->all());
    }

    public function test_returned_document_reminds_origin_instead_of_returning_destination(): void
    {
        [$document, $origin, $creator] = $this->documentContext(DocumentStatus::RETURNED, $this->now->subDay());
        $originHead = $this->userWithRole(RoleName::CAREER_HEAD, $origin);
        $destination = $this->career();
        $destinationUser = $this->userWithRole(RoleName::SECRETARY, $destination);
        $this->recipient($document, $destination, $destinationUser, DocumentRecipientStatus::RETURNED);

        app(DocumentDeadlineReminderService::class)->process($this->now);

        $this->assertSame(1, $creator->notifications()->count());
        $this->assertSame(1, $originHead->notifications()->count());
        $this->assertSame(0, $destinationUser->notifications()->count());
    }

    public function test_restricted_reminder_is_generic_and_still_does_not_grant_access(): void
    {
        [$document] = $this->documentContext(
            DocumentStatus::SENT,
            $this->now->addDay(),
            DocumentConfidentiality::RESTRICTED,
        );
        $destination = $this->career();
        $recipient = $this->userWithRole(RoleName::SECRETARY, $destination);
        $this->recipient($document, $destination, $recipient);
        app(DocumentDeadlineReminderService::class)->process($this->now);
        $data = $recipient->notifications()->sole()->data;
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($document->subject, $encoded);
        $this->assertStringNotContainsString((string) $document->code, $encoded);
        $this->assertNull($data['document_code']);
        $this->assertSame('Un documento confidencial está próximo a vencer.', $data['message']);

        $unrelated = $this->userWithRole(RoleName::EXTRA, $this->career());
        $unrelated->notify(new DocumentDeadlineNotification(
            $document,
            DocumentDeadlineStatus::DUE_SOON,
            $this->now->toIso8601String(),
        ));
        $this->actingAs($unrelated)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_list_and_detail_show_text_badges_and_reusable_filters(): void
    {
        [$dueSoon, , $creator] = $this->documentContext(DocumentStatus::IN_REVIEW, $this->now->addDay());
        [$far] = $this->documentContext(DocumentStatus::SENT, $this->now->addDays(10), creator: $creator);

        $this->actingAs($creator)->get(route('documents.index', ['deadline' => 'due_soon']))
            ->assertOk()
            ->assertSee($dueSoon->subject)
            ->assertDontSee($far->subject)
            ->assertSee('PRÓXIMO A VENCER');
        $this->actingAs($creator)->get(route('documents.show', $dueSoon))
            ->assertOk()
            ->assertSee('PRÓXIMO A VENCER')
            ->assertSee('Fecha límite');
    }

    public function test_manual_command_and_daily_scheduler_are_registered_safely(): void
    {
        $this->artisan('documents:process-deadlines')
            ->expectsOutputToContain('Procesamiento de fechas límite finalizado.')
            ->expectsOutputToContain('Documentos evaluados: 0')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'documents:process-deadlines'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->getExpression());
        $this->assertSame('America/La_Paz', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('America/La_Paz', config('app.timezone'));
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function documentContext(
        DocumentStatus $status,
        ?CarbonImmutable $dueAt = null,
        DocumentConfidentiality $confidentiality = DocumentConfidentiality::NORMAL,
        ?User $creator = null,
    ): array {
        $origin = $creator?->organizationalUnit ?? $this->career();
        $creator ??= $this->userWithRole(RoleName::SECRETARY, $origin);
        $official = $status !== DocumentStatus::DRAFT;
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null,
            'subject' => 'Plazo '.Str::random(10),
            'general_status' => $status,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => $confidentiality,
            'due_at' => $dueAt,
            'code' => $official ? 'FTEC-'.$origin->acronym.'-2026-'.Str::upper(Str::random(6)) : null,
            'sent_at' => $official ? $this->now->subDay() : null,
            'lock_version' => $official ? 1 : 0,
        ]);

        return [$document, $origin, $creator];
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Plazos '.Str::random(8),
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

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        ?User $user = null,
        DocumentRecipientStatus $status = DocumentRecipientStatus::SENT,
    ): DocumentRecipient {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => $user?->id,
            'requires_response' => true,
            'status' => $status,
            'lock_version' => 1,
        ]);
    }
}
