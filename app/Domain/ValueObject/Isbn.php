<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\DomainError;

final readonly class Isbn
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw DomainError::validation('El ISBN debe tener exactamente 13 dígitos.');
        }

        $this->value = $value;
    }
}
