<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\TokenIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/** HS256 token with only `sub` (user id) and `role`; no personal data inside (api-contract.md section 4, DT-15). */
final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
        private readonly Clock $clock,
    ) {
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function issue(string $userId, string $role): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode(
            ['sub' => $userId, 'role' => $role, 'iat' => $now, 'exp' => $now + $this->ttl],
            $this->secret,
            'HS256',
        );
    }

    public function parse(string $token): ?array
    {
        try {
            JWT::$timestamp = $this->clock->now()->getTimestamp();
            $claims = (array) JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$timestamp = null;
        }

        if (! isset($claims['sub'], $claims['role'])) {
            return null;
        }

        return ['sub' => (string) $claims['sub'], 'role' => (string) $claims['role']];
    }
}
