<x-layouts.app :title="'Iniciar sesión — '.config('app.name')">
    <main id="contenido-principal" class="grid min-h-screen bg-[var(--ft-background)] lg:grid-cols-[minmax(22rem,44%)_1fr]">
        <section class="relative flex flex-col justify-between overflow-hidden bg-[var(--ft-primary)] px-6 py-6 text-white sm:px-10 sm:py-8 lg:min-h-screen lg:px-14 lg:py-12" aria-label="Identidad institucional">
            <div class="relative z-10">
                <x-ui.brand-logos />
                <p class="mt-4 text-[.7rem] font-bold uppercase tracking-[.16em] text-blue-200 sm:mt-7 sm:text-xs sm:tracking-[.2em]">Universidad Mayor de San Andrés</p>
                <p class="mt-1 text-xl font-bold tracking-tight sm:mt-2 sm:text-3xl">Facultad de Tecnología</p>
                <div class="mt-4 h-1 w-12 rounded-full bg-[var(--ft-light-blue)] sm:mt-7" aria-hidden="true"></div>
                <h1 class="mt-4 max-w-lg text-2xl font-bold leading-tight tracking-tight sm:mt-7 sm:text-4xl">Sistema de Gestión Documental</h1>
                <p class="mt-4 hidden max-w-md text-sm leading-7 text-blue-100 sm:block sm:text-base">Un espacio claro y seguro para gestionar el trabajo documental de nuestra Facultad.</p>
            </div>
            <p class="relative z-10 mt-12 hidden max-w-md text-sm leading-6 text-blue-200 lg:block">Ciencia, cálculo y jerarquía — Adelante Tecnología</p>
            <div class="pointer-events-none absolute -bottom-28 -right-28 h-80 w-80 rounded-full border-[44px] border-white/5" aria-hidden="true"></div>
        </section>

        <section class="flex items-center justify-center px-4 py-6 sm:px-8 sm:py-10 lg:py-16" aria-labelledby="login-title">
            <div class="w-full max-w-md rounded-2xl border border-[var(--ft-border)] bg-white p-6 shadow-sm sm:p-10">
                <p class="ft-section-heading">Acceso al sistema</p>
                <h2 id="login-title" class="mt-2 text-2xl font-bold tracking-tight text-[var(--ft-primary)] sm:text-3xl">Iniciar sesión</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">Ingrese con el correo y la contraseña de su cuenta institucional.</p>

                @if (session('status'))
                    <div role="status" class="ft-alert ft-alert-success mt-6">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div role="alert" data-form-errors tabindex="-1" class="ft-alert ft-alert-error mt-6">
                        <p class="font-bold">No fue posible iniciar sesión.</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5" data-submit-label="Ingresando…">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-bold text-slate-800">Correo electrónico</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required @if (! $errors->any()) autofocus @endif autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-help" class="mt-2 block w-full text-base" placeholder="nombre@institucion.edu.bo">
                        <p id="email-help" class="mt-1.5 text-xs leading-5 text-slate-500">Use el correo asignado por la institución.</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-3"><label for="password" class="block text-sm font-bold text-slate-800">Contraseña</label><button type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" aria-pressed="false" hidden class="min-h-11 rounded px-2 text-sm font-bold text-[var(--ft-secondary)] underline underline-offset-4">Mostrar</button></div>
                        <input id="password" name="password" type="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="mt-2 block w-full text-base">
                    </div>
                    <button type="submit" class="ft-button-primary w-full text-base">Iniciar sesión <x-ui.icon name="arrow" class="h-4 w-4" /></button>
                </form>

                <p class="mt-7 border-t border-[var(--ft-border)] pt-5 text-sm leading-6 text-slate-600">¿Tiene dificultades para ingresar? Comuníquese con el responsable del sistema de su unidad.</p>
            </div>
        </section>
    </main>
</x-layouts.app>
