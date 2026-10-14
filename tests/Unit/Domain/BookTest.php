<?php

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    private function book(int $total = 2): Book
    {
        return new Book('b1', 'Cien años de soledad', 'García Márquez', new Isbn('9780307474728'), 'c1', new Quantity($total));
    }

    public function test_available_starts_equal_to_total(): void
    {
        $this->assertSame(2, $this->book(2)->available());
    }

    public function test_withdraw_never_goes_below_zero(): void
    {
        $book = $this->book(1);
        $book->withdraw();

        try {
            $book->withdraw();
            $this->fail('A second withdraw of the last copy should fail');
        } catch (DomainError $e) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $e->errorCode);
            $this->assertSame(0, $book->available());
        }
    }

    public function test_restock_never_goes_above_total(): void
    {
        $book = $this->book(1);

        $this->expectException(DomainError::class);
        $book->restock();
    }

    public function test_changing_the_total_moves_available_by_the_same_difference(): void
    {
        $book = $this->book(3);
        $book->withdraw();
        $book->changeTotal(new Quantity(5));

        $this->assertSame(4, $book->available());
    }

    public function test_changing_the_total_cannot_leave_available_negative(): void
    {
        $book = $this->book(3);
        $book->withdraw();
        $book->withdraw();
        $book->withdraw();

        $this->expectException(DomainError::class);
        $book->changeTotal(new Quantity(1));
    }

    public function test_soft_delete_marks_the_book_inactive(): void
    {
        $book = $this->book();
        $book->softDelete(new DateTimeImmutable('2026-10-14T10:00:00Z'));

        $this->assertFalse($book->isActive());
    }
}
