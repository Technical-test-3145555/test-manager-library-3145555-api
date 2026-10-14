<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Exception\DomainError;

/** Input rules shared by the book use cases. */
final class BookInput
{
    public static function assertTexts(string $title, string $author): void
    {
        if ($title === '' || mb_strlen($title) > 250) {
            throw DomainError::validation('El título es obligatorio y admite hasta 250 caracteres.', [['field' => 'title', 'issue' => 'inválido']]);
        }

        if ($author === '' || mb_strlen($author) > 200) {
            throw DomainError::validation('El autor es obligatorio y admite hasta 200 caracteres.', [['field' => 'author', 'issue' => 'inválido']]);
        }
    }

    public static function assertCategory(BookRepository $books, string $categoryId): void
    {
        if (! $books->categoryExists($categoryId)) {
            throw DomainError::validation('La categoría no existe.', [['field' => 'category_id', 'issue' => 'no existe']]);
        }
    }
}
