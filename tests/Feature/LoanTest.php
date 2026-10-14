<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use DatabaseTransactions;

    private array $admin;

    private array $librarian;

    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->tokenFor('admin');
        $this->librarian = $this->tokenFor('librarian');
    }

    private function book(int $total = 3, ?string $title = null): array
    {
        self::$seq++;

        return $this->postJson('/api/v1/books', [
            'title' => $title ?? 'Libro '.self::$seq,
            'author' => 'Autor',
            'isbn' => str_pad((string) (2000000000000 + self::$seq), 13, '0', STR_PAD_LEFT),
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => $total,
        ], $this->admin)->assertCreated()->json();
    }

    private function member(): string
    {
        self::$seq++;

        return $this->postJson('/api/v1/members', ['full_name' => 'Socio '.self::$seq, 'document_number' => 'DOC-'.self::$seq], $this->librarian)->assertCreated()->json('id');
    }

    private function lend(string $memberId, array $bookIds): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/loans', ['member_id' => $memberId, 'items' => array_map(fn ($id) => ['book_id' => $id], $bookIds)], $this->librarian);
    }

    private function available(string $bookId): int
    {
        return $this->getJson("/api/v1/books/{$bookId}", $this->librarian)->json('available_copies');
    }

    public function test_lending_takes_one_copy_per_line_freezes_the_values_and_sets_the_due_date(): void
    {
        $a = $this->book(3, 'Cien años de soledad');
        $b = $this->book(1);
        $memberId = $this->member();

        $loan = $this->lend($memberId, [$a['id'], $b['id']])
            ->assertCreated()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('member_id', $memberId)
            ->assertJsonCount(2, 'items')
            ->json();

        $this->assertSame(2, $this->available($a['id']));
        $this->assertSame(0, $this->available($b['id']));

        $item = collect($loan['items'])->firstWhere('book_id', $a['id']);
        $this->assertSame('Cien años de soledad', $item['book_title']);
        $this->assertSame('Novela', $item['category_name']);
        $this->assertNull($item['returned_at']);
        $this->assertSame(
            (new \DateTimeImmutable($loan['loaned_at']))->modify('+14 days')->format('Y-m-d\TH:i:s\Z'),
            $item['due_at'],
        );
    }

    public function test_a_book_without_copies_is_a_conflict_and_nothing_is_registered(): void
    {
        $withCopies = $this->book(2);
        $last = $this->book(1);
        $memberId = $this->member();

        $this->lend($this->member(), [$last['id']])->assertCreated();

        $this->lend($memberId, [$withCopies['id'], $last['id']])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOK_NOT_AVAILABLE');

        $this->assertSame(2, $this->available($withCopies['id']));
        $this->getJson("/api/v1/loans?member_id={$memberId}", $this->librarian)->assertJsonPath('total', 0);
    }

    public function test_a_loan_has_one_to_three_distinct_lines(): void
    {
        $a = $this->book();
        $memberId = $this->member();

        $this->lend($memberId, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->lend($memberId, [$a['id'], $this->book()['id'], $this->book()['id'], $this->book()['id']])->assertStatus(422);
        $this->lend($memberId, [$a['id'], $a['id']])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_BOOK_IN_LOAN');
        $this->assertSame(3, $this->available($a['id']));
    }

    public function test_a_member_cannot_hold_more_than_three_books_and_the_error_reports_the_count(): void
    {
        $memberId = $this->member();
        $this->lend($memberId, [$this->book()['id'], $this->book()['id']])->assertCreated();

        $this->lend($memberId, [$this->book()['id'], $this->book()['id']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MEMBER_LIMIT_EXCEEDED')
            ->assertJsonPath('message', 'El socio ya tiene 2 libros sin devolver y el máximo es 3.');
    }

    public function test_a_member_with_an_overdue_book_cannot_borrow_another(): void
    {
        $memberId = $this->member();
        $loanId = $this->lend($memberId, [$this->book()['id']])->json('id');
        DB::table('library.loan_item')->where('loan_id', $loanId)->update(['due_at' => '2020-01-01 00:00:00+00']);

        $this->lend($memberId, [$this->book()['id']])->assertStatus(409)->assertJsonPath('code', 'MEMBER_HAS_OVERDUE');
    }

    public function test_an_unknown_member_or_book_is_404(): void
    {
        $this->lend('11111111-2222-4333-8444-555555555555', [$this->book()['id']])->assertStatus(404);
        $this->lend($this->member(), ['11111111-2222-4333-8444-555555555555'])->assertStatus(404);
        $this->lend('not-a-uuid', [])->assertStatus(422);
    }

    public function test_returning_a_line_restocks_once_and_closes_the_loan_when_all_are_back(): void
    {
        $a = $this->book(2);
        $b = $this->book(2);
        $loan = $this->lend($this->member(), [$a['id'], $b['id']])->json();
        [$first, $second] = $loan['items'];
        $url = fn (array $item) => "/api/v1/loans/{$loan['id']}/items/{$item['id']}/return";

        $this->postJson($url($first), [], $this->librarian)->assertOk()->assertJsonPath('id', $first['id'])->assertJsonStructure(['returned_at']);
        $this->assertNotNull($this->postJson($url($second), [], $this->librarian)->json('returned_at'));

        $this->assertSame(2, $this->available($a['id']));
        $this->assertSame(2, $this->available($b['id']));
        $this->getJson("/api/v1/loans/{$loan['id']}", $this->librarian)->assertJsonPath('status', 'closed');
    }

    public function test_returning_the_same_line_twice_is_a_conflict_and_never_adds_two_copies(): void
    {
        $a = $this->book(2);
        $loan = $this->lend($this->member(), [$a['id']])->json();
        $url = "/api/v1/loans/{$loan['id']}/items/{$loan['items'][0]['id']}/return";

        $this->postJson($url, [], $this->librarian)->assertOk();
        $this->postJson($url, [], $this->librarian)->assertStatus(409)->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');

        $this->assertSame(2, $this->available($a['id']));
    }

    public function test_a_missing_line_is_404(): void
    {
        $loan = $this->lend($this->member(), [$this->book()['id']])->json();

        $this->postJson("/api/v1/loans/{$loan['id']}/items/11111111-2222-4333-8444-555555555555/return", [], $this->librarian)->assertStatus(404);
    }

    public function test_the_status_is_computed_as_open_overdue_and_closed(): void
    {
        $loan = $this->lend($this->member(), [$this->book()['id']])->assertJsonPath('status', 'open')->json();

        DB::table('library.loan_item')->where('loan_id', $loan['id'])->update(['due_at' => '2020-01-01 00:00:00+00']);
        $this->getJson("/api/v1/loans/{$loan['id']}", $this->librarian)->assertJsonPath('status', 'overdue');

        $this->postJson("/api/v1/loans/{$loan['id']}/items/{$loan['items'][0]['id']}/return", [], $this->librarian)->assertOk();
        $this->getJson("/api/v1/loans/{$loan['id']}", $this->librarian)->assertJsonPath('status', 'closed');
    }

    public function test_listing_filters_by_member_status_and_dates_newest_first(): void
    {
        $memberId = $this->member();
        $old = $this->lend($memberId, [$this->book()['id']])->json('id');
        $new = $this->lend($memberId, [$this->book()['id']])->json('id');
        DB::table('library.loan')->where('id', $old)->update(['loaned_at' => '2026-01-10 10:00:00+00']);
        DB::table('library.loan')->where('id', $new)->update(['loaned_at' => '2026-02-10 10:00:00+00']);
        DB::table('library.loan_item')->where('loan_id', $old)->update(['due_at' => '2020-01-01 00:00:00+00']);

        $ids = fn (string $query) => collect($this->getJson("/api/v1/loans?member_id={$memberId}{$query}", $this->librarian)->assertOk()->json('items'))->pluck('id')->all();

        $this->assertSame([$new, $old], $ids(''));
        $this->assertSame([$old], $ids('&status=overdue'));
        $this->assertSame([$new], $ids('&status=open'));
        $this->assertSame([], $ids('&status=closed'));
        $this->assertSame([$old], $ids('&from=2026-01-01&to=2026-01-31'));
        $this->assertSame([$new], $ids('&size=1'));
        $this->getJson('/api/v1/loans?status=weird', $this->librarian)->assertStatus(422);
        $this->getJson('/api/v1/loans?from=2026-02-01&to=2026-01-01', $this->librarian)->assertStatus(422)->assertJsonPath('code', 'INVALID_DATE_RANGE');
    }

    public function test_overdue_lists_unreturned_lines_most_late_first_with_the_member_name(): void
    {
        $memberA = $this->member();
        $memberB = $this->member();
        $loanA = $this->lend($memberA, [$this->book(3, 'Mas tarde')['id']])->json();
        $loanB = $this->lend($memberB, [$this->book(3, 'Menos tarde')['id']])->json();
        DB::table('library.loan_item')->where('loan_id', $loanA['id'])->update(['due_at' => now()->subDays(10)]);
        DB::table('library.loan_item')->where('loan_id', $loanB['id'])->update(['due_at' => now()->subDays(3)]);

        $rows = collect($this->getJson('/api/v1/loans/overdue', $this->librarian)->assertOk()->json('items'));

        $this->assertSame(['Mas tarde', 'Menos tarde'], $rows->whereIn('loan_id', [$loanA['id'], $loanB['id']])->pluck('book_title')->values()->all());
        $mostLate = $rows->firstWhere('loan_id', $loanA['id']);
        $this->assertGreaterThanOrEqual(9, $mostLate['days_late']);
        $this->assertStringStartsWith('Socio', $mostLate['member_name']);
        $this->assertSame($loanA['items'][0]['id'], $mostLate['item_id']);
    }

    public function test_the_report_counts_borrowings_by_frozen_title_and_does_not_follow_renames(): void
    {
        $a = $this->book(5, 'Titulo viejo');
        $b = $this->book(5, 'Otro libro');
        $this->lend($this->member(), [$a['id'], $b['id']])->assertCreated();
        $this->lend($this->member(), [$a['id']])->assertCreated();

        $this->putJson("/api/v1/books/{$a['id']}", ['title' => 'Titulo nuevo', 'author' => 'Autor', 'category_id' => '22222222-2222-4222-8222-222222222222', 'total_copies' => 5], $this->admin)->assertOk();

        $today = now('UTC')->format('Y-m-d');
        $rows = collect($this->getJson("/api/v1/reports/most-borrowed?from={$today}&to={$today}", $this->admin)->assertOk()->json('rows'));

        $top = $rows->firstWhere('book_id', $a['id']);
        $this->assertSame('Titulo viejo', $top['book_title']);
        $this->assertSame('Novela', $top['category_name']);
        $this->assertSame(2, $top['times_borrowed']);
        $this->assertSame(1, $rows->firstWhere('book_id', $b['id'])['times_borrowed']);
        $this->assertArrayNotHasKey('member_name', $top);
        $this->assertArrayNotHasKey('member_id', $top);
    }

    public function test_the_report_is_admin_only_validates_the_range_and_an_empty_range_is_an_empty_list(): void
    {
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-01-01&to=2026-01-31', $this->librarian)->assertStatus(403);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-02-01&to=2026-01-01', $this->admin)->assertStatus(422)->assertJsonPath('code', 'INVALID_DATE_RANGE');
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-01-01', $this->admin)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->getJson('/api/v1/reports/most-borrowed?from=1999-01-01&to=1999-01-31', $this->admin)->assertOk()->assertJsonPath('rows', []);
    }

    public function test_member_detail_counts_unreturned_loans(): void
    {
        $memberId = $this->member();
        $this->lend($memberId, [$this->book()['id'], $this->book()['id']])->assertCreated();

        $this->getJson("/api/v1/members/{$memberId}", $this->librarian)->assertJsonPath('active_loans', 2);
    }
}
