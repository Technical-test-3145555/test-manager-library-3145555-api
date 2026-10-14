<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?array
    {
        $row = DB::table('library.user')->where('username', $username)->first();

        return $row === null ? null : [
            'id' => $row->id,
            'username' => $row->username,
            'password_hash' => $row->password_hash,
            'role' => $row->role,
        ];
    }

    public function create(string $id, string $username, string $passwordHash, string $role): bool
    {
        try {
            DB::transaction(fn () => DB::table('library.user')->insert([
                'id' => $id,
                'username' => $username,
                'password_hash' => $passwordHash,
                'role' => $role,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
