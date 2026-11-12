@php
    $recipientStatus = $recipient->status;
    $updatedAt = $recipient->decided_at ?? $recipient->received_at ?? $recipient->updated_at ?? $document->sent_at;
    $progressMessage = match ($recipientStatus) {
        App\Enums\DocumentRecipientStatus::SENT => $recipient->requires_response ? 'Esperando recepción.' : 'Copia enviada para conocimiento. No requiere respuesta.',
        App\Enums\DocumentRecipientStatus::RECEIVED => $recipient->requires_response ? 'Recibido; pendiente de revisión.' : 'Copia informativa recibida.',
        App\Enums\DocumentRecipientStatus::IN_REVIEW => 'El documento está siendo evaluado.',
        App\Enums\DocumentRecipientStatus::APPROVED => 'Proceso finalizado para este destinatario.',
        App\Enums\DocumentRecipientStatus::RETURNED => 'Se solicitó una corrección.',
        App\Enums\DocumentRecipientStatus::REJECTED => 'El documento fue rechazado.',
        default => 'Pendiente de envío.',
    };
@endphp

<article @unless ($summaryAction && $ownRecipient?->id === $recipient->id) id="destino-{{ $recipient->id }}" tabindex="-1" @endunless class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <p class="text-xs font-bold uppercase tracking-wide {{ $recipient->requires_response ? 'text-blue-800' : 'text-slate-600' }}">{{ $recipient->requires_response ? 'Destinatario principal' : 'Copia informativa' }}</p>
    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="font-bold text-slate-950">{{ $recipient->user?->name ?? $recipient->unit->name }}</h3>
            @if ($recipient->user)
                <p class="mt-0.5 text-xs text-slate-600">{{ $recipient->unit->name }}</p>
            @endif
        </div>
        <x-ui.status-badge :status="$recipientStatus" />
    </div>
    <p class="mt-2 text-sm text-slate-700">{{ $progressMessage }}</p>
    @if ($updatedAt)
        <p class="mt-2 text-xs text-slate-500">Última actualización: <time datetime="{{ $updatedAt->toIso8601String() }}">{{ $updatedAt->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</time></p>
    @endif
    @if ($recipient->decision_reason && in_array($recipientStatus, [App\Enums\DocumentRecipientStatus::RETURNED, App\Enums\DocumentRecipientStatus::REJECTED], true))
        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-950"><span class="font-bold">Motivo:</span> {{ $recipient->decision_reason }}</p>
    @endif

    @unless ($isSenderContext)
        <div class="mt-4 flex flex-wrap items-start gap-2">
            @if (! ($summaryAction && $ownRecipient?->id === $recipient->id))
            @can('receiveRecipient', [$document, $recipient])
                <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'receive']) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                    <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                    <button type="submit" class="{{ $recipient->requires_response ? 'ft-button-primary' : 'ft-button-secondary' }}">{{ $recipient->requires_response ? 'Recibir documento' : 'Registrar recepción de copia' }}</button>
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
                <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'approve']) }}" onsubmit="return confirm('¿Desea aprobar este documento para el destinatario indicado? La decisión quedará registrada en el historial.');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                    <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                    <button type="submit" class="ft-button-primary">Aprobar documento</button>
                </form>
            @endcan
            @endif
            @if ($recipientStatus === App\Enums\DocumentRecipientStatus::IN_REVIEW)
                @canany(['returnRecipient', 'rejectRecipient'], [$document, $recipient])
                    <details class="min-w-[12rem] rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <summary class="min-h-7 font-semibold text-slate-700">Otras decisiones</summary>
                        <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3">
                            @can('returnRecipient', [$document, $recipient])
                                <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'return']) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                    <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                    <label for="return_reason_{{ $recipient->id }}" class="block text-xs font-bold text-amber-950">Motivo de devolución</label>
                                    <textarea id="return_reason_{{ $recipient->id }}" name="reason" required maxlength="{{ config('documents.workflow.reason_max_length') }}" rows="2" class="mt-2 block w-full"></textarea>
                                    <button type="submit" class="ft-button-secondary mt-2">Devolver documento</button>
                                </form>
                            @endcan
                            @can('rejectRecipient', [$document, $recipient])
                                <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'reject']) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                    <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                    <label for="reject_reason_{{ $recipient->id }}" class="block text-xs font-bold text-red-950">Motivo de rechazo</label>
                                    <textarea id="reject_reason_{{ $recipient->id }}" name="reason" required maxlength="{{ config('documents.workflow.reason_max_length') }}" rows="2" class="mt-2 block w-full"></textarea>
                                    <button type="submit" class="ft-button-secondary mt-2">Rechazar documento</button>
                                </form>
                            @endcan
                        </div>
                    </details>
                @endcanany
            @endif
        </div>
    @endunless
</article>
