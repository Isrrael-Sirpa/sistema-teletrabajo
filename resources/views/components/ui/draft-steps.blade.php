@props(['active' => 1])

<ol class="ft-card mt-6 grid gap-4 p-4 sm:grid-cols-4 sm:gap-2 sm:p-5" aria-label="Preparación del documento">
    @foreach (['Datos del documento', 'Destinatarios', 'Archivos', 'Enviar'] as $index => $label)
        <li class="ft-step" data-state="{{ $index + 1 === $active ? 'current' : ($index + 1 < $active ? 'done' : 'upcoming') }}" @if($index + 1 === $active) aria-current="step" @endif>
            <span class="ft-step-number">{{ $index + 1 }}</span><span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
