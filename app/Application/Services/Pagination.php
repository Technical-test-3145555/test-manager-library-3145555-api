<?php

namespace App\Application\Services;

use App\Domain\Exception\DomainError;

/** `?page=1&size=20`, size at most 50. Out-of-range values are rejected, not clamped (DT-10). */
final class Pagination
{
    public const MAX_SIZE = 50;

    /** @return array{0: int, 1: int} page and size */
    public static function parse(mixed $page, mixed $size): array
    {
        $page = $page === null || $page === '' ? 1 : $page;
        $size = $size === null || $size === '' ? 20 : $size;

        if (filter_var($page, FILTER_VALIDATE_INT) === false || (int) $page < 1) {
            throw DomainError::validation('page debe ser un entero mayor o igual a 1.', [['field' => 'page', 'issue' => 'inválido']]);
        }

        if (filter_var($size, FILTER_VALIDATE_INT) === false || (int) $size < 1 || (int) $size > self::MAX_SIZE) {
            throw DomainError::validation('size debe estar entre 1 y '.self::MAX_SIZE.'.', [['field' => 'size', 'issue' => 'fuera de rango']]);
        }

        return [(int) $page, (int) $size];
    }
}
