<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Domain\Exception\DomainError;
use Closure;
use Illuminate\Http\Request;

/** Authorization by role on each endpoint; a 403 never reveals whether the resource exists. */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! in_array($request->attributes->get('auth_role'), $roles, true)) {
            throw DomainError::forbiddenRole();
        }

        return $next($request);
    }
}
