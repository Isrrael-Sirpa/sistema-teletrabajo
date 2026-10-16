@props(['status'])

@php
    $steps = [
        App\Enums\DocumentStatus::DRAFT,
        App\Enums\DocumentStatus::SENT,
        App\Enums\DocumentStatus::RECEIVED,
        App\Enums\DocumentStatus::IN_REVIEW,
        App\Enums\DocumentStatus::APPROVED,
    ];
    $current = array_search($status, $steps, true);
    $branch = in_array($status, [App\Enums\DocumentStatus::RETURNED, App\Enums\DocumentStatus::REJECTED, App\Enums\DocumentStatus::ANNULLED], true);
@endphp

<section class="ft-card mt-6 p-5 sm:p-6" aria-labelledby="document-progress-title">
    <h2 id="document-progress-title" class="ft-section-heading">Recorrido del documento</h2>
    <ol class="mt-4 grid gap-3 sm:grid-cols-5" aria-label="Etapas del documento">
        @foreach ($steps as $index => $step)
            <li class="ft-step" data-state="{{ $branch ? 'upcoming' : ($index === $current ? 'current' : ($index < $current ? 'done' : 'upcoming')) }}" @if(! $branch && $index === $current) aria-current="step" @endif>
                <span class="ft-step-number">{{ $index + 1 }}</span>
                <span>{{ $step->label() }}</span>
            </li>
        @endforeach
    </ol>
    @if ($branch)
        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950">
            Estado actual: {{ $status === App\Enums\DocumentStatus::RETURNED ? 'Devuelto para corrección' : $status->label() }}.
        </p>
    @endif
</section>
