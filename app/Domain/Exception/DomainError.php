<?php

namespace App\Domain\Exception;

use RuntimeException;

/**
 * Base of every business error. Only the HTTP adapter maps `code` to a status
 * and to the single error body of api-contract.md.
 */
class DomainError extends RuntimeException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function validation(string $message, array $details = []): self
    {
        return new self('VALIDATION_ERROR', $message, $details);
    }

    public static function invalidCredentials(): self
    {
        return new self('INVALID_CREDENTIALS', 'Credenciales inválidas.');
    }

    public static function forbiddenRole(): self
    {
        return new self('FORBIDDEN_ROLE', 'Tu rol no permite esta operación.');
    }

    public static function notFound(string $what = 'El recurso'): self
    {
        return new self('NOT_FOUND', "{$what} no existe.");
    }

    public static function usernameTaken(): self
    {
        return new self('USERNAME_TAKEN', 'Ese nombre de usuario ya existe.');
    }

    public static function isbnTaken(): self
    {
        return new self('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.');
    }

    public static function documentTaken(): self
    {
        return new self('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.');
    }

    public static function bookHasOpenLoans(): self
    {
        return new self('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos abiertos y no se puede dar de baja.');
    }

    public static function bookNotAvailable(string $title): self
    {
        return new self('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$title}».");
    }

    public static function memberHasOverdue(): self
    {
        return new self('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir otro.');
    }

    public static function memberLimitExceeded(int $current): self
    {
        return new self('MEMBER_LIMIT_EXCEEDED', "El socio ya tiene {$current} libros sin devolver y el máximo es 3.");
    }

    public static function duplicateBookInLoan(): self
    {
        return new self('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir el mismo libro en un préstamo.');
    }

    public static function itemAlreadyReturned(): self
    {
        return new self('ITEM_ALREADY_RETURNED', 'Esta línea ya fue devuelta.');
    }

    public static function invalidDateRange(): self
    {
        return new self('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.');
    }

    /** Status of the single error body (api-contract.md sections 1 and 2). */
    public function httpStatus(): int
    {
        return match ($this->errorCode) {
            'INVALID_CREDENTIALS' => 401,
            'FORBIDDEN_ROLE' => 403,
            'NOT_FOUND' => 404,
            'USERNAME_TAKEN', 'ISBN_TAKEN', 'DOCUMENT_TAKEN', 'BOOK_HAS_OPEN_LOANS',
            'BOOK_NOT_AVAILABLE', 'MEMBER_HAS_OVERDUE', 'ITEM_ALREADY_RETURNED' => 409,
            default => 422,
        };
    }
}
