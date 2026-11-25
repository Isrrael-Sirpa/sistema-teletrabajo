@php
    $viewer = auth()->user();
    $isDraft = $document->general_status === App\Enums\DocumentStatus::DRAFT;
    $isOriginViewer = $document->created_by === $viewer->id || $document->origin_unit_id === $viewer->organizational_unit_id;
    $mainRecipients = $document->recipients->where('requires_response', true);
    $copyRecipients = $document->recipients->where('requires_response', false);
    $documentFiles = $document->versions->sortByDesc('version_number')->flatMap(fn ($version) => $version->files)->values();
    $canObtainFiles = Gate::allows('downloadFile', $document);
    $ownRecipient = $document->recipients
        ->filter(fn ($recipient) => $recipient->user_id === $viewer->id
            || ($recipient->unit_id === $viewer->organizational_unit_id
                && ($recipient->user_id === null || ($isUnitResponsible && $recipient->requires_response))))
        ->sortByDesc(fn ($recipient) => (int) $recipient->requires_response)
        ->first();
    $isSenderContext = $isOriginViewer && ! $ownRecipient && ! $viewer->hasInstitutionalGlobalScope();
    $recipientCounts = $mainRecipients->countBy(fn ($recipient) => $recipient->status->value);
    $unitOnlyDestinations = $mainRecipients->every(fn ($recipient) => $recipient->user_id === null
        && $recipient->unit->type === App\Enums\OrganizationalUnitType::CAREER);
    $destinationCount = $unitOnlyDestinations
        ? $mainRecipients->pluck('unit_id')->unique()->count()
        : $mainRecipients->count();
    $destinationLabel = $unitOnlyDestinations ? ($destinationCount === 1 ? 'carrera' : 'carreras') : ($destinationCount === 1 ? 'destinatario principal' : 'destinatarios principales');
    $summaryAction = $ownRecipient && ! $isDraft ? match (true) {
        $ownRecipient->status === App\Enums\DocumentRecipientStatus::SENT && Gate::allows('receiveRecipient', [$document, $ownRecipient]) => 'receive',
        $ownRecipient->status === App\Enums\DocumentRecipientStatus::RECEIVED && Gate::allows('reviewRecipient', [$document, $ownRecipient]) => 'start_review',
        $ownRecipient->status === App\Enums\DocumentRecipientStatus::IN_REVIEW && Gate::allows('approveRecipient', [$document, $ownRecipient]) => 'approve',
        default => null,
    } : null;
    $simpleStatus = match ($document->general_status) {
        App\Enums\DocumentStatus::SENT => 'Esperando recepción',
        App\Enums\DocumentStatus::RECEIVED => 'En proceso',
        App\Enums\DocumentStatus::IN_REVIEW => 'En revisión',
        App\Enums\DocumentStatus::APPROVED => 'Finalizado / Aprobado',
        App\Enums\DocumentStatus::RETURNED => 'Requiere corrección',
        App\Enums\DocumentStatus::REJECTED => 'Rechazado',
        App\Enums\DocumentStatus::ANNULLED => 'Anulado',
        default => 'Borrador',
    };
@endphp

