<?php

namespace App\Domain;

use App\Domain\Exception\DomainError;
use DateTimeImmutable;

final class Loan
{
    public const MAX_ITEMS = 3;

    public const TERM_DAYS = 14;

    /** @var list<LoanItem> */
    private array $items = [];

    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
    ) {
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Adding a line and taking the copy are one operation (architecture.md, aggregates):
     * the book is withdrawn before the line exists.
     */
    public function addItem(string $itemId, Book $book, string $categoryName): LoanItem
    {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw DomainError::validation('Un préstamo admite de 1 a 3 libros.');
        }

        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw DomainError::duplicateBookInLoan();
            }
        }

        $book->withdraw();

        $item = new LoanItem(
            $itemId,
            $book->id,
            $book->title,
            $book->isbn->value,
            $categoryName,
            $this->loanedAt->modify('+'.self::TERM_DAYS.' days'),
        );
        $this->items[] = $item;

        return $item;
    }

    /** CA-07.1 and CA-07.2: a line is returned once, and restocks the book once. */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $now): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id !== $itemId) {
                continue;
            }

            if ($item->isReturned()) {
                throw DomainError::itemAlreadyReturned();
            }

            $item->markReturned($now);
            $book->restock();

            return $item;
        }

        throw new DomainError('NOT_FOUND', 'La línea del préstamo no existe.');
    }

    /** Computed, never stored (Article VII): open, overdue or closed. */
    public function status(DateTimeImmutable $now): string
    {
        $pending = array_filter($this->items, fn (LoanItem $item) => ! $item->isReturned());

        if ($pending === []) {
            return 'closed';
        }

        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return 'overdue';
            }
        }

        return 'open';
    }
}
