<?php

namespace App\Application\Ports\Outbound;

interface UserRepository
{
    /** @return array{id: string, username: string, password_hash: string, role: string}|null */
    public function findByUsername(string $username): ?array;

    /** Returns false when the username already exists. */
    public function create(string $id, string $username, string $passwordHash, string $role): bool;
}
