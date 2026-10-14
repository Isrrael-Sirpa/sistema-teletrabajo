<x-layouts.authenticated :title="$document->subject">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">Documento</p>
            <h1 class="mt-1 max-w-4xl text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $document->subject }}</h1>
            <div class="mt-3 flex flex-wrap gap-2">
                <span @class([
                    'inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide',
                    'bg-red-700 text-white' => $document->general_status === App\Enums\DocumentStatus::ANNULLED,
                    'bg-amber-50 text-amber-800' => $document->general_status !== App\Enums\DocumentStatus::ANNULLED,
                ])>{{ $document->general_status->label() }}</span>
                @if ($document->confidentiality === App\Enums\DocumentConfidentiality::RESTRICTED)
                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-800">Acceso restringido</span>
                @endif
                @if ($deadlineStatus === App\Enums\DocumentDeadlineStatus::DUE_SOON)
                    <span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-900">PRÓXIMO A VENCER</span>
                @elseif ($deadlineStatus === App\Enums\DocumentDeadlineStatus::OVERDUE)
                    <span class="inline-flex rounded-full border border-red-300 bg-red-50 px-3 py-1 text-xs font-bold text-red-900">VENCIDO</span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $document)
                <a href="{{ route('documents.edit', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Editar borrador</a>
            @endcan
            <a href="{{ route('documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first() }}</div>
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

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="document-data-title">
        <h2 id="document-data-title" class="text-lg font-bold text-slate-950">Datos del documento</h2>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Código oficial</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $document->code ?? 'Se asignará al enviar' }}</dd></div>
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
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="recipients-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="recipients-title" class="text-lg font-bold text-slate-950">Destinatarios</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    @if ($document->general_status === App\Enums\DocumentStatus::DRAFT)
                        Estos destinatarios están preparados. El documento todavía no fue enviado.
                    @elseif ($document->general_status === App\Enums\DocumentStatus::ANNULLED)
                        Estados históricos conservados. La anulación no modifica las recepciones, revisiones ni decisiones individuales.
                    @else
                        Cada destinatario conserva su recepción, revisión y decisión de forma independiente.
                    @endif
                </p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ $document->recipients->count() }} {{ $document->recipients->count() === 1 ? 'destinatario' : 'destinatarios' }}</span>
        </div>

        @if ($document->recipients->isNotEmpty())
            <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <caption class="sr-only">Destinatarios preparados para este borrador.</caption>
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Destinatario</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Finalidad</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($document->recipients as $recipient)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $recipient->user?->name ?? $recipient->unit->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $recipient->user ? 'Persona específica · '.$recipient->unit->name : 'Carrera o unidad completa' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($canManageRecipients)
                                        @php
                                            $canRequirePersonalResponse = ! $recipient->user
                                                || $recipient->user->roleName()?->canBeFormalPersonalDocumentRecipient();
                                        @endphp
                                        <form method="POST" action="{{ route('documents.recipients.update', $document) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="recipient_id" value="{{ $recipient->id }}">
                                            <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                                            <label class="sr-only" for="requires_response_{{ $recipient->id }}">Finalidad para {{ $recipient->user?->name ?? $recipient->unit->name }}</label>
                                            <select id="requires_response_{{ $recipient->id }}" name="requires_response" class="min-h-10 rounded-lg border-slate-300 py-2 text-sm focus:border-blue-600 focus:ring-blue-600">
                                                <option value="1" @selected($recipient->requires_response) @disabled(! $canRequirePersonalResponse)>Requiere respuesta</option>
                                                <option value="0" @selected(! $recipient->requires_response)>Copia informativa</option>
                                            </select>
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Actualizar</button>
                                        </form>
                                    @else
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                            'bg-emerald-50 text-emerald-800' => $recipient->requires_response,
                                            'bg-slate-100 text-slate-700' => ! $recipient->requires_response,
                                        ])>{{ $recipient->requires_response ? 'Requiere respuesta' : 'Copia informativa' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
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
                                <td class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                    @if ($canManageRecipients)
                                        <form method="POST" action="{{ route('documents.recipients.destroy', $document) }}" onsubmit="return confirm('¿Quitar este destinatario del borrador?');">
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
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg bg-institutional-700 px-3 py-2 text-sm font-bold text-white hover:bg-institutional-900">Recibir documento</button>
                                        </form>
                                    @endcan

                                    @can('reviewRecipient', [$document, $recipient])
                                        <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'start_review']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                            <input type="hidden" name="recipient_lock_version" value="{{ $recipient->lock_version }}">
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg bg-institutional-700 px-3 py-2 text-sm font-bold text-white hover:bg-institutional-900">Iniciar revisión</button>
                                        </form>
                                    @endcan

                                    @can('approveRecipient', [$document, $recipient])
                                        <form method="POST" action="{{ route('documents.recipients.transition', [$document, $recipient, 'approve']) }}" onsubmit="return confirm('¿Está seguro de aprobar este documento?');">
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Todavía no se agregaron destinatarios.</div>
        @endif

        @if ($canManageRecipients)
            <div class="mt-6 grid gap-5 border-t border-slate-200 pt-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('documents.recipients.store', $document) }}" class="rounded-xl border border-slate-200 p-4">
                    @csrf
                    <input type="hidden" name="recipient_type" value="unit">
                    <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                    <h3 class="font-bold text-slate-900">Enviar a una carrera o unidad</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600">Puede elegir una carrera activa o Kardex. La Facultad no se ofrece como destino ordinario.</p>

                    <label for="recipient_unit_id" class="mt-4 block text-sm font-bold text-slate-800">Carrera o unidad</label>
                    <select id="recipient_unit_id" name="unit_id" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="">Seleccione una opción</option>
                        @foreach ($recipientUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->type->label() }})</option>
                        @endforeach
                    </select>

                    <label for="unit_requires_response" class="mt-4 block text-sm font-bold text-slate-800">Finalidad</label>
                    <select id="unit_requires_response" name="requires_response" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="1">Requiere respuesta</option>
                        <option value="0">Copia informativa</option>
                    </select>

                    <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Agregar unidad</button>
                </form>

                <form method="POST" action="{{ route('documents.recipients.store', $document) }}" class="rounded-xl border border-slate-200 p-4">
                    @csrf
                    <input type="hidden" name="recipient_type" value="person">
                    <input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
                    <h3 class="font-bold text-slate-900">Enviar a una persona específica</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600">Jefatura y Secretaría pueden recibir trámites formales. Auxiliares solo pueden agregarse como copia informativa. Los usuarios Extra no se muestran.</p>

                    <label for="recipient_user_id" class="mt-4 block text-sm font-bold text-slate-800">Persona</label>
                    <select id="recipient_user_id" name="user_id" required data-personal-recipient-select class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="">Seleccione una opción</option>
                        @foreach ($recipientUsers as $user)
                            <option
                                value="{{ $user->id }}"
                                data-can-require-response="{{ $user->roleName()?->canBeFormalPersonalDocumentRecipient() ? '1' : '0' }}"
                            >{{ $user->name }} — {{ $user->roleName()?->label() }} · {{ $user->organizationalUnit->name }}</option>
                        @endforeach
                    </select>

                    <label for="person_requires_response" class="mt-4 block text-sm font-bold text-slate-800">Finalidad</label>
                    <select id="person_requires_response" name="requires_response" required data-personal-recipient-purpose class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="1">Requiere respuesta</option>
                        <option value="0">Copia informativa</option>
                    </select>
                    <p data-personal-recipient-help hidden class="mt-2 text-xs font-semibold text-amber-700">El Auxiliar solo puede seleccionarse como copia informativa.</p>

                    <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Agregar persona</button>
                </form>
            </div>
        @endif
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
                            <form method="POST" action="{{ route('documents.correction.send', $document) }}" class="mt-4" onsubmit="return confirm('¿Confirma el envío de esta corrección? Los destinatarios devueltos volverán al estado Enviado.');">
                                @csrf
                                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Enviar corrección</button>
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

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="files-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="files-title" class="text-lg font-bold text-slate-950">Archivos adjuntos</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Los archivos se conservan en almacenamiento privado y solo se descargan con autorización.</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">
                {{ $document->versions->sum(fn ($version) => $version->files->count()) }} archivos
            </span>
        </div>

        @forelse ($document->versions->sortByDesc('version_number') as $version)
            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
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
                                @can('downloadFile', $document)
                                    <a href="{{ route('documents.files.download', [$document, $file]) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Descargar</a>
                                @endcan
                                @if ($canManageFiles)
                                    <form method="POST" action="{{ route('documents.files.destroy', [$document, $file]) }}" onsubmit="return confirm('¿Retirar este archivo del borrador?');">
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
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Este borrador todavía no tiene archivos adjuntos.</div>
        @endforelse

        @if ($canManageFiles)
            <form method="POST" action="{{ route('documents.files.store', $document) }}" enctype="multipart/form-data" class="mt-6 border-t border-slate-200 pt-6">
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
                    aria-describedby="document-files-help"
                >
                <p id="document-files-help" class="mt-2 text-xs leading-5 text-slate-600">
                    Máximo {{ config('documents.uploads.max_files_per_request') }} archivos por carga y {{ config('documents.uploads.max_size_kb') / 1024 }} MB por archivo. Formatos: PDF, Word, Excel, PowerPoint, JPG, PNG y ZIP.
                </p>
                <button type="submit" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Adjuntar archivos</button>
            </form>
        @endif
    </section>

    @if ($canManageAccessGrants)
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="access-grants-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="access-grants-title" class="text-lg font-bold text-slate-950">Accesos explícitos</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Permiten consultar este documento sin convertir al usuario en destinatario ni participante del flujo.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ $activeAccessGrants->count() }} {{ $activeAccessGrants->count() === 1 ? 'acceso activo' : 'accesos activos' }}</span>
            </div>

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

                                <form method="POST" action="{{ route('documents.access-grants.destroy', [$document, $grant]) }}" class="rounded-lg border border-red-200 bg-red-50 p-3" onsubmit="return confirm('¿Confirma que desea revocar este acceso?');">
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
                <button type="submit" @disabled($eligibleAccessUsers->isEmpty()) class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900 disabled:cursor-not-allowed disabled:opacity-50">Conceder acceso</button>
                @if ($eligibleAccessUsers->isEmpty())
                    <p class="mt-2 text-xs font-semibold text-slate-600">No hay usuarios Extra activos y disponibles dentro de su ámbito.</p>
                @endif
            </form>
        </section>
    @endif

    @can('send', $document)
        <section class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-7" aria-labelledby="send-title">
            <h2 id="send-title" class="text-lg font-bold text-blue-950">Enviar documento oficialmente</h2>
            <p class="mt-2 text-sm leading-6 text-blue-900">Revise el resumen antes de enviar. Esta acción asignará el código institucional y volverá inmutables los destinatarios y archivos de esta entrega.</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="font-bold text-blue-950">Origen</dt><dd class="text-blue-900">{{ $document->originUnit->name }}</dd></div>
                <div><dt class="font-bold text-blue-950">Tipo</dt><dd class="text-blue-900">{{ $document->documentType->name }}</dd></div>
                <div><dt class="font-bold text-blue-950">Asunto</dt><dd class="text-blue-900">{{ $document->subject }}</dd></div>
                <div><dt class="font-bold text-blue-950">Destinatarios</dt><dd class="text-blue-900">{{ $document->recipients->count() }}</dd></div>
                <div><dt class="font-bold text-blue-950">Archivos</dt><dd class="text-blue-900">{{ $document->versions->sum(fn ($version) => $version->files->count()) }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('documents.send', $document) }}" class="mt-5" onsubmit="return confirm('¿Confirma el envío oficial de este documento? Se asignará un código y ya no podrá modificar destinatarios ni archivos.');">
                @csrf
                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                <button type="submit" @disabled($document->recipients->isEmpty()) class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900 disabled:cursor-not-allowed disabled:opacity-50">Enviar documento</button>
            </form>
            @if ($document->recipients->isEmpty())
                <p class="mt-2 text-xs font-semibold text-red-700">Debe agregar al menos un destinatario antes de enviar.</p>
            @endif
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
            <form method="POST" action="{{ route('documents.annul', $document) }}" class="mt-5" onsubmit="return confirm('¿Confirma la anulación definitiva? El documento quedará sin efecto institucional y esta acción no se puede deshacer.');">
                @csrf
                <input type="hidden" name="document_lock_version" value="{{ $document->lock_version }}">
                <label for="annulment_reason" class="block text-sm font-bold text-red-950">Motivo obligatorio</label>
                <textarea id="annulment_reason" name="reason" rows="4" required maxlength="{{ max(1, min(10000, (int) config('documents.workflow.annulment_reason_max_length', 2000))) }}" class="mt-2 block w-full rounded-lg border-red-300 bg-white text-sm focus:border-red-700 focus:ring-red-700" aria-describedby="annulment-help">{{ old('reason') }}</textarea>
                <p id="annulment-help" class="mt-2 text-xs leading-5 text-red-800">Explique la causa administrativa. El motivo quedará registrado de forma inmutable en el historial y la auditoría.</p>
                <button type="submit" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-red-800 px-5 py-2.5 text-sm font-black text-white hover:bg-red-950">Anular documento</button>
            </form>
        </section>
    @endcan

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="history-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="history-title" class="text-lg font-bold text-slate-950">Historial documental</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Línea de tiempo inmutable, ordenada desde el evento más reciente.</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ $history->total() }} {{ $history->total() === 1 ? 'evento' : 'eventos' }}</span>
        </div>

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
            <div class="mt-6 border-t border-slate-200 pt-5">{{ $history->links() }}</div>
        @endif
    </section>

    @can('discardDraft', $document)
        <section class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="discard-title">
            <h2 id="discard-title" class="font-bold text-red-950">Descartar borrador</h2>
            <p class="mt-2 text-sm leading-6 text-red-900">Esta acción retira el borrador de los listados normales, siempre que nunca haya sido enviado y no tenga información relacionada. Su trazabilidad se conserva.</p>
            <form method="POST" action="{{ route('documents.discard', $document) }}" class="mt-4" onsubmit="return confirm('¿Confirma que desea descartar este borrador? Esta acción no se puede deshacer.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800">Descartar borrador</button>
            </form>
        </section>
    @endcan
</x-layouts.authenticated>
