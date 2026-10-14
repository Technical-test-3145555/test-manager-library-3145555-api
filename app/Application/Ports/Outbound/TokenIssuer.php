<?php

namespace App\Application\Ports\Outbound;

interface TokenIssuer
{
    /** Lifetime in seconds (8 hours, CA-01.1). */
    public function ttl(): int;

    public function issue(string $userId, string $role): string;

    /** @return array{sub: string, role: string}|null null when the token is invalid or expired */
    public function parse(string $token): ?array;
}
