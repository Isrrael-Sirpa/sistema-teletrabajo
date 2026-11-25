@props(['name'])

<svg {{ $attributes->class(['h-5 w-5 shrink-0'])->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('home')<path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z" />@break
        @case('documents')<path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm7 0v5h5M8 12h8M8 16h8" />@break
        @case('plus')<path stroke-linecap="round" d="M12 5v14M5 12h14" />@break
        @case('bell')<path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" />@break
        @case('users')<path stroke-linecap="round" stroke-linejoin="round" d="M16 20H3v-2a5 5 0 0 1 10 0v2Zm-8-8a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm9-2a3 3 0 1 0 0-6m-1 9a4 4 0 0 1 5 4v3h-4" />@break
        @case('building')<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 10h1m4 0h1m-6 4h1m4 0h1m-5 7v-4h4v4" />@break
        @case('list')<path stroke-linecap="round" d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01" />@break
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2" /><path stroke-linecap="round" d="M7 3v4m10-4v4M3 10h18" />@break
        @case('chart')<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m5 10V4m5 16v-7m5 7V8M2 20h20" />@break
        @case('shield')<path stroke-linecap="round" stroke-linejoin="round" d="m12 2 8 4v6c0 5-3 8-8 10-5-2-8-5-8-10V6l8-4Zm-3 10 2 2 4-4" />@break
        @case('menu')<path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />@break
        @case('close')<path stroke-linecap="round" d="M5 5 19 19M19 5 5 19" />@break
        @case('arrow')<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />@break
        @case('eye')<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" />@break
        @case('eye-off')<path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10.7 10.7 0 0 1 12 5c6 0 9.5 7 9.5 7a18.3 18.3 0 0 1-3.1 4.1M6.1 6.1A18.4 18.4 0 0 0 2.5 12S6 19 12 19c1.2 0 2.4-.3 3.4-.8" /><path stroke-linecap="round" stroke-linejoin="round" d="M14 9.2A3 3 0 0 0 9.2 14" />@break
        @default<circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
