<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;
use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use Ramsey\Uuid\Uuid;

/** CA-03.1 to CA-03.3: available copies start equal to the total, the server computes them. */
final class CreateBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function create(string $title, string $author, string $isbn, string $categoryId, int $total): array
    {
        $title = trim($title);
        $author = trim($author);
        BookInput::assertTexts($title, $author);

        // The domain objects validate the invariants: ISBN of 13 digits, total of at least 1.
        $book = new Book(Uuid::uuid4()->toString(), $title, $author, new Isbn($isbn), $categoryId, new Quantity($total));
        BookInput::assertCategory($this->books, $categoryId);

        if (! $this->books->insert($book->id, $title, $author, $book->isbn->value, $categoryId, $book->total())) {
            throw DomainError::isbnTaken();
        }

        return $this->books->find($book->id);
    }
}
