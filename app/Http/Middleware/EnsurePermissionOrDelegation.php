<?php

namespace App\Http\Middleware;

use App\Services\DelegatedAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionOrDelegation
{
    public function __construct(private readonly DelegatedAuthorizationService $delegations) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user && ($user->can($permission) || $this->delegations->hasAny($user, $permission)), 403);

        return $next($request);
    }
}
