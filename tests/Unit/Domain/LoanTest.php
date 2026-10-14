<?php

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Exception\DomainError;
use App\Domain\Loan;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LoanTest extends TestCase
{
    private function book(string $id, int $total = 2): Book
    {
        return new Book($id, "Libro {$id}", 'Autor', new Isbn('9780307474728'), 'c1', new Quantity($total));
    }

    private function loan(): Loan
    {
        return new Loan('l1', 'm1', 'u1', new DateTimeImmutable('2026-10-14T14:15:00Z'));
    }

    public function test_due_date_is_fourteen_days_after_the_loan_and_values_are_frozen(): void
    {
        $loan = $this->loan();
        $book = $this->book('b1');

        $item = $loan->addItem('i1', $book, 'Novela');

        $this->assertSame('2026-10-28T14:15:00+00:00', $item->dueAt->format('c'));
        $this->assertSame('Libro b1', $item->bookTitle);
        $this->assertSame('Novela', $item->categoryName);
        $this->assertSame(1, $book->available());
    }

    public function test_the_same_book_cannot_repeat(): void
    {
        $loan = $this->loan();
        $book = $this->book('b1');
        $loan->addItem('i1', $book, 'Novela');

        try {
            $loan->addItem('i2', $book, 'Novela');
            $this->fail('A repeated book should be rejected');
        } catch (DomainError $e) {
            $this->assertSame('DUPLICATE_BOOK_IN_LOAN', $e->errorCode);
            $this->assertSame(1, $book->available());
        }
    }

    public function test_a_loan_has_at_most_three_lines(): void
    {
        $loan = $this->loan();
        foreach (['b1', 'b2', 'b3'] as $i => $id) {
            $loan->addItem("i{$i}", $this->book($id), 'Novela');
        }

        $this->expectException(DomainError::class);
        $loan->addItem('i9', $this->book('b4'), 'Novela');
    }

    public function test_a_book_without_copies_leaves_the_loan_unchanged(): void
    {
        $loan = $this->loan();
        $book = $this->book('b1', 1);
        $book->withdraw();

        try {
            $loan->addItem('i1', $book, 'Novela');
            $this->fail('A book without copies should be rejected');
        } catch (DomainError $e) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $e->errorCode);
            $this->assertCount(0, $loan->items());
        }
    }

    public function test_status_is_open_overdue_and_closed_with_a_fake_clock(): void
    {
        $loan = $this->loan();
        $book = $this->book('b1');
        $loan->addItem('i1', $book, 'Novela');

        $this->assertSame('open', $loan->status(new DateTimeImmutable('2026-10-20T00:00:00Z')));
        $this->assertSame('overdue', $loan->status(new DateTimeImmutable('2026-10-29T00:00:00Z')));

        $loan->returnItem('i1', $book, new DateTimeImmutable('2026-10-30T00:00:00Z'));
        $this->assertSame('closed', $loan->status(new DateTimeImmutable('2026-11-30T00:00:00Z')));
    }

    public function test_returning_twice_never_restocks_twice(): void
    {
        $loan = $this->loan();
        $book = $this->book('b1');
        $loan->addItem('i1', $book, 'Novela');
        $loan->returnItem('i1', $book, new DateTimeImmutable('2026-10-15T00:00:00Z'));

        try {
            $loan->returnItem('i1', $book, new DateTimeImmutable('2026-10-16T00:00:00Z'));
            $this->fail('A second return should be rejected');
        } catch (DomainError $e) {
            $this->assertSame('ITEM_ALREADY_RETURNED', $e->errorCode);
            $this->assertSame(2, $book->available());
        }
    }
}
