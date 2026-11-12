<?php

namespace Tests\Feature;

use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DocumentInternalReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([AuthorizationSeeder::class, InstitutionalStructureSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake('documentos');
    }

    public function test_each_destination_receives_assigns_and_decides_independently(): void
    {
        [$origin, $career] = OrganizationalUnit::query()->careers()->orderBy('id')->take(2)->get()->all();
        $kardex = OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->sole();
        $sender = $this->user(RoleName::SECRETARY, $origin);
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $kardexHead = $this->user(RoleName::KARDEX_HEAD, $kardex);
        $secretary = $this->user(RoleName::SECRETARY, $career);
        $secondSecretary = $this->user(RoleName::SECRETARY, $career);
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $kardex);
        $document = $this->draft($sender);
        $file = $this->attach($document, $sender);

        $this->actingAs($sender)->get(route('documents.show', $document))->assertSee($kardex->name);
        $this->addDestination($sender, $document, $career);
        $this->addDestination($sender, $document, $kardex);
        $this->send($sender, $document);
        $careerRecipient = $document->recipients()->where('unit_id', $career->id)->sole();
        $kardexRecipient = $document->recipients()->where('unit_id', $kardex->id)->sole();

        $this->assertSame(1, $head->notifications()->count());
        $this->assertSame(1, $kardexHead->notifications()->count());
        $this->assertSame(0, $secretary->notifications()->count());
        $this->assertSame(0, $assistant->notifications()->count());
        $this->actingAs($secretary)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($head)->get(route('documents.index', ['work' => 'pending_receive']))->assertSee($document->subject);
        $this->actingAs($kardexHead)->get(route('dashboard'))->assertOk()
            ->assertSee('Encargado de Kardex')->assertSee($document->subject)->assertSee('Por recibir')->assertSee('1 adjunto')
            ->assertSee(route('documents.show', $document).'#destino-'.$kardexRecipient->id);
        $this->transition($secretary, $document, $careerRecipient, 'receive')->assertForbidden();
        $this->transition($head, $document, $kardexRecipient, 'receive')->assertForbidden();
        $this->transition($head, $document, $careerRecipient, 'receive')->assertRedirect();
        $this->assertSame(DocumentRecipientStatus::SENT, $kardexRecipient->fresh()->status);
        $this->get(route('dashboard'))->assertSee('Por asignar')
            ->assertSee(route('documents.show', $document).'#asignar-revision-'.$careerRecipient->id);
        $this->actingAs($kardexHead)->get(route('dashboard'))->assertSee('Por recibir')->assertDontSee('Por asignar');
        $this->transition($kardexHead, $document, $kardexRecipient, 'receive')->assertRedirect();

        $this->actingAs($head)->get(route('documents.show', $document))->assertSee('Asignar revisión')
            ->assertSee('id="asignar-revision-'.$careerRecipient->id.'"', false);
        $this->assign($head, $document, $careerRecipient, $assistant, 'No pertenece al equipo.')
            ->assertSessionHasErrors('assigned_to');
        $assignmentKey = (string) Str::uuid();
        $this->assign($head, $document, $careerRecipient, $secretary, 'Verificar fechas.', $assignmentKey)->assertRedirect();
        $this->assign($head, $document, $careerRecipient, $secretary, 'Verificar fechas.', $assignmentKey)->assertRedirect();
        $this->assign($head, $document, $careerRecipient, $secondSecretary, 'Verificar firmas.')->assertRedirect();
        $this->assign($kardexHead, $document, $kardexRecipient, $assistant, 'Verificar registro académico.')->assertRedirect();
        $this->assertSame(2, $careerRecipient->reviewTasks()->count());
        $this->assertSame(DocumentStatus::RECEIVED, $document->fresh()->general_status);

        $task = $careerRecipient->reviewTasks()->where('assigned_to', $secretary->id)->sole();
        $this->actingAs($secretary)->get(route('reviews.mine'))->assertOk()
            ->assertSee('Mis tareas')->assertSee('Verificar fechas.')->assertDontSee('Verificar firmas.');
        $this->get(route('documents.show', $document))->assertOk()
            ->assertSee('Entregar revisión')->assertDontSee('Verificar registro académico.');
        $this->get(route('documents.files.download', [$document, $file]))->assertOk();
        $this->get(route('reports.documents.index'))->assertViewHas('documentos', fn ($documents) => $documents->isEmpty());
        $this->actingAs($secondSecretary)->patch(route('documents.reviews.update', [$document, $task]), [
            'recommendation' => 'reject',
            'message' => 'Tarea de otra persona.',
        ])->assertForbidden();
        $this->actingAs($secretary)->patch(route('documents.reviews.update', [$document, $task]), [
            'recommendation' => 'approve',
            'message' => 'Las fechas coinciden con el registro interno.',
        ])->assertRedirect();
        $this->patch(route('documents.reviews.update', [$document, $task]), [
            'recommendation' => 'reject',
            'message' => 'Segundo intento.',
        ])->assertSessionHasErrors('message');
        $this->assertSame('Las fechas coinciden con el registro interno.', $task->fresh()->message);
        $this->assertSame(DocumentRecipientStatus::RECEIVED, $careerRecipient->fresh()->status);
        $this->assertNull($careerRecipient->fresh()->decided_by);

        $careerBoard = $this->actingAs($head)->get(route('reviews.unit'))->assertOk()
            ->assertSee('Verificar fechas.')->assertSee('Verificar firmas.')->assertDontSee('Verificar registro académico.');
        $careerDashboard = $this->get(route('dashboard'))->assertOk()
            ->assertSee('En revisión del equipo')->assertSee('Ver revisiones')->assertSee($secondSecretary->name)
            ->assertSee('#revision-'.$task->public_id)->assertDontSee($assistant->name);
        $careerTasks = $careerDashboard->viewData('teamTasks');
        $this->assertSame($careerBoard->viewData('lanes')['Pendientes']->total(), $careerTasks['pending']);
        $this->assertSame($careerBoard->viewData('lanes')['Entregadas']->total(), $careerTasks['delivered']);
        $this->assertSame(1, $careerTasks['pending']);
        $this->assertSame(1, $careerTasks['delivered']);

        $kardexDashboard = $this->actingAs($kardexHead)->get(route('dashboard'))->assertOk()
            ->assertSee($assistant->name)->assertDontSee($secondSecretary->name)->assertDontSee('#revision-'.$task->public_id);
        $this->assertSame(1, $kardexDashboard->viewData('teamTasks')['pending']);
        $this->assertSame(0, $kardexDashboard->viewData('teamTasks')['delivered']);
        $this->actingAs($head);
        $this->get(route('documents.show', $document))->assertSee('Las fechas coinciden con el registro interno.');
        $this->actingAs($sender)->get(route('documents.show', $document))->assertDontSee('Las fechas coinciden con el registro interno.');
        $this->transition($head, $document, $careerRecipient, 'start_review')->assertRedirect();
        foreach (['approve', 'reject', 'return'] as $decision) {
            $this->transition($secretary, $document, $careerRecipient, $decision)->assertForbidden();
            $this->transition($kardexHead, $document, $careerRecipient, $decision)->assertForbidden();
        }
        $this->transition($head, $document, $careerRecipient, 'approve')->assertRedirect();
        $this->assertSame($head->id, $careerRecipient->fresh()->decided_by);
        $this->assertNotNull($careerRecipient->reviewTasks()->where('assigned_to', $secondSecretary->id)->sole()->closed_at);
        $this->assertNull($kardexRecipient->reviewTasks()->sole()->closed_at);
        $this->transition($kardexHead, $document, $kardexRecipient, 'reject')->assertRedirect();
        $this->assertSame($kardexHead->id, $kardexRecipient->fresh()->decided_by);
        $this->assertTrue($sender->notifications()->get()->contains(fn ($notice) => $notice->data['event_type'] === 'document_approved'));
        $this->assertTrue($sender->notifications()->get()->contains(fn ($notice) => $notice->data['event_type'] === 'document_rejected'));
    }

    public function test_corrections_and_team_changes_preserve_task_history_and_limit_file_access(): void
    {
        [$origin, $first, $second] = OrganizationalUnit::query()->careers()->orderBy('id')->take(3)->get()->all();
        $sender = $this->user(RoleName::SECRETARY, $origin);
        $firstHead = $this->user(RoleName::CAREER_HEAD, $first);
        $secondHead = $this->user(RoleName::CAREER_HEAD, $second);
        $firstAssistant = $this->user(RoleName::SECRETARY_ASSISTANT, $first);
        $secondAssistant = $this->user(RoleName::SECRETARY_ASSISTANT, $second);
        $document = $this->draft($sender);
        $firstFile = $this->attach($document, $sender);
        $this->addDestination($sender, $document, $first);
        $this->addDestination($sender, $document, $second);
        $this->send($sender, $document);
        $firstRecipient = $document->recipients()->where('unit_id', $first->id)->sole();
        $secondRecipient = $document->recipients()->where('unit_id', $second->id)->sole();
        $this->transition($firstHead, $document, $firstRecipient, 'receive')->assertRedirect();
        $this->transition($secondHead, $document, $secondRecipient, 'receive')->assertRedirect();
        $this->assign($firstHead, $document, $firstRecipient, $firstAssistant, 'Primera revisión.')->assertRedirect();
        $firstTask = $firstRecipient->reviewTasks()->sole();
        $this->actingAs($firstAssistant)->patch(route('documents.reviews.update', [$document, $firstTask]), [
            'recommendation' => 'approve', 'message' => 'Revisión de la primera versión.',
        ])->assertRedirect();
        $this->actingAs($firstHead)->get(route('dashboard'))->assertSee('Por decidir');
        $this->assign($firstHead, $document, $firstRecipient, $firstAssistant, 'Comprobar anexos.')->assertRedirect();
        $this->transition($firstHead, $document, $firstRecipient, 'return')->assertRedirect();

        $this->actingAs($sender)->post(route('documents.versions.correction.store', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
            'files' => [
                UploadedFile::fake()->createWithContent('correccion.pdf', "%PDF-1.4\nCorrección"),
                UploadedFile::fake()->createWithContent('anexo.pdf', "%PDF-1.4\nAnexo"),
            ],
        ])->assertSessionHasNoErrors();
        $secondFile = $document->versions()->where('version_number', 2)->sole()->files()->where('original_name', 'correccion.pdf')->sole();
        $this->actingAs($sender)->post(route('documents.correction.send', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertSessionHasNoErrors();
        $this->transition($firstHead, $document, $firstRecipient, 'receive')->assertRedirect();
        $this->get(route('dashboard'))->assertSee('Por asignar')->assertSee('2 adjuntos')->assertDontSee('Por decidir');
        $this->actingAs($secondHead)->get(route('dashboard'))->assertSee('1 adjunto')->assertDontSee('2 adjuntos');
        $this->assign($firstHead, $document, $firstRecipient, $firstAssistant, 'Revisar corrección.')->assertRedirect();
        $this->assign($secondHead, $document, $secondRecipient, $secondAssistant, 'Revisar envío original.')->assertRedirect();
        $this->assertNotNull($firstRecipient->reviewTasks()->where('instructions', 'Comprobar anexos.')->sole()->closed_at);
        $this->assertSame($secondFile->document_version_id, $firstRecipient->reviewTasks()->latest('id')->firstOrFail()->document_version_id);
        $this->assertSame($firstFile->document_version_id, $secondRecipient->reviewTasks()->sole()->document_version_id);
        $this->actingAs($firstAssistant)->get(route('documents.files.download', [$document, $secondFile]))->assertOk();
        $this->actingAs($secondAssistant)->get(route('documents.files.download', [$document, $firstFile]))->assertOk();
        $this->get(route('documents.files.download', [$document, $secondFile]))->assertForbidden();
        $this->get(route('documents.show', $document))->assertDontSee('correccion.pdf');

        $secondAssistant->update(['organizational_unit_id' => $first->id]);
        $this->actingAs($secondAssistant->fresh())->get(route('reviews.mine'))->assertDontSee('Revisar envío original.');
        $this->get(route('documents.show', $document))->assertForbidden();
    }

    private function user(RoleName $role, OrganizationalUnit $unit): User
    {
        return User::factory()->create(['organizational_unit_id' => $unit->id])->assignRole($role->value);
    }

    private function draft(User $sender): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->firstOrFail()->id,
            'origin_unit_id' => $sender->organizational_unit_id,
            'created_by' => $sender->id,
            'subject' => 'Informe para revisión interna',
            'general_status' => DocumentStatus::DRAFT,
            'confidentiality' => 'restricted',
        ]);
    }

    private function attach(Document $document, User $sender): DocumentFile
    {
        $this->actingAs($sender)->post(route('documents.files.store', $document), [
            'lock_version' => $document->fresh()->lock_version,
            'files' => [UploadedFile::fake()->createWithContent('informe.pdf', "%PDF-1.4\nInforme")],
        ])->assertSessionHasNoErrors();

        return $document->versions()->sole()->files()->sole();
    }

    private function addDestination(User $sender, Document $document, OrganizationalUnit $unit): void
    {
        $this->actingAs($sender)->post(route('documents.recipients.store', $document), [
            'recipient_type' => 'unit',
            'unit_id' => $unit->id,
            'requires_response' => true,
            'lock_version' => $document->fresh()->lock_version,
        ])->assertSessionHasNoErrors();
    }

    private function send(User $sender, Document $document): void
    {
        $this->actingAs($sender)->post(route('documents.send', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertSessionHasNoErrors();
    }

    private function transition(User $actor, Document $document, DocumentRecipient $recipient, string $transition): TestResponse
    {
        return $this->actingAs($actor)->patch(route('documents.recipients.transition', [$document, $recipient, $transition]), [
            'document_lock_version' => $document->fresh()->lock_version,
            'recipient_lock_version' => $recipient->fresh()->lock_version,
            'reason' => 'Motivo de prueba.',
        ]);
    }

    private function assign(User $actor, Document $document, DocumentRecipient $recipient, User $assignee, string $instructions, ?string $key = null): TestResponse
    {
        return $this->actingAs($actor)->post(route('documents.reviews.store', [$document, $recipient]), [
            'assigned_to' => $assignee->id,
            'instructions' => $instructions,
            'assignment_key' => $key ?? (string) Str::uuid(),
            'recipient_lock_version' => $recipient->fresh()->lock_version,
        ]);
    }
}
