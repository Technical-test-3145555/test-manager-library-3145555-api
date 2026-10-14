<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Exception\DomainError;

/** HU-01: one use case, one reason to change. */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    /** @return array{access_token: string, token_type: string, expires_in: int, role: string} */
    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(UsernameNormalizer::normalize($username));

        // Same message and same work whether the user or the password failed (CA-01.2).
        $hash = $user['password_hash'] ?? $this->hasher->hash('not-a-real-password');
        $valid = $this->hasher->verify($password, $hash);

        if ($user === null || ! $valid) {
            throw DomainError::invalidCredentials();
        }

        return [
            'access_token' => $this->tokens->issue($user['id'], $user['role']),
            'token_type' => 'Bearer',
            'expires_in' => $this->tokens->ttl(),
            'role' => $user['role'],
        ];
    }
}
