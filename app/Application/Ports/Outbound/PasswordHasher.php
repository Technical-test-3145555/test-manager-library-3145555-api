<?php

namespace App\Application\Ports\Outbound;

interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}
