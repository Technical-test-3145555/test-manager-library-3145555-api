<?php

namespace Tests\Feature;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $username, string $role = 'librarian'): void
    {
        app(UserRepository::class)->create(Uuid::uuid4()->toString(), $username, app(PasswordHasher::class)->hash('secret-pass'), $role);
    }

    public function test_login_returns_a_token_valid_for_eight_hours(): void
    {
        $this->makeUser('ana');

        $this->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 28800)
            ->assertJsonPath('role', 'librarian')
            ->assertJsonStructure(['access_token']);
    }

    public function test_login_failure_does_not_say_whether_the_user_or_the_password_failed(): void
    {
        $this->makeUser('ana');

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => 'nope']);
        $unknownUser = $this->postJson('/api/v1/auth/login', ['username' => 'ghost', 'password' => 'nope']);

        $wrongPassword->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->assertSame($wrongPassword->json(), $unknownUser->json());
        $this->assertSame(401, $unknownUser->status());
    }

    public function test_login_validates_the_input_with_the_single_error_body(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['code', 'message', 'details' => [['field', 'issue']]]);
    }

    public function test_every_endpoint_except_login_and_health_needs_a_token(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/categories')->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->getJson('/api/v1/categories', ['Authorization' => 'Bearer garbage'])->assertStatus(401);
    }

    public function test_the_admin_creates_a_librarian_with_a_normalized_name_and_no_password_in_the_response(): void
    {
        $admin = $this->tokenFor('admin');

        $response = $this->postJson('/api/v1/users', ['username' => ' Ana2 ', 'password' => 'secret-pass', 'role' => 'librarian'], $admin)
            ->assertCreated()
            ->assertJsonPath('username', 'ana2')
            ->assertJsonPath('role', 'librarian')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('password_hash');

        $this->assertNotEmpty($response->json('id'));
    }

    public function test_a_repeated_username_is_a_conflict(): void
    {
        $admin = $this->tokenFor('admin');
        $payload = ['username' => 'dup', 'password' => 'secret-pass', 'role' => 'librarian'];

        $this->postJson('/api/v1/users', $payload, $admin)->assertCreated();
        $this->postJson('/api/v1/users', ['username' => ' DUP '] + $payload, $admin)
            ->assertStatus(409)
            ->assertJsonPath('code', 'USERNAME_TAKEN');
    }

    public function test_asking_for_the_admin_role_through_the_api_is_rejected(): void
    {
        $this->postJson('/api/v1/users', ['username' => 'boss', 'password' => 'secret-pass', 'role' => 'admin'], $this->tokenFor('admin'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_a_librarian_cannot_create_users(): void
    {
        $this->postJson('/api/v1/users', ['username' => 'x', 'password' => 'secret-pass', 'role' => 'librarian'], $this->tokenFor('librarian'))
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    public function test_categories_lists_the_five_seeded_ones_ordered_by_name(): void
    {
        $names = collect($this->getJson('/api/v1/categories', $this->tokenFor('librarian'))->assertOk()->json())->pluck('name')->all();

        $this->assertSame(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología'], $names);
    }
}
