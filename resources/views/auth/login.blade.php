<x-layouts.app :title="'Iniciar sesión — '.config('app.name')">
    <main id="contenido-principal" class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 h-72 bg-institutional-900"></div>

        <section class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15" aria-labelledby="login-title">
            <header class="bg-institutional-900 px-6 pb-8 pt-7 text-center text-white sm:px-9">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/25" aria-hidden="true">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h9a2.25 2.25 0 0 1 2.25 2.25v12A2.25 2.25 0 0 1 16.5 20.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
                        <path stroke-linecap="round" d="M8.5 8.25h7M8.5 12h7M8.5 15.75h4" />
                    </svg>
                </div>
                <p class="mt-4 text-xs font-semibold uppercase tracking-[0.14em] text-blue-100">
                    {{ config('institution.university') }}
                </p>
                <p class="mt-1 text-sm text-blue-100">{{ config('institution.faculty') }}</p>
            </header>

            <div class="px-6 py-8 sm:px-9">
                <h1 id="login-title" class="text-2xl font-bold tracking-tight text-slate-950">Iniciar sesión</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Ingrese con su correo institucional para acceder al sistema.
                </p>

                @if (session('status'))
                    <div role="status" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" aria-live="polite" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        <p class="font-semibold">No fue posible iniciar sesión.</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-800">Correo electrónico</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            inputmode="email"
                            aria-describedby="email-help"
                            class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20"
                            placeholder="nombre@institucion.edu.bo"
                        >
                        <p id="email-help" class="mt-1.5 text-xs leading-5 text-slate-500">Use el correo asignado por la institución.</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-800">Contraseña</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20"
                        >
                    </div>

                    <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-institutional-700 px-4 py-3 text-base font-bold text-white shadow-sm transition hover:bg-institutional-900 focus-visible:outline-offset-2">
                        Ingresar al sistema
                    </button>
                </form>

                <p class="mt-6 border-t border-slate-200 pt-5 text-center text-xs leading-5 text-slate-500">
                    Si no puede ingresar, comuníquese con el responsable del sistema de su unidad.
                </p>
            </div>
        </section>
    </main>
</x-layouts.app>
