<?php

namespace App\Domain;

use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;

final class Book
{
    private int $available;

    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $author,
        public readonly Isbn $isbn,
        public readonly string $categoryId,
        private Quantity $total,
        ?int $available = null,
    ) {
        if (trim($title) === '') {
            throw DomainError::validation('El título no puede estar vacío.');
        }

        $this->available = $available ?? $total->value;

        if ($this->available < 0 || $this->available > $total->value) {
            throw DomainError::validation('Los disponibles deben estar entre 0 y el total.');
        }
    }

    public function available(): int
    {
        return $this->available;
    }

    public function total(): int
    {
        return $this->total->value;
    }

    /** CA-03.3: changing the total moves the available copies by the same difference, never below 0. */
    public function changeTotal(Quantity $newTotal): void
    {
        $newAvailable = $this->available + ($newTotal->value - $this->total->value);

        if ($newAvailable < 0) {
            throw DomainError::validation('El total no puede dejar los disponibles por debajo de 0.');
        }

        $this->total = $newTotal;
        $this->available = $newAvailable;
    }

    /** One copy leaves the shelf (CA-06.3). */
    public function withdraw(): void
    {
        if ($this->available < 1) {
            throw DomainError::bookNotAvailable($this->title);
        }

        $this->available--;
    }

    /** One copy comes back (CA-07.1); available never exceeds total (CA-07.3). */
    public function restock(): void
    {
        if ($this->available >= $this->total->value) {
            throw DomainError::validation('Los disponibles no pueden superar el total.');
        }

        $this->available++;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }
}
