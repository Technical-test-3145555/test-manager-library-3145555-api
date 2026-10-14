<?php

namespace App\Adapters\Outbound\Persistence;

/**
 * Builds a "contains" pattern for ILIKE with `!` as the escape character
 * (a backslash escape confuses the PDO placeholder parser).
 */
final class LikePattern
{
    public const ESCAPE = "ESCAPE '!'";

    public static function contains(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
    }
}
