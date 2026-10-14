<?php

namespace Tests;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Ramsey\Uuid\Uuid;

abstract class TestCase extends BaseTestCase
{
    /** @return array{Authorization: string} header of a freshly created user with the given role */
    protected function tokenFor(string $role, ?string $username = null): array
    {
        $id = Uuid::uuid4()->toString();
        $username ??= 'user-'.substr($id, 0, 8);

        app(UserRepository::class)->create($id, $username, app(PasswordHasher::class)->hash('secret-pass'), $role);

        return ['Authorization' => 'Bearer '.app(TokenIssuer::class)->issue($id, $role)];
    }
}
