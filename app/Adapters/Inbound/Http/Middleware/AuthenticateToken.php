<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Application\Ports\Outbound\TokenIssuer;
use App\Domain\Exception\DomainError;
use Closure;
use Illuminate\Http\Request;

/** CA-01.3: every endpoint except login and health needs a valid token (401 otherwise, DT-06). */
final class AuthenticateToken
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $header = (string) $request->header('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
        $claims = $token === '' ? null : $this->tokens->parse($token);

        if ($claims === null) {
            throw DomainError::invalidCredentials();
        }

        $request->attributes->set('auth_user_id', $claims['sub']);
        $request->attributes->set('auth_role', $claims['role']);

        return $next($request);
    }
}
