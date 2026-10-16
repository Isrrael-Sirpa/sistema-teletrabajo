@props(['href', 'icon', 'active' => false])

<a href="{{ $href }}" class="ft-nav-link" @if($active) aria-current="page" @endif>
    <x-ui.icon :name="$icon" class="ft-nav-icon" />
    <span>{{ $slot }}</span>
</a>
