<?php

namespace App\Application\Ports\Outbound;

use DateTimeImmutable;

/**
 * Rows are plain arrays shaped like the API book:
 * id, title, author, isbn, category{id,name}, total_copies, available_copies, cover_key.
 */
interface BookRepository
{
    /** @return array{items: list<array>, total: int} active books only, ordered by title (CA-04.1) */
    public function search(?string $q, ?string $categoryId, int $page, int $size): array;

    /** @return array|null null when it does not exist or was deactivated (CA-04.3) */
    public function find(string $id): ?array;

    public function categoryExists(string $categoryId): bool;

    /** Returns false when an active book already has that ISBN (CA-03.2). */
    public function insert(string $id, string $title, string $author, string $isbn, string $categoryId, int $total): bool;

    public function update(string $id, string $title, string $author, string $categoryId, int $total, int $available): void;

    public function hasOpenLoans(string $id): bool;

    public function softDelete(string $id, DateTimeImmutable $at): void;

    public function setCoverKey(string $id, ?string $key): void;
}
