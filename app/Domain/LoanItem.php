<?php

namespace App\Domain;

use DateTimeImmutable;

/** One book inside a loan. Title, ISBN and category name are frozen at loan time (RN-4). */
final class LoanItem
{
    private ?DateTimeImmutable $returnedAt = null;

    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
    ) {
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    public function markReturned(DateTimeImmutable $at): void
    {
        $this->returnedAt = $at;
    }
}
