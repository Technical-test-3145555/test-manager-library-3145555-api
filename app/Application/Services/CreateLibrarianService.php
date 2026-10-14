<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Exception\DomainError;
use Ramsey\Uuid\Uuid;

/** HU-02: the admin creates librarians; nobody creates an admin at runtime. */
final class CreateLibrarianService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /** @return array{id: string, username: string, role: string} */
    public function create(string $username, string $password, string $role): array
    {
        if ($role !== 'librarian') {
            throw DomainError::validation(
                'Solo se puede crear el rol librarian.',
                [['field' => 'role', 'issue' => 'solo se admite librarian']],
            );
        }

        $username = UsernameNormalizer::normalize($username);

        if ($username === '' || mb_strlen($username) > 120) {
            throw DomainError::validation('El nombre de usuario es obligatorio y admite hasta 120 caracteres.', [['field' => 'username', 'issue' => 'inválido']]);
        }

        if ($password === '') {
            throw DomainError::validation('La clave es obligatoria.', [['field' => 'password', 'issue' => 'obligatoria']]);
        }

        $id = Uuid::uuid4()->toString();

        if (! $this->users->create($id, $username, $this->hasher->hash($password), 'librarian')) {
            throw DomainError::usernameTaken();
        }

        return ['id' => $id, 'username' => $username, 'role' => 'librarian'];
    }
}
