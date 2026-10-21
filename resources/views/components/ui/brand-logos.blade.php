@props(['compact' => false])

<span {{ $attributes->class(['ft-brand-logos', 'ft-brand-logos-compact' => $compact]) }}>
    @unless($compact)
        <img src="{{ asset('images/brand/umsa-logo.png') }}" alt="Escudo de la Universidad Mayor de San Andrés" width="60" height="60" class="ft-brand-logo-umsa" decoding="async">
    @endunless
    <img src="{{ asset('images/brand/ft-logo.png') }}" alt="Facultad de Tecnología UMSA" width="{{ $compact ? 44 : 64 }}" height="{{ $compact ? 44 : 64 }}" class="ft-brand-logo-ft" decoding="async">
</span>
