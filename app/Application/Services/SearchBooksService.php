<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Exception\DomainError;

/** HU-04 */
final class SearchBooksService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    /** @return array{items: list<array>, page: int, size: int, total: int} */
    public function search(?string $q, ?string $categoryId, mixed $page, mixed $size): array
    {
        [$page, $size] = Pagination::parse($page, $size);
        $result = $this->books->search($q === null || trim($q) === '' ? null : trim($q), $categoryId, $page, $size);

        return ['items' => $result['items'], 'page' => $page, 'size' => $size, 'total' => $result['total']];
    }

    public function get(string $id): array
    {
        return $this->books->find($id) ?? throw DomainError::notFound('El libro');
    }
}
