<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->user()?->status === UserStatus::ACTIVE) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort_if($request->routeIs('documents.recipients.transition'), 403, 'Su cuenta no está activa.');

        return redirect()->route('login')->withErrors([
            'email' => 'Su cuenta no está activa. Solicite ayuda al responsable del sistema.',
        ]);
    }
}