<x-layouts.authenticated :title="$document->subject" :career-unit="$document->originUnit->type === App\Enums\OrganizationalUnitType::CAREER ? $document->originUnit : null">
    <div class="ft-page-header">
        <div>
            <p class="ft-section-heading">{{ $document->code ?? 'Documento sin correlativo' }}</p>
            <h1 class="ft-page-title max-w-4xl">{{ $document->subject }}</h1>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-ui.status-badge :status="$document->general_status" />
                <span class="ft-badge bg-slate-100 text-slate-700">Prioridad: {{ $document->priority->label() }}</span>
                @if ($document->confidentiality === App\Enums\DocumentConfidentiality::RESTRICTED)
                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-800">Acceso restringido</span>
                @endif
                @if ($deadlineStatus === App\Enums\DocumentDeadlineStatus::DUE_SOON)
                    <span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-900">PRÓXIMO A VENCER</span>
                @elseif ($deadlineStatus === App\Enums\DocumentDeadlineStatus::OVERDUE)
                    <span class="inline-flex rounded-full border border-red-300 bg-red-50 px-3 py-1 text-xs font-bold text-red-900">VENCIDO</span>
                @endif
            </div>
            <p class="mt-3 text-sm text-slate-600">{{ $document->documentType->name }} · Origen: {{ $document->originUnit->name }} · Creado el {{ $document->created_at->format('d/m/Y') }} · Límite: {{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'sin fecha' }}</p>
        </div>
        <div class="flex w-full flex-wrap items-start gap-2 sm:w-auto">
            @can('create', App\Models\WorkActivity::class)
                <a href="{{ route('activities.create', ['document' => $document->public_id]) }}" class="ft-button-secondary shrink-0 whitespace-nowrap text-sm">Asignar actividad vinculada</a>
            @endcan
            @can('update', $document)
                <a href="{{ route('documents.edit', $document) }}" class="ft-button-primary shrink-0 whitespace-nowrap text-sm">Editar borrador</a>
            @endcan
            @if ($isDraft && $document->documentType->uses_correlative && $document->code && in_array($document->authorization_status, [App\Enums\OutgoingAuthorizationStatus::NOT_REQUESTED, App\Enums\OutgoingAuthorizationStatus::RETURNED], true))
                <a href="{{ route('documents.template.download', $document) }}" class="ft-button-secondary shrink-0 whitespace-nowrap text-sm">Descargar planilla Word</a>
            @endif
            <a href="{{ route('documents.index') }}" class="ft-button-secondary shrink-0 whitespace-nowrap text-sm">Volver a documentos</a>
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" data-form-errors tabindex="-1" class="ft-alert ft-alert-error mt-6"><p class="font-bold">No se pudo completar la acción.</p><ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p class="mt-2">Revise el mensaje antes de intentarlo nuevamente. No vuelva a enviar si no puede confirmar el resultado.</p></div>
    @endif

    @if ($isDraft)
        <x-ui.document-stepper :status="$document->general_status" />
        <nav class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Secciones del borrador">
            <a href="#document-data-title" class="ft-button-secondary shrink-0">Información</a>
            <a href="#recipients-title" class="ft-button-secondary shrink-0">Destinatarios</a>
            <a href="#files-title" class="ft-button-secondary shrink-0">Archivos</a>
            <a href="#history-title" class="ft-button-secondary shrink-0">Historial</a>
            @can('send', $document)<a href="#send-title" class="ft-button-primary shrink-0">Enviar</a>@endcan
        </nav>
        <p class="ft-alert ft-alert-info mt-3">El borrador aún no es un envío oficial. Complete los destinatarios, adjunte los archivos y revise el resumen en «Enviar».</p>
    @else
        <section class="ft-card ft-card-accent mt-6 p-5 sm:p-7" aria-labelledby="tracking-summary-title">
            <p class="ft-section-heading">{{ $isSenderContext ? 'SEGUIMIENTO DEL DOCUMENTO' : ($ownRecipient ? ($ownRecipient->requires_response ? 'DOCUMENTO PARA SU ATENCIÓN' : 'COPIA INFORMATIVA') : 'ESTADO DEL DOCUMENTO') }}</p>
            <h2 id="tracking-summary-title" class="mt-2 text-xl font-bold text-slate-950 sm:text-2xl">
                @if ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::RETURNED)
                    Este documento requiere su atención.
                @elseif ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::APPROVED)
                    Documento finalizado.
                @elseif ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::REJECTED)
                    Documento rechazado.
                @elseif ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::ANNULLED)
                    Documento anulado.
                @elseif ($isSenderContext)
                    Documento enviado.
                @elseif ($ownRecipient && ! $ownRecipient->requires_response)
                    Copia informativa para {{ $ownRecipient->user_id ? 'usted' : ($ownRecipient->unit->type === App\Enums\OrganizationalUnitType::CAREER ? 'su carrera' : 'su unidad') }}.
                @elseif ($ownRecipient)
                    @switch ($ownRecipient->status)
                        @case (App\Enums\DocumentRecipientStatus::SENT) Nuevo documento @break
                        @case (App\Enums\DocumentRecipientStatus::RECEIVED) Documento recibido @break
                        @case (App\Enums\DocumentRecipientStatus::IN_REVIEW) Documento en revisión @break
                        @case (App\Enums\DocumentRecipientStatus::APPROVED) Documento aprobado @break
                        @case (App\Enums\DocumentRecipientStatus::RETURNED) Documento devuelto @break
                        @case (App\Enums\DocumentRecipientStatus::REJECTED) Documento rechazado @break
                        @default Seguimiento del documento
                    @endswitch
                @else
                    Seguimiento del documento.
                @endif
            </h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                @if ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::RETURNED)
                    Una unidad destinataria solicitó una corrección. Revise sus observaciones antes de preparar la nueva versión.
                @elseif ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::REJECTED)
                    Se registró un rechazo. Consulte el seguimiento y el historial para conocer la decisión.
                @elseif ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::ANNULLED)
                    Este documento quedó sin efecto institucional. Su expediente e historial se conservan.
                @elseif ($isSenderContext)
                    El envío ya está realizado. Aquí puede ver cómo avanza cada destinatario.
                @elseif ($ownRecipient && ! $ownRecipient->requires_response)
                    Este documento se comparte solo para su conocimiento. No necesita aprobarlo ni responderlo.
                @elseif ($ownRecipient)
                    @switch ($ownRecipient->status)
                        @case (App\Enums\DocumentRecipientStatus::SENT)
                            Este documento fue enviado a {{ match (true) {
                                $ownRecipient->user_id === $viewer->id => 'usted',
                                $ownRecipient->user_id !== null => 'una persona de su unidad',
                                $ownRecipient->unit->type === App\Enums\OrganizationalUnitType::CAREER => 'su carrera',
                                default => 'su unidad',
                            } }}.
                            {{ $summaryAction === 'receive' ? 'Puede recibirlo ahora.' : 'Puede consultar sus archivos y seguimiento.' }}
                            @break
                        @case (App\Enums\DocumentRecipientStatus::RECEIVED)
                            {{ $summaryAction === 'start_review' ? 'Puede revisar el contenido antes de iniciar su evaluación.' : 'El documento ya fue recibido. Puede consultar sus archivos y seguimiento.' }}
                            @break
                        @case (App\Enums\DocumentRecipientStatus::IN_REVIEW)
                            Está en revisión. La persona autorizada puede registrar la decisión.
                            @break
                        @case (App\Enums\DocumentRecipientStatus::APPROVED)
                            Su destino aprobó el documento. No hay acciones pendientes.
                            @break
                        @case (App\Enums\DocumentRecipientStatus::RETURNED)
                            Su destino solicitó una corrección al remitente.
                            @break
                        @case (App\Enums\DocumentRecipientStatus::REJECTED)
                            Su destino rechazó el documento. Consulte el historial si necesita más detalles.
                            @break
                        @default
                            Consulte el estado de su destino y las acciones que tenga disponibles.
                    @endswitch
                @else
                    Consulte el avance de los destinatarios y la información conservada del expediente.
                @endif
            </p>
            <dl class="mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Destinatarios principales</dt><dd class="mt-1 text-sm font-bold text-slate-950">{{ $mainRecipients->map(fn ($recipient) => $recipient->user?->name ?? $recipient->unit->name)->join(', ') ?: 'Ninguno' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Copias informativas</dt><dd class="mt-1 text-sm font-bold text-slate-950">{{ $copyRecipients->map(fn ($recipient) => $recipient->user?->name ?? $recipient->unit->name)->join(', ') ?: 'Ninguna' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Fecha de envío</dt><dd class="mt-1 text-base font-bold text-slate-950">{{ $document->sent_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'No disponible' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Estado general</dt><dd class="mt-1 text-base font-bold text-slate-950">{{ $simpleStatus }}</dd></div>
            </dl>
            @if ($isSenderContext)
                <p @class(['mt-5 rounded-lg px-4 py-3 text-sm font-semibold', 'bg-amber-50 text-amber-950' => $document->general_status === App\Enums\DocumentStatus::RETURNED, 'bg-blue-50 text-blue-950' => $document->general_status !== App\Enums\DocumentStatus::RETURNED])>
                    @if ($document->general_status === App\Enums\DocumentStatus::RETURNED)
                        {{ $pendingCorrectionVersion ? 'La corrección está preparada. Revise las observaciones y envíela cuando esté lista.' : 'Revise las observaciones y prepare la corrección cuando corresponda.' }}
                    @elseif ($document->general_status === App\Enums\DocumentStatus::APPROVED)
                        No tiene acciones pendientes. El trámite quedó aprobado.
                    @elseif ($document->general_status === App\Enums\DocumentStatus::REJECTED)
                        No hay una corrección disponible para este estado. Consulte las decisiones registradas.
                    @elseif ($document->general_status === App\Enums\DocumentStatus::ANNULLED)
                        No puede realizar nuevas acciones sobre este documento.
                    @else
                        Por ahora no necesita realizar ninguna acción.
                    @endif
                </p>
            @endif
            <p class="mt-4 text-sm font-semibold text-slate-700">Archivos adjuntos: {{ $documentFiles->count() }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                @if ($canObtainFiles && $documentFiles->isNotEmpty())
                    <a href="#files-title" data-file-viewer-open="{{ $documentFiles->first()->public_id }}" aria-haspopup="dialog" class="ft-button-secondary">Visualizar archivos</a>
                @endif
                @if ($summaryAction)
                    <form id="destino-{{ $ownRecipient->id }}" tabindex="-1" class="scroll-mt-24" method="POST" action="{{ route('documents.recipients.transition', [$document, $ownRecipient, $summaryAction]) }}" @if ($summaryAction === 'approve') data-confirm-title="Aprobar documento" data-confirm-message="La decisión quedará registrada en el historial." @endif>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                        <input type="hidden" name="recipient_lock_version" value="{{ $ownRecipient->lock_version }}">
                        <button type="submit" class="ft-button-primary">{{ match ($summaryAction) {
                            'receive' => $ownRecipient->requires_response ? 'Recibir documento' : 'Registrar recepción de copia',
                            'start_review' => 'Iniciar revisión oficial',
                            'approve' => 'Aprobar documento',
                        } }}</button>
                    </form>
                @endif
                @if ($assignableDestinations->isNotEmpty())
                    <a href="#revisiones" class="ft-button-secondary">Asignar revisión</a>
                @elseif ($reviewTasks->isNotEmpty())
                    <a href="#revisiones" class="ft-button-secondary">Ver revisiones internas</a>
                @endif
                <a href="#recipients-title" class="{{ $summaryAction ? 'ft-button-secondary' : 'ft-button-primary' }}">Ver seguimiento</a>
                @if ($isSenderContext && $document->general_status === App\Enums\DocumentStatus::RETURNED)
                    <a href="#correction-title" class="ft-button-secondary">Ver observaciones</a>
                    @if ($pendingCorrectionVersion)
                        @can('sendCorrection', $document)<a href="#correction-title" class="ft-button-secondary">Enviar corrección</a>@endcan
                    @else
                        @can('createCorrectionVersion', $document)<a href="#correction-title" class="ft-button-secondary">Preparar corrección</a>@endcan
                    @endif
                @endif
                @unless ($canObtainFiles && $documentFiles->isNotEmpty())
                    <a href="#files-title" class="ft-button-secondary">Ver archivos</a>
                @endunless
                <a href="#history-title" class="ft-button-secondary">Ver historial</a>
            </div>
        </section>
    @endif

    @if ($document->general_status === App\Enums\DocumentStatus::ANNULLED)
        <section class="mt-6 rounded-2xl border-2 border-red-700 bg-red-50 p-5 shadow-sm sm:p-7" aria-labelledby="annulled-title">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-red-800">Expediente histórico</p>
            <h2 id="annulled-title" class="mt-2 text-2xl font-black text-red-950">DOCUMENTO ANULADO</h2>
            <p class="mt-2 text-sm leading-6 text-red-900">Este documento quedó sin efecto institucional. El código, archivos, versiones, destinatarios, decisiones e historial permanecen conservados.</p>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-red-800">Código</dt><dd class="mt-1 text-sm font-semibold text-red-950">{{ $document->code }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-red-800">Anulado por</dt><dd class="mt-1 text-sm font-semibold text-red-950">{{ $document->annuller?->name ?? 'Usuario institucional' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-red-800">Fecha de anulación</dt><dd class="mt-1 text-sm font-semibold text-red-950">{{ $document->annulled_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</dd></div>
            </dl>
            <div class="mt-4 rounded-xl border border-red-200 bg-white px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-wide text-red-800">Motivo inmutable</p>
                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $document->annulment_reason }}</p>
            </div>
        </section>
    @endif

    @unless ($isDraft)
        <details @if ($taskOnly) open @endif class="ft-card mt-6 p-5 sm:p-7">
            <summary id="document-data-title" class="text-lg font-bold text-slate-950">Información del documento <span class="ml-2 text-sm font-normal text-slate-600">(ver detalles)</span></summary>
    @else
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="document-data-title">
            <h2 id="document-data-title" class="text-lg font-bold text-slate-950">Datos del documento</h2>
    @endunless
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Cite</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $document->code ?? 'Sin correlativo' }}</dd>@if ($isDraft && $document->code)<p class="mt-1 text-xs text-slate-500">Reservado el {{ $document->correlative_reserved_at?->format('d/m/Y H:i') }}. El número no cambiará.</p>@elseif($isDraft && $document->documentType->uses_correlative)<p class="mt-1 text-xs text-slate-500">Debe reservar el cite desde Control de correlativos antes de enviar.</p>@endif</div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Tipo documental</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->documentType->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Unidad de origen</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->originUnit->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Creado por</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->creator->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Prioridad</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->priority->label() }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Confidencialidad</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->confidentiality->label() }}</dd></div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Fecha límite</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Sin fecha límite' }}</dd>
                @if ($deadlineStatus === App\Enums\DocumentDeadlineStatus::DUE_SOON)
                    <p class="mt-1 text-xs font-bold text-amber-800">Próximo a vencer.</p>
                @elseif ($deadlineStatus === App\Enums\DocumentDeadlineStatus::OVERDUE)
                    <p class="mt-1 text-xs font-bold text-red-800">La fecha límite ya venció y el trámite requiere actuación.</p>
                @endif
            </div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Creación</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Envío oficial</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->sent_at?->format('d/m/Y H:i') ?? 'Todavía no enviado' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Versión actual</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $currentVersionNumber > 0 ? 'Versión '.$currentVersionNumber : 'Sin archivos adjuntos' }}</dd></div>
        </dl>

        <div class="mt-6 border-t border-slate-200 pt-5">
            <h3 class="text-sm font-bold text-slate-800">Descripción</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $document->description ?: 'Sin descripción.' }}</p>
        </div>
    @unless ($isDraft)
        </details>
    @else
        </section>
    @endunless

    <section @if ($isDraft && $canManageRecipients) data-recipient-editor="{{ $document->public_id }}" @endif class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="recipients-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="recipients-title" class="text-lg font-bold text-slate-950">{{ $isDraft ? 'Destinatarios' : 'Seguimiento por destinatario' }}</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    @if ($isDraft)
                        Estos destinatarios están preparados. El documento todavía no fue enviado.
                    @elseif ($document->general_status === App\Enums\DocumentStatus::ANNULLED)
                        Se conservan los estados que tenía cada destinatario al anular el documento.
                    @else
                        Vea quién recibió el documento, quién lo revisa y qué decisiones se registraron.
                    @endif
                </p>
            </div>
            <span data-recipient-counts class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ $mainRecipients->count() }} {{ $mainRecipients->count() === 1 ? 'principal' : 'principales' }} · {{ $copyRecipients->count() }} {{ $copyRecipients->count() === 1 ? 'copia' : 'copias' }}</span>
        </div>

        @unless ($isDraft)
            @if ($mainRecipients->count() > 1)
                <div class="mt-5 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-700" aria-label="Resumen de destinatarios">
                    <span class="mr-2 font-bold text-slate-950">{{ $destinationCount }} {{ $destinationLabel }}:</span>
                    @foreach (['approved' => ['aprobó', 'aprobaron'], 'in_review' => ['en revisión', 'en revisión'], 'received' => ['recibió', 'recibieron'], 'sent' => ['espera recepción', 'esperan recepción'], 'returned' => ['devolvió', 'devolvieron'], 'rejected' => ['rechazó', 'rechazaron']] as $status => $summary)
                        @if (($recipientCounts[$status] ?? 0) > 0)
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 font-semibold">{{ $recipientCounts[$status] }} {{ $summary[$recipientCounts[$status] === 1 ? 0 : 1] }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="mt-5">
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">Destinatarios principales</h3>
                <p class="mt-1 text-sm text-slate-600">Deben atender el documento.</p>
                <div class="mt-3 grid gap-3">
                    @forelse ($mainRecipients as $recipient)
                        @include('documents.partials.recipient-tracking-card', ['recipient' => $recipient, 'document' => $document, 'isSenderContext' => $isSenderContext])
                    @empty
                        <p class="text-sm text-slate-600">No hay destinatarios principales.</p>
                    @endforelse
                </div>
            </div>
            @if ($copyRecipients->isNotEmpty())
                <div class="mt-6 border-t border-slate-200 pt-5">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Copias informativas</h3>
                    <p class="mt-1 text-sm text-slate-600">Solo reciben el documento para conocimiento; no necesitan aprobarlo ni responderlo.</p>
                    <div class="mt-3 grid gap-3">
                        @foreach ($copyRecipients as $recipient)
                            @include('documents.partials.recipient-tracking-card', ['recipient' => $recipient, 'document' => $document, 'isSenderContext' => $isSenderContext])
                        @endforeach
                    </div>
                </div>
            @endif
        @else
        <p data-recipient-feedback role="status" aria-live="polite" hidden class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950"></p>
        <button type="button" data-recipient-sync hidden class="ft-button-secondary mt-2">Reintentar actualización</button>
        <div data-recipient-list>
        @if ($document->recipients->isNotEmpty())
            <div class="ft-table-wrap mt-5">
                <table class="ft-data-table divide-y divide-slate-200" role="table">
                    <caption class="sr-only">Destinatarios principales y copias informativas preparados para este borrador.</caption>
                    <thead class="bg-slate-50" role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Destinatario</th>
                            <th scope="col" role="columnheader" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Finalidad</th>
                            <th scope="col" role="columnheader" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                            <th scope="col" role="columnheader" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200" role="rowgroup">
                        @foreach ([['title' => 'Destinatarios principales', 'help' => 'Deben atender el documento.', 'items' => $mainRecipients], ['title' => 'Copias informativas', 'help' => 'Solo reciben el documento para conocimiento.', 'items' => $copyRecipients]] as $group)
                            <tr role="row" class="bg-slate-50"><th role="rowheader" scope="rowgroup" colspan="4" class="px-4 py-3 text-left"><span class="block text-sm font-bold text-slate-900">{{ $group['title'] }}</span><span class="block text-xs font-normal text-slate-600">{{ $group['help'] }}</span></th></tr>
                        @forelse ($group['items'] as $recipient)
                            <tr role="row">
                                <td role="cell" data-label="Destinatario" class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $recipient->user?->name ?? $recipient->unit->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $recipient->user ? 'Persona específica · '.$recipient->unit->name : 'Carrera o unidad completa' }}</p>
                                </td>
                                <td role="cell" data-label="Finalidad" class="px-4 py-4">
                                    @if ($canManageRecipients)
                                        <form method="POST" action="{{ route('documents.recipients.update', $document) }}" data-recipient-operation="update" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="recipient_id" value="{{ $recipient->id }}">
                                            <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                                            <label class="sr-only" for="requires_response_{{ $recipient->id }}">Finalidad para {{ $recipient->user?->name ?? $recipient->unit->name }}</label>
                                            <select id="requires_response_{{ $recipient->id }}" name="requires_response" class="min-h-10 rounded-lg border-slate-300 py-2 text-sm focus:border-blue-600 focus:ring-blue-600">
                                                <option value="1" @selected($recipient->requires_response) @disabled($recipient->user_id !== null)>Destinatario principal</option>
                                                <option value="0" @selected(! $recipient->requires_response)>Copia informativa</option>
                                            </select>
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Actualizar</button>
                                        </form>
                                    @else
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                            'bg-emerald-50 text-emerald-800' => $recipient->requires_response,
                                            'bg-slate-100 text-slate-700' => ! $recipient->requires_response,
                                        ])>{{ $recipient->requires_response ? 'Destinatario principal' : 'Copia informativa' }}</span>
                                    @endif
                                </td>
                                <td role="cell" data-label="Estado" class="px-4 py-4">
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-800">{{ $recipient->status->label() }}</span>
                                    @if ($recipient->receiver)
                                        <p class="mt-2 text-xs text-slate-500">Recibido por {{ $recipient->receiver->name }}<br>{{ $recipient->received_at?->format('d/m/Y H:i') }}</p>
                                    @endif
                                    @if ($recipient->decisionMaker)
                                        <p class="mt-2 text-xs text-slate-500">Decisión de {{ $recipient->decisionMaker->name }}<br>{{ $recipient->decided_at?->format('d/m/Y H:i') }}</p>
                                    @endif
                                    @if ($recipient->decision_reason)
                                        <p class="mt-2 max-w-sm whitespace-pre-line text-xs text-slate-600">Motivo: {{ $recipient->decision_reason }}</p>
                                    @endif
                                </td>
                                <td role="cell" data-label="Acciones" class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                    @if ($canManageRecipients)
                                        <form method="POST" action="{{ route('documents.recipients.destroy', $document) }}" data-recipient-operation="remove" data-confirm-title="Quitar destinatario" data-confirm-message="Se retirará del borrador.">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="recipient_id" value="{{ $recipient->id }}">
                                            <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50">Quitar</button>
                                        </form>
                                    @endif

                                    @can('receiveRecipient', [$document, $recipient])
                                        <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'receive']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                            <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                            <button type="submit" class="ft-button-primary">Recibir documento</button>
                                        </form>
                                    @endcan

                                    @can('reviewRecipient', [$document, $recipient])
                                        <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'start_review']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                            <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                            <button type="submit" class="ft-button-primary">Iniciar revisión oficial</button>
                                        </form>
                                    @endcan

                                    @can('approveRecipient', [$document, $recipient])
                                        <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'approve']) }}" data-confirm-title="Aprobar documento" data-confirm-message="La decisión quedará registrada en el historial.">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                            <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-800">Aprobar</button>
                                        </form>
                                    @endcan

                                    @if ($recipient->status === App\Enums\DocumentRecipientStatus::IN_REVIEW)
                                        @can('returnRecipient', [$document, $recipient])
                                            <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'return']) }}" class="w-full max-w-xs rounded-lg border border-amber-200 bg-amber-50 p-3 text-left">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                                <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                                <label for="return_reason_{{ $recipient->id }}" class="block text-xs font-bold text-amber-950">Motivo de devolución</label>
                                                <textarea id="return_reason_{{ $recipient->id }}" name="reason" required maxlength="{{ config('documents.workflow.reason_max_length') }}" rows="2" class="mt-2 block w-full rounded-lg border-amber-300 text-sm focus:border-amber-600 focus:ring-amber-600"></textarea>
                                                <button type="submit" class="mt-2 inline-flex min-h-10 items-center rounded-lg bg-amber-700 px-3 py-2 text-sm font-bold text-white hover:bg-amber-800">Devolver</button>
                                            </form>
                                        @endcan

                                        @can('rejectRecipient', [$document, $recipient])
                                            <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'reject']) }}" class="w-full max-w-xs rounded-lg border border-red-200 bg-red-50 p-3 text-left">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                                <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                                <label for="reject_reason_{{ $recipient->id }}" class="block text-xs font-bold text-red-950">Motivo de rechazo</label>
                                                <textarea id="reject_reason_{{ $recipient->id }}" name="reason" required maxlength="{{ config('documents.workflow.reason_max_length') }}" rows="2" class="mt-2 block w-full rounded-lg border-red-300 text-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                                <button type="submit" class="mt-2 inline-flex min-h-10 items-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800">Rechazar</button>
                                            </form>
                                        @endcan
                                    @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr role="row"><td role="cell" colspan="4" class="px-4 py-3 text-sm text-slate-500">Ninguno por ahora.</td></tr>
                        @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Todavía no se agregaron destinatarios.</div>
        @endif
        </div>

        @if ($canManageRecipients)
            <div class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="text-base font-bold text-slate-950">Destinatarios principales</h3>
                <p class="mt-1 text-sm text-slate-600">Seleccione las carreras o Kardex que deben responder al documento. Cada unidad lo recibirá a través de su responsable.</p>
                <form method="POST" action="{{ route('documents.recipients.store', $document) }}" data-multi-career-form class="mt-4 rounded-xl border border-blue-200 bg-blue-50/40 p-4">
                    @csrf
                    <input type="hidden" name="lock_version" value="{{ $document->lock_version }}" data-multi-career-lock-version>
                    <label for="career_search" class="block text-sm font-bold text-slate-800">Buscar carrera o Kardex</label>
                    <input id="career_search" type="search" data-career-search placeholder="Escriba el nombre o la sigla" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <fieldset class="mt-4">
                        <legend class="sr-only">Unidades destinatarias principales</legend>
                        <div data-career-options class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($recipientUnits as $unit)
                                @php $existingUnitRecipient = $document->recipients->first(fn ($recipient) => $recipient->user_id === null && $recipient->unit_id === $unit->id); @endphp
                                <label data-career-option class="flex min-h-12 items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 {{ $existingUnitRecipient ? 'opacity-70' : 'cursor-pointer hover:border-blue-500' }}">
                                    <input type="checkbox" data-career-choice data-career-name="{{ $unit->name }}" value="{{ $unit->id }}" @checked($existingUnitRecipient) @disabled($existingUnitRecipient) class="mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                                    <span><span class="font-bold">{{ $unit->acronym }}</span> · {{ $unit->name }}@if ($existingUnitRecipient)<span class="block text-xs text-slate-600">{{ $existingUnitRecipient->requires_response ? 'Ya es destinatario principal' : 'Ya está como copia; cambie su finalidad arriba' }}</span>@endif</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <p data-career-selection-count aria-live="polite" class="mt-4 text-sm font-semibold text-slate-800">0 unidades seleccionadas.</p>
                    <button type="submit" data-career-submit disabled class="ft-button-primary mt-4 disabled:cursor-not-allowed disabled:opacity-50">Agregar unidades seleccionadas</button>
                </form>
                <noscript>
                    <form method="POST" action="{{ route('documents.recipients.store', $document) }}" class="mt-4 rounded-xl border border-slate-200 p-4">
                        @csrf
                        <input type="hidden" name="recipient_type" value="unit">
                        <input type="hidden" name="requires_response" value="1">
                        <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                        <label for="single_career" class="block text-sm font-bold">Agregar una unidad destinataria</label>
                        <select id="single_career" name="unit_id" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Seleccione una unidad</option>
                            @foreach ($recipientUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->acronym }} · {{ $unit->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="ft-button-primary mt-4">Agregar unidad</button>
                    </form>
                </noscript>
            </div>

            <div class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="text-base font-bold text-slate-950">Copia informativa <span class="text-sm font-normal text-slate-500">· Opcional</span></h3>
                <p class="mt-1 text-sm text-slate-600">Estas personas o unidades recibirán el documento solo para conocimiento. No necesitan aprobarlo ni responderlo.</p>
                <details class="mt-4 rounded-xl border border-slate-200 p-4">
                    <summary class="cursor-pointer font-bold text-blue-800">+ Agregar copia informativa</summary>
                    <div class="mt-4 grid gap-5 border-t border-slate-200 pt-4 lg:grid-cols-2">
                        <form method="POST" action="{{ route('documents.recipients.store', $document) }}" data-recipient-operation="add-copy">
                            @csrf
                            <input type="hidden" name="recipient_type" value="unit">
                            <input type="hidden" name="requires_response" value="0">
                            <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                            <label for="copy_unit_id" class="block text-sm font-bold text-slate-800">Carrera o Kardex</label>
                            <select id="copy_unit_id" name="unit_id" data-copy-unit-options required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300">
                                <option value="">Seleccione una unidad</option>
                                @foreach ($recipientUnits as $unit)
                                    <option value="{{ $unit->id }}" @disabled($document->recipients->contains(fn ($recipient) => $recipient->user_id === null && $recipient->unit_id === $unit->id))>{{ $unit->acronym }} · {{ $unit->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="ft-button-secondary mt-3">Agregar copia de unidad</button>
                        </form>
                        <form method="POST" action="{{ route('documents.recipients.store', $document) }}" data-recipient-operation="add-copy">
                            @csrf
                            <input type="hidden" name="recipient_type" value="person">
                            <input type="hidden" name="requires_response" value="0">
                            <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                            <label for="copy_user_id" class="block text-sm font-bold text-slate-800">Persona específica</label>
                            <select id="copy_user_id" name="user_id" data-copy-person-options required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300">
                                <option value="">Seleccione una persona</option>
                                @foreach ($recipientUsers as $user)
                                    <option value="{{ $user->id }}" @disabled($document->recipients->contains(fn ($recipient) => $recipient->user_id === $user->id))>{{ $user->name }} · {{ $user->organizationalUnit->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="ft-button-secondary mt-3">Agregar copia de persona</button>
                        </form>
                    </div>
                </details>
            </div>
        @endif
        @endunless
    </section>

    @if ($document->general_status === App\Enums\DocumentStatus::RETURNED)
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-7" aria-labelledby="correction-title">
            <h2 id="correction-title" class="text-lg font-bold text-amber-950">Documento devuelto: preparar corrección</h2>
            <p class="mt-2 text-sm leading-6 text-amber-900">La corrección crea una versión nueva. Las versiones y decisiones anteriores permanecen intactas.</p>

            <div class="mt-4 space-y-3">
                @foreach ($returnedRecipients as $recipient)
                    <div class="rounded-xl border border-amber-200 bg-white px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-800">Motivo indicado por {{ $recipient->user?->name ?? $recipient->unit->name }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $recipient->decision_reason }}</p>
                    </div>
                @endforeach
            </div>

            @can('createCorrectionVersion', $document)
                @if ($pendingCorrectionVersion)
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="font-bold text-emerald-950">Versión {{ $pendingCorrectionVersion->version_number }} preparada</p>
                        <p class="mt-1 text-sm leading-6 text-emerald-900">Revise los {{ $pendingCorrectionVersion->files->count() }} archivos en el historial de versiones. Solo al enviar la corrección volverán al flujo los destinatarios que devolvieron el documento.</p>

                        @can('sendCorrection', $document)
                            <form method="POST" action="{{ route('documents.correction.send', $document) }}" class="mt-4" data-confirm-title="Enviar corrección" data-confirm-message="Los destinatarios devueltos volverán al estado Enviado.">
                                @csrf
                                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                <button type="submit" class="ft-button-primary">Enviar corrección</button>
                            </form>
                        @endcan
                    </div>
                @else
                    <form method="POST" action="{{ route('documents.versions.correction.store', $document) }}" enctype="multipart/form-data" class="mt-5 rounded-xl border border-amber-200 bg-white p-4">
                        @csrf
                        <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                        <label for="correction_files" class="block text-sm font-bold text-slate-900">Archivos completos de la nueva versión</label>
                        <input
                            id="correction_files"
                            type="file"
                            name="files[]"
                            multiple
                            required
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip"
                            class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:min-h-11 file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:font-bold file:text-slate-700 hover:file:bg-slate-200"
                            aria-describedby="correction-files-help"
                        >
                        <p id="correction-files-help" class="mt-2 text-xs leading-5 text-slate-600">Seleccione el conjunto documental corregido. Máximo {{ config('documents.uploads.max_files_per_request') }} archivos y {{ config('documents.uploads.max_size_kb') / 1024 }} MB por archivo.</p>
                        <button type="submit" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-800">Preparar nueva versión</button>
                    </form>
                @endif
            @endcan
        </section>
    @endif

    @include('documents.reviews._section')

    @if ($isDraft)
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="files-title">
            <h2 id="files-title" class="text-lg font-bold text-slate-950">Archivos adjuntos</h2>
    @else
        <details class="ft-card mt-6 p-5 sm:p-7">
            <summary id="files-title" class="flex min-h-8 items-center justify-between gap-3 text-lg font-bold text-slate-950">
                Archivos adjuntos <span class="shrink-0 text-xs font-semibold text-slate-600">{{ $documentFiles->count() }} {{ $documentFiles->count() === 1 ? 'archivo' : 'archivos' }}</span>
            </summary>
    @endif
        <p class="mt-2 text-sm leading-6 text-slate-600">Revise el contenido antes de enviar o tomar una decisión. La visualización y descarga requieren autorización.</p>

        @forelse ($document->versions->sortByDesc('version_number') as $version)
            <div id="version-{{ $version->version_number }}" class="mt-5 scroll-mt-24 overflow-hidden rounded-xl border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-900">Versión {{ $version->version_number }}</h3>
                        @if ($version->version_number === $currentVersionNumber)
                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-800">Versión actual</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-600">Adjuntada por {{ $version->uploader->name }} · {{ $version->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <ul class="divide-y divide-slate-200">
                    @foreach ($version->files as $file)
                        @php
                            $displaySize = $file->size_bytes >= 1048576
                                ? number_format($file->size_bytes / 1048576, 2, ',', '.').' MB'
                                : number_format($file->size_bytes / 1024, 1, ',', '.').' KB';
                        @endphp
                        <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="break-all text-sm font-semibold text-slate-900">{{ $file->original_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ strtoupper($file->extension) }} · {{ $displaySize }} · {{ $file->created_at->format('d/m/Y H:i') }} · {{ $file->uploader->name }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @include('documents.partials.file-actions', ['file' => $file])
                                @if ($canManageFiles)
                                    <form method="POST" action="{{ route('documents.files.destroy', [$document, $file]) }}" data-confirm-title="Retirar archivo" data-confirm-message="El archivo se retirará del borrador.">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                                        <button type="submit" class="inline-flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50">Retirar</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">{{ $isDraft ? 'Este borrador todavía no tiene archivos adjuntos.' : 'Este documento no tiene archivos adjuntos.' }}</div>
        @endforelse

        @if ($canManageFiles)
            <form method="POST" action="{{ route('documents.files.store', $document) }}" enctype="multipart/form-data" class="mt-6 border-t border-slate-200 pt-6" data-submit-label="Subiendo archivos…" data-return-anchor="files-title">
                @csrf
                <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                <label for="document_files" class="block text-sm font-bold text-slate-800">Seleccionar archivos</label>
                <input
                    id="document_files"
                    type="file"
                    name="files[]"
                    multiple
                    required
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip"
                    class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:min-h-11 file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:font-bold file:text-slate-700 hover:file:bg-slate-200"
                    aria-invalid="{{ $errors->has('files') || $errors->has('files.*') ? 'true' : 'false' }}"
                    aria-describedby="document-files-help{{ $errors->has('files') || $errors->has('files.*') ? ' document-files-error' : '' }}"
                >
                <p id="document-files-help" class="mt-2 text-xs leading-5 text-slate-600">
                    Máximo {{ config('documents.uploads.max_files_per_request') }} archivos por carga y {{ config('documents.uploads.max_size_kb') / 1024 }} MB por archivo. Formatos: PDF, Word, Excel, PowerPoint, JPG, PNG y ZIP.
                    Si la carga falla, seleccione los archivos nuevamente antes de reintentar.
                </p>
                @if ($errors->has('files') || $errors->has('files.*'))<p id="document-files-error" class="ft-field-error">{{ $errors->first('files') ?: $errors->first('files.*') }}</p>@endif
                <button type="submit" class="ft-button-primary mt-4">Subir archivos</button>
            </form>
        @endif
    @if ($isDraft)
        </section>
    @else
        </details>
    @endif

    @if ($canManageAccessGrants)
        @if ($isDraft)
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="access-grants-title">
                <h2 id="access-grants-title" class="text-lg font-bold text-slate-950">Accesos explícitos</h2>
        @else
            <details class="ft-card mt-6 p-5 sm:p-7">
                <summary id="access-grants-title" class="flex min-h-8 items-center justify-between gap-3 text-lg font-bold text-slate-950">
                    Administrar accesos <span class="shrink-0 text-xs font-semibold text-slate-600">{{ $activeAccessGrants->count() }} {{ $activeAccessGrants->count() === 1 ? 'acceso activo' : 'accesos activos' }}</span>
                </summary>
        @endif
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Permiten consultar este documento sin convertir al usuario en destinatario ni participante del flujo.</p>

            @if ($document->confidentiality === App\Enums\DocumentConfidentiality::RESTRICTED)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                    Este documento es restringido. Conceda acceso únicamente cuando exista una necesidad institucional expresa.
                </div>
            @endif

            <div class="mt-5 grid gap-4">
                @forelse ($activeAccessGrants as $grant)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $grant->user->name }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $grant->user->roleName()?->label() }} · {{ $grant->user->organizationalUnit?->name ?? 'Sin unidad' }}</p>
                                <dl class="mt-3 grid gap-x-6 gap-y-2 text-xs text-slate-600 sm:grid-cols-2">
                                    <div><dt class="font-bold text-slate-700">Nivel</dt><dd>{{ $grant->permission->label() }}</dd></div>
                                    <div><dt class="font-bold text-slate-700">Concedido por</dt><dd>{{ $grant->grantor->name }}</dd></div>
                                    <div><dt class="font-bold text-slate-700">Fecha</dt><dd>{{ $grant->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
                                    <div><dt class="font-bold text-slate-700">Vencimiento</dt><dd>{{ $grant->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin vencimiento' }}</dd></div>
                                </dl>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:w-[34rem]">
                                <form method="POST" action="{{ route('documents.access-grants.update', [$document, $grant]) }}" class="rounded-lg border border-slate-200 p-3">
                                    @csrf
                                    @method('PATCH')
                                    <label for="permission_{{ $grant->id }}" class="block text-xs font-bold text-slate-700">Cambiar nivel</label>
                                    <div class="mt-2 flex gap-2">
                                        <select id="permission_{{ $grant->id }}" name="permission" class="min-h-10 min-w-0 flex-1 rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                            @foreach ($accessPermissions as $permission)
                                                <option value="{{ $permission->value }}" @selected($grant->permission === $permission)>{{ $permission->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Aplicar</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('documents.access-grants.destroy', [$document, $grant]) }}" class="rounded-lg border border-red-200 bg-red-50 p-3" data-confirm-title="Revocar acceso" data-confirm-message="La persona dejará de tener acceso al documento.">
                                    @csrf
                                    @method('DELETE')
                                    <label for="revocation_reason_{{ $grant->id }}" class="block text-xs font-bold text-red-900">Motivo de revocación</label>
                                    <textarea id="revocation_reason_{{ $grant->id }}" name="revocation_reason" rows="2" required maxlength="1000" class="mt-2 block w-full rounded-lg border-red-300 text-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                    <button type="submit" class="mt-2 inline-flex min-h-10 items-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800">Revocar acceso</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center text-sm text-slate-600">No existen accesos explícitos activos para este documento.</div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('documents.access-grants.store', $document) }}" class="mt-6 border-t border-slate-200 pt-6">
                @csrf
                <h3 class="font-bold text-slate-950">Conceder acceso</h3>
                <div class="mt-4 grid gap-4 lg:grid-cols-3 lg:items-end">
                    <div>
                        <label for="access_user_id" class="block text-sm font-bold text-slate-800">Usuario Extra</label>
                        <select id="access_user_id" name="user_id" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Seleccione una persona</option>
                            @foreach ($eligibleAccessUsers as $eligibleUser)
                                <option value="{{ $eligibleUser->id }}" @selected(old('user_id') == $eligibleUser->id)>{{ $eligibleUser->name }} — Extra · {{ $eligibleUser->organizationalUnit?->name ?? 'Sin unidad' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="access_permission" class="block text-sm font-bold text-slate-800">Nivel de acceso</label>
                        <select id="access_permission" name="permission" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            @foreach ($accessPermissions as $permission)
                                <option value="{{ $permission->value }}" @selected(old('permission', 'view') === $permission->value)>{{ $permission->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="access_ends_at" class="block text-sm font-bold text-slate-800">Vencimiento opcional</label>
                        <input id="access_ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-600">“Ver” no permite descargar. “Ver y descargar” habilita ambos accesos. La vigencia se evalúa automáticamente en America/La_Paz.</p>
                <button type="submit" @disabled($eligibleAccessUsers->isEmpty()) class="ft-button-primary mt-4">Conceder acceso</button>
                @if ($eligibleAccessUsers->isEmpty())
                    <p class="mt-2 text-xs font-semibold text-slate-600">No hay usuarios Extra activos y disponibles dentro de su ámbito.</p>
                @endif
            </form>
        @if ($isDraft)
            </section>
        @else
            </details>
        @endif
    @endif

    @if ($isDraft && $document->authorization_status !== App\Enums\OutgoingAuthorizationStatus::NOT_REQUIRED)
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-7" aria-labelledby="authorization-title">
            <h2 id="authorization-title" class="text-lg font-bold text-amber-950">Autorización de salida</h2>
            <p class="mt-2 text-sm text-amber-900">Estado: <strong>{{ $document->authorization_status->label() }}</strong></p>

            @if ($document->authorization_note)
                <div class="mt-4 rounded-xl bg-white p-4 text-sm text-slate-800">
                    <p class="font-bold">Observación de la autoridad</p>
                    <p class="mt-1 whitespace-pre-line">{{ $document->authorization_note }}</p>
                </div>
            @endif

            @can('requestAuthorization', $document)
                <form method="POST" action="{{ route('documents.authorization.request', $document) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                    <button type="submit" @disabled($document->recipients->isEmpty()) class="ft-button-primary">Solicitar autorización</button>
                    @if ($document->recipients->isEmpty())
                        <p class="mt-2 text-xs font-semibold text-red-700">Agregue al menos un destinatario antes de solicitar autorización.</p>
                    @endif
                </form>
            @endcan

            @can('decideAuthorization', $document)
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('documents.authorization.decide', [$document, 'approve']) }}" class="rounded-xl bg-white p-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                        <label for="approval_note" class="block text-sm font-bold text-slate-800">Observación opcional</label>
                        <textarea id="approval_note" name="authorization_note" rows="3" maxlength="2000" class="mt-2 block w-full rounded-lg border-slate-300 text-sm"></textarea>
                        <button type="submit" class="ft-button-primary mt-3">Autorizar envío</button>
                    </form>
                    <form method="POST" action="{{ route('documents.authorization.decide', [$document, 'return']) }}" class="rounded-xl bg-white p-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                        <label for="return_note" class="block text-sm font-bold text-slate-800">Corrección requerida</label>
                        <textarea id="return_note" name="authorization_note" rows="3" required maxlength="2000" class="mt-2 block w-full rounded-lg border-slate-300 text-sm"></textarea>
                        <button type="submit" class="ft-button-secondary mt-3">Devolver a Secretaría</button>
                    </form>
                </div>
            @endcan

            @if ($document->authorization_status === App\Enums\OutgoingAuthorizationStatus::PENDING)
                <p class="mt-4 text-sm text-amber-900">La Secretaria no puede modificar ni enviar el borrador mientras espera la decisión.</p>
            @elseif ($document->authorization_status === App\Enums\OutgoingAuthorizationStatus::APPROVED)
                <p class="mt-4 text-sm text-emerald-800">Autorizado por {{ $document->authorizer?->name ?? 'la autoridad' }}. Ya puede realizarse el envío oficial.</p>
            @endif
        </section>
    @endif

    @can('send', $document)
        <section class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-7" aria-labelledby="send-title">
            <h2 id="send-title" class="text-lg font-bold text-blue-950">Listo para enviar</h2>
            <p class="mt-2 text-sm leading-6 text-blue-900">Revise el resumen antes de enviar. Los destinatarios y archivos quedarán inmutables en esta entrega.</p>
            <dl data-recipient-send-summary class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="font-bold text-blue-950">Origen</dt><dd class="text-blue-900">{{ $document->originUnit->name }}</dd></div>
                @if($document->documentType->uses_correlative)<div><dt class="font-bold text-blue-950">Cite reservado</dt><dd class="text-blue-900">{{ $document->code ?? 'Pendiente de reserva' }}</dd></div>@endif
                <div><dt class="font-bold text-blue-950">Tipo</dt><dd class="text-blue-900">{{ $document->documentType->name }}</dd></div>
                <div><dt class="font-bold text-blue-950">Asunto</dt><dd class="text-blue-900">{{ $document->subject }}</dd></div>
                <div><dt class="font-bold text-blue-950">Destinatarios principales</dt><dd class="text-blue-900">{{ $mainRecipients->map(fn ($recipient) => $recipient->user?->name ?? $recipient->unit->name)->join(', ') ?: 'Ninguno' }}</dd></div>
                <div><dt class="font-bold text-blue-950">Copias informativas</dt><dd class="text-blue-900">{{ $copyRecipients->map(fn ($recipient) => $recipient->user?->name ?? $recipient->unit->name)->join(', ') ?: 'Ninguna' }}</dd></div>
                <div><dt class="font-bold text-blue-950">Archivos</dt><dd class="text-blue-900">{{ $document->versions->sum(fn ($version) => $version->files->count()) }}</dd></div>
            </dl>
            <div class="mt-4 rounded-xl bg-white p-4">
                <h3 class="text-sm font-bold text-slate-950">Archivos que enviará</h3>
                <ul class="mt-3 space-y-3">
                    @forelse ($documentFiles as $file)
                        <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="min-w-0 break-words text-sm font-semibold text-slate-800">{{ $file->original_name }}</span>
                            <div class="flex shrink-0 flex-wrap gap-2">@include('documents.partials.file-actions', ['file' => $file])</div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Este documento se enviará sin archivos adjuntos.</li>
                    @endforelse
                </ul>
            </div>
            <div data-recipient-send-controls>
            <a href="{{ route('documents.edit', $document) }}" class="ft-button-secondary mt-4">Volver y corregir</a>
            <form method="POST" action="{{ route('documents.send', $document) }}" class="mt-5" data-confirm-title="Enviar documento" data-confirm-message="Se asignará un código oficial y ya no podrá modificar destinatarios ni archivos.">
                @csrf
                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                <button type="submit" @disabled($document->recipients->isEmpty()) class="ft-button-primary">Enviar documento</button>
            </form>
            @if ($document->recipients->isEmpty())
                <p class="mt-2 text-xs font-semibold text-red-700">Debe agregar al menos un destinatario antes de enviar.</p>
            @endif
            </div>
        </section>
    @endcan

    @can('annul', $document)
        <section class="mt-6 rounded-2xl border-2 border-red-300 bg-red-50 p-5 shadow-sm sm:p-7" aria-labelledby="annul-title">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-800">Acción administrativa superior</p>
            <h2 id="annul-title" class="mt-2 text-xl font-black text-red-950">Anular documento</h2>
            <p class="mt-2 text-sm leading-6 text-red-900">Esta acción dejará el documento sin efecto institucional. El expediente y su historial permanecerán conservados. No equivale a borrar, rechazar ni devolver y no podrá deshacerse.</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="font-bold text-red-950">Código oficial</dt><dd class="text-red-900">{{ $document->code }}</dd></div>
                <div><dt class="font-bold text-red-950">Estado actual</dt><dd class="text-red-900">{{ $document->general_status->label() }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('documents.annul', $document) }}" class="mt-5" data-confirm-title="Anular documento" data-confirm-message="Quedará sin efecto institucional. El expediente y su historial se conservarán.">
                @csrf
                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                <label for="annulment_reason" class="block text-sm font-bold text-red-950">Motivo obligatorio</label>
                <textarea id="annulment_reason" name="reason" rows="4" required maxlength="{{ max(1, min(10000, (int) config('documents.workflow.annulment_reason_max_length', 2000))) }}" class="mt-2 block w-full rounded-lg border-red-300 bg-white text-sm focus:border-red-700 focus:ring-red-700" aria-describedby="annulment-help">{{ old('reason') }}</textarea>
                <p id="annulment-help" class="mt-2 text-xs leading-5 text-red-800">Explique la causa administrativa. El motivo quedará registrado de forma inmutable en el historial y la auditoría.</p>
                <button type="submit" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-red-800 px-5 py-2.5 text-sm font-black text-white hover:bg-red-950">Anular documento</button>
            </form>
        </section>
    @endcan

    @if ($isDraft)
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="history-title">
            <h2 id="history-title" class="text-lg font-bold text-slate-950">Historial documental</h2>
    @else
        <details data-document-history @if (request()->filled('historial')) open @endif class="ft-card mt-6 p-5 sm:p-7">
            <summary id="history-title" class="flex min-h-8 items-center justify-between gap-3 text-lg font-bold text-slate-950">
                Historial documental <span class="shrink-0 text-xs font-semibold text-slate-600">{{ $history->total() }} {{ $history->total() === 1 ? 'evento' : 'eventos' }}</span>
            </summary>
            <p class="mt-2 text-sm text-slate-600">Abra esta sección para consultar la trazabilidad completa.</p>
    @endif

        <div class="mt-5">

        @forelse ($history as $event)
            <article class="relative ml-3 border-l-2 border-slate-200 pb-7 pl-7 last:pb-0">
                <span class="absolute -left-[9px] top-1.5 h-4 w-4 rounded-full border-4 border-white {{ $event->event_type->isOfficial() ? 'bg-institutional-700' : 'bg-slate-500' }}" aria-hidden="true"></span>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-bold text-slate-950">{{ $event->event_type->label() }}</h3>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $event->event_type->isOfficial() ? 'bg-blue-50 text-blue-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $event->event_type->isOfficial() ? 'Evento oficial' : 'Preparación' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm leading-6 text-slate-700">{{ $event->description() }}</p>

                        @if ($event->reason)
                            <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                <span class="font-bold">Motivo:</span> {{ $event->reason }}
                            </div>
                        @endif

                        @if (! empty($event->metadata['changed_fields']))
                            <p class="mt-2 text-xs text-slate-600">Campos actualizados: {{ implode(', ', $event->metadata['changed_fields']) }}.</p>
                        @endif
                        @if ($event->event_type === App\Enums\DocumentEventType::DOCUMENT_SENT)
                            <p class="mt-2 text-xs text-slate-600">Código {{ $event->metadata['code'] }} · {{ $event->metadata['recipient_count'] }} destinatarios · origen {{ $event->metadata['origin_unit'] }}.</p>
                        @endif
                        @if (in_array($event->event_type, [App\Enums\DocumentEventType::FILE_ADDED, App\Enums\DocumentEventType::FILE_REMOVED], true))
                            <p class="mt-2 text-xs text-slate-600">Versión {{ $event->metadata['version'] }}.</p>
                        @endif
                        @if ($event->event_type === App\Enums\DocumentEventType::DOCUMENT_VERSION_CREATED)
                            <p class="mt-2 text-xs text-slate-600">Versión {{ $event->metadata['version'] }} · {{ $event->metadata['file_count'] }} archivos.</p>
                        @endif
                        @if ($event->event_type === App\Enums\DocumentEventType::CORRECTION_SENT)
                            <p class="mt-2 text-xs text-slate-600">Versión {{ $event->metadata['version'] }} · {{ $event->metadata['recipient_count'] }} destinatarios reincorporados al flujo.</p>
                        @endif
                    </div>
                    <time datetime="{{ $event->occurred_at->toIso8601String() }}" class="shrink-0 text-xs font-semibold text-slate-500">
                        {{ $event->occurred_at->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}
                    </time>
                </div>
            </article>
        @empty
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Todavía no existen eventos documentales.</div>
        @endforelse

            @if ($history->hasPages())
                <div class="mt-6 border-t border-slate-200 pt-5">{{ $history->fragment('history-title')->links() }}</div>
            @endif
        </div>
    @if ($isDraft)
        </section>
    @else
        </details>
    @endif

    @can('discardDraft', $document)
        <section class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="discard-title">
            <h2 id="discard-title" class="font-bold text-red-950">Descartar borrador</h2>
            <p class="mt-2 text-sm leading-6 text-red-900">Esta acción retira el borrador de los listados normales, siempre que nunca haya sido enviado y no tenga información relacionada. Su trazabilidad se conserva.</p>
            <form method="POST" action="{{ route('documents.discard', $document) }}" class="mt-4" data-confirm-title="Descartar borrador" data-confirm-message="El borrador dejará de aparecer en los listados normales.">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800">Descartar borrador</button>
            </form>
        </section>
    @endcan
    @if ($canObtainFiles && $documentFiles->isNotEmpty())
        @include('documents.partials.file-viewer')
    @endif
</x-layouts.authenticated>
