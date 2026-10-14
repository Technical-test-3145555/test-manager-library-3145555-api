<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Domain\Exception\DomainError;

/** CA-03.5: soft delete; a book with open loans is not deactivated. */
final class DeleteBookService
{
    public function __construct(private readonly BookRepository $books, private readonly Clock $clock)
    {
    }

    public function delete(string $id): void
    {
        $this->books->find($id) ?? throw DomainError::notFound('El libro');

        if ($this->books->hasOpenLoans($id)) {
            throw DomainError::bookHasOpenLoans();
        }

        $this->books->softDelete($id, $this->clock->now());
    }
}
