<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/brand/umsa-favicon.ico') }}">
    <title>@yield('status') · @yield('title') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; background: #f4f6f8; color: #1f2937; padding: 1rem; }
        main { width: min(100%, 42rem); overflow: hidden; background: #fff; border: 1px solid #d9dee5; border-radius: 1.25rem; box-shadow: 0 1rem 3rem rgba(11, 46, 89, .08); }
        header { padding: 1.5rem clamp(1.5rem, 5vw, 3rem); background: #0b2e59; color: #fff; }
        .institution { font-size: .875rem; font-weight: 700; line-height: 1.6; }
        .product { margin-top: .25rem; color: #dbeaf5; font-size: .8rem; }
        .content { padding: clamp(1.5rem, 5vw, 3rem); }
        .status { display: inline-block; border: 1px solid #dbeaf5; border-radius: .5rem; padding: .4rem .6rem; background: #eef5fa; color: #174a8b; font-size: .8rem; font-weight: 700; margin-bottom: 1.25rem; }
        h1 { font-size: clamp(1.5rem, 5vw, 2rem); line-height: 1.25; margin: 0; letter-spacing: -.025em; }
        p { color: #475569; line-height: 1.7; margin: 1rem 0 1.5rem; overflow-wrap: anywhere; }
        .guidance { border-left: 3px solid #2878b5; padding: .75rem 1rem; background: #f4f7fa; font-size: .875rem; }
        a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border-radius: .625rem; background: #0b2e59; color: #fff; font-size: .875rem; font-weight: 700; padding: .75rem 1.25rem; text-decoration: none; }
        a:hover { background: #174a8b; }
        a:focus-visible { outline: 3px solid #2878b5; outline-offset: 4px; }
        footer { border-top: 1px solid #e2e8f0; padding: 1rem clamp(1.5rem, 5vw, 3rem); color: #64748b; font-size: .75rem; line-height: 1.6; }
    </style>
</head>
<body>
    <main aria-labelledby="error-title">
        <header>
            <div class="institution">Facultad de Tecnología · UMSA</div>
            <div class="product">Sistema de Gestión Documental</div>
        </header>
        <div class="content">
        <div class="status">Código @yield('status')</div>
        <h1 id="error-title">@yield('title')</h1>
        <p>@yield('message')</p>
        @hasSection('guidance')<p class="guidance">@yield('guidance')</p>@endif
        <a href="{{ url('/') }}">Volver al inicio</a>
        </div>
        <footer>Si necesita ayuda, comunique a la administración el código de esta página y qué estaba intentando realizar. No comparta su contraseña.</footer>
    </main>
</body>
</html>
