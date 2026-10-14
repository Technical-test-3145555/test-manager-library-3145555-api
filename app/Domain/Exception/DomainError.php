<?php

namespace App\Domain\Exception;

use RuntimeException;

/**
 * Base of every business error. Only the HTTP adapter maps `code` to a status
 * and to the single error body of api-contract.md.
 */
class DomainError extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function validation(string $message): self
    {
        return new self('VALIDATION_ERROR', $message);
    }

    public static function bookNotAvailable(string $title): self
    {
        return new self('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$title}».");
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
}
