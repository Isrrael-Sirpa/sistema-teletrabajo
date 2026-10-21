<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Sistema institucional de gestión documental de la Facultad de Tecnología.">
        <link rel="icon" type="image/x-icon" href="{{ asset('images/brand/umsa-favicon.ico') }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <a href="#contenido-principal" class="ft-skip-link">
            Ir al contenido principal
        </a>

        {{ $slot }}
        <p class="sr-only" role="status" aria-live="polite" data-interaction-status></p>

        @livewireScripts
    </body>
</html>
