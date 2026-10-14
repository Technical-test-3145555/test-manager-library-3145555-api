<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;
use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;

/** PUT replaces title, author, category and total. The ISBN does not change (DT-09). */
final class UpdateBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function update(string $id, string $title, string $author, string $categoryId, int $total): array
    {
        $row = $this->books->find($id) ?? throw DomainError::notFound('El libro');

        $title = trim($title);
        $author = trim($author);
        BookInput::assertTexts($title, $author);
        BookInput::assertCategory($this->books, $categoryId);

        // CA-03.3: the available copies move by the same difference and never go below 0.
        $book = new Book($id, $title, $author, new Isbn($row['isbn']), $categoryId, new Quantity($row['total_copies']), $row['available_copies']);
        $book->changeTotal(new Quantity($total));

        $this->books->update($id, $title, $author, $categoryId, $book->total(), $book->available());

        return $this->books->find($id);
    }
}
