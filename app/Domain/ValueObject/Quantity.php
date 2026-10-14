<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\DomainError;

final readonly class Quantity
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw DomainError::validation('La cantidad debe ser mayor o igual a 1.');
        }
    }
}
