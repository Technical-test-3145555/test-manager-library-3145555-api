<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookTest extends TestCase
{
    use DatabaseTransactions;

    private const NOVEL = '11111111-1111-4111-8111-111111111111';

    private const SCIENCE = '22222222-2222-4222-8222-222222222222';

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function book(array $overrides = [], ?array $headers = null): \Illuminate\Testing\TestResponse
    {
        $payload = $overrides + [
            'title' => 'Cien años de soledad',
            'author' => 'Gabriel García Márquez',
            'isbn' => '9780307474728',
            'category_id' => self::NOVEL,
            'total_copies' => 4,
        ];

        return $this->postJson('/api/v1/books', $payload, $headers ?? $this->tokenFor('admin'));
    }

    public function test_the_admin_creates_a_book_and_available_copies_equal_the_total(): void
    {
        $this->book()
            ->assertCreated()
            ->assertJsonPath('title', 'Cien años de soledad')
            ->assertJsonPath('category.name', 'Novela')
            ->assertJsonPath('total_copies', 4)
            ->assertJsonPath('available_copies', 4)
            ->assertJsonPath('cover_key', null);
    }

    public function test_a_librarian_cannot_create_a_book(): void
    {
        $this->book([], $this->tokenFor('librarian'))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    public function test_the_isbn_has_thirteen_digits_and_the_total_is_at_least_one(): void
    {
        $this->book(['isbn' => '12345'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->book(['total_copies' => 0])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->book(['category_id' => '99999999-9999-4999-8999-999999999999'])->assertStatus(422);
    }

    public function test_a_repeated_isbn_conflicts_but_it_can_be_registered_again_after_the_soft_delete(): void
    {
        $admin = $this->tokenFor('admin');
        $id = $this->book([], $admin)->assertCreated()->json('id');

        $this->book([], $admin)->assertStatus(409)->assertJsonPath('code', 'ISBN_TAKEN');

        $this->deleteJson("/api/v1/books/{$id}", [], $admin)->assertNoContent();
        $this->book([], $admin)->assertCreated();
    }

    public function test_changing_the_total_moves_the_available_copies_and_never_leaves_them_negative(): void
    {
        $admin = $this->tokenFor('admin');
        $id = $this->book([], $admin)->json('id');
        DB::table('library.book')->where('id', $id)->update(['available_copies' => 1]);

        $this->putJson("/api/v1/books/{$id}", ['title' => 'Nuevo', 'author' => 'A', 'category_id' => self::SCIENCE, 'total_copies' => 6], $admin)
            ->assertOk()
            ->assertJsonPath('total_copies', 6)
            ->assertJsonPath('available_copies', 3)
            ->assertJsonPath('category.name', 'Ciencia');

        $this->putJson("/api/v1/books/{$id}", ['title' => 'Nuevo', 'author' => 'A', 'category_id' => self::SCIENCE, 'total_copies' => 2], $admin)
            ->assertStatus(422);
    }

    public function test_a_deleted_book_disappears_from_the_catalog_and_returns_404(): void
    {
        $admin = $this->tokenFor('admin');
        $id = $this->book([], $admin)->json('id');

        $this->deleteJson("/api/v1/books/{$id}", [], $admin)->assertNoContent();
        $this->getJson("/api/v1/books/{$id}", $admin)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/api/v1/books?q=Cien', $admin)->assertJsonPath('total', 0);
    }

    public function test_a_book_with_open_loans_cannot_be_deleted(): void
    {
        $admin = $this->tokenFor('admin');
        $id = $this->book([], $admin)->json('id');
        $memberId = '77777777-7777-4777-8777-777777777777';
        $userId = '88888888-8888-4888-8888-888888888888';
        DB::table('library.member')->insert(['id' => $memberId, 'full_name' => 'Socio', 'document_number' => 'D-1']);
        DB::table('library.user')->insert(['id' => $userId, 'username' => 'u-open', 'password_hash' => 'x', 'role' => 'librarian']);
        DB::table('library.loan')->insert(['id' => '99999999-0000-4000-8000-000000000001', 'member_id' => $memberId, 'loaned_by_user_id' => $userId, 'loaned_at' => '2026-10-14 00:00:00+00']);
        DB::table('library.loan_item')->insert([
            'id' => '99999999-0000-4000-8000-000000000002', 'loan_id' => '99999999-0000-4000-8000-000000000001', 'book_id' => $id,
            'book_title' => 'x', 'isbn' => '9780307474728', 'category_name' => 'Novela', 'due_at' => '2026-10-28 00:00:00+00', 'returned_at' => null,
        ]);

        $this->deleteJson("/api/v1/books/{$id}", [], $admin)->assertStatus(409)->assertJsonPath('code', 'BOOK_HAS_OPEN_LOANS');
    }

    public function test_search_filters_by_text_and_category_orders_by_title_and_paginates(): void
    {
        $admin = $this->tokenFor('admin');
        $this->book(['title' => 'Zeta tecnica', 'isbn' => '1000000000001', 'category_id' => self::SCIENCE], $admin)->assertCreated();
        $this->book(['title' => 'Alfa novela', 'isbn' => '1000000000002', 'author' => 'Zeta Autor'], $admin)->assertCreated();
        $this->book(['title' => 'Beta', 'isbn' => '1000000000003'], $admin)->assertCreated();

        $titles = fn ($r) => collect($r->json('items'))->pluck('title')->all();

        $all = $this->getJson('/api/v1/books?size=2&page=1', $admin)->assertOk();
        $this->assertSame(['Alfa novela', 'Beta'], $titles($all));
        $all->assertJsonPath('total', 3)->assertJsonPath('page', 1)->assertJsonPath('size', 2);

        $this->assertSame(['Alfa novela', 'Zeta tecnica'], $titles($this->getJson('/api/v1/books?q=zeta', $admin)));
        $this->assertSame(['Zeta tecnica'], $titles($this->getJson('/api/v1/books?category_id='.self::SCIENCE, $admin)));
        $this->getJson('/api/v1/books?size=51', $admin)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_the_cover_is_validated_by_content_stored_under_an_opaque_key_and_removable(): void
    {
        $admin = $this->tokenFor('admin');
        $id = $this->book([], $admin)->json('id');
        $auth = ['HTTP_AUTHORIZATION' => $admin['Authorization'], 'HTTP_ACCEPT' => 'application/json'];

        $png = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($png, base64_decode(self::PNG));

        $this->call('PUT', "/api/v1/books/{$id}/cover", [], [], ['file' => new UploadedFile($png, 'cover.png', 'image/png', null, true)], $auth)
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('cover_key', fn ($key) => preg_match('/^[0-9a-f-]{36}\.png$/', $key) === 1)->etc());

        $this->get("/api/v1/books/{$id}/cover", $admin)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->deleteJson("/api/v1/books/{$id}/cover", [], $admin)->assertNoContent();
        $this->getJson("/api/v1/books/{$id}", $admin)->assertJsonPath('cover_key', null);
        $this->getJson("/api/v1/books/{$id}/cover", $admin)->assertStatus(404);

        unlink($png);
    }

    public function test_a_text_file_and_a_file_over_two_megabytes_are_rejected(): void
    {
        $admin = $this->tokenFor('admin');
        $id = $this->book([], $admin)->json('id');
        $auth = ['HTTP_AUTHORIZATION' => $admin['Authorization'], 'HTTP_ACCEPT' => 'application/json'];

        $text = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($text, 'not an image');
        $this->call('PUT', "/api/v1/books/{$id}/cover", [], [], ['file' => new UploadedFile($text, 'fake.png', 'image/png', null, true)], $auth)->assertStatus(422);

        $big = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($big, base64_decode(self::PNG).str_repeat('a', 2 * 1024 * 1024 + 1));
        $this->call('PUT', "/api/v1/books/{$id}/cover", [], [], ['file' => new UploadedFile($big, 'big.png', 'image/png', null, true)], $auth)->assertStatus(422);

        $this->getJson("/api/v1/books/{$id}", $admin)->assertJsonPath('cover_key', null);
        unlink($text);
        unlink($big);
    }
}
