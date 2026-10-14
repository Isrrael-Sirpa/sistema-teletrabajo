<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('status') · @yield('title') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; padding: 1.5rem; }
        main { width: min(100%, 42rem); background: #fff; border: 1px solid #cbd5e1; border-radius: 1rem; padding: clamp(1.5rem, 5vw, 3rem); box-shadow: 0 1rem 3rem rgba(15, 23, 42, .08); }
        .institution { color: #475569; font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .status { color: #991b1b; font-size: clamp(2.5rem, 10vw, 5rem); font-weight: 900; line-height: 1; margin: 1.25rem 0 .5rem; }
        h1 { font-size: clamp(1.5rem, 5vw, 2.25rem); margin: 0; }
        p { color: #475569; line-height: 1.7; margin: 1rem 0 1.5rem; }
        a { display: inline-block; border-radius: .6rem; background: #7f1d1d; color: #fff; font-weight: 700; padding: .75rem 1rem; text-decoration: none; }
        a:focus-visible { outline: 3px solid #f59e0b; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <div class="institution">Facultad de Tecnología · UMSA</div>
        <div class="status" aria-hidden="true">@yield('status')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">Volver al inicio</a>
    </main>
</body>
</html>
