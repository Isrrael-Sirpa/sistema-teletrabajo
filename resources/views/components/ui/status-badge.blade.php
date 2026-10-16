@props(['status'])

<span {{ $attributes->class(['ft-badge'])->merge(['data-status' => $status->value]) }}>{{ $status->label() }}</span>
