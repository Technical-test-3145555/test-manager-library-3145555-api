<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Exception\DomainError;

/**
 * CA-03.4: JPG or PNG up to 2 MB, validated by content, stored under an opaque key.
 * Retention rule: the key is cleared and committed first, the binary is deleted after.
 */
final class CoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
    ) {
    }

    public function upload(string $bookId, string $contents): array
    {
        $book = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');

        if (strlen($contents) > self::MAX_BYTES) {
            throw DomainError::validation('La portada supera los 2 MB.', [['field' => 'file', 'issue' => 'demasiado grande']]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! isset(self::TYPES[$mime])) {
            throw DomainError::validation('La portada debe ser JPG o PNG.', [['field' => 'file', 'issue' => 'tipo no admitido']]);
        }

        $key = $this->storage->put($contents, self::TYPES[$mime]);
        $this->books->setCoverKey($bookId, $key);

        if ($book['cover_key'] !== null) {
            $this->storage->delete($book['cover_key']);
        }

        return $this->books->find($bookId);
    }

    public function remove(string $bookId): void
    {
        $book = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');

        if ($book['cover_key'] === null) {
            return;
        }

        $this->books->setCoverKey($bookId, null);
        $this->storage->delete($book['cover_key']);
    }

    /** @return array{contents: string, mime: string} */
    public function get(string $bookId): array
    {
        $book = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
        $key = $book['cover_key'] ?? throw DomainError::notFound('La portada');
        $contents = $this->storage->get($key) ?? throw DomainError::notFound('La portada');

        return ['contents' => $contents, 'mime' => str_ends_with($key, '.png') ? 'image/png' : 'image/jpeg'];
    }
}
