<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use Ramsey\Uuid\Uuid;

/** D-10: the initial admin comes from the environment because only the hasher can produce the hash. */
final class BootstrapAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /** Returns true when the admin was created, false when it already existed. */
    public function ensure(string $username, string $password): bool
    {
        $username = UsernameNormalizer::normalize($username);

        if ($this->users->findByUsername($username) !== null) {
            return false;
        }

        return $this->users->create(Uuid::uuid4()->toString(), $username, $this->hasher->hash($password), 'admin');
    }
}
