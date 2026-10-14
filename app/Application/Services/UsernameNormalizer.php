<?php

namespace App\Application\Services;

/** CA-02.2: usernames are stored lowercase and trimmed. */
final class UsernameNormalizer
{
    public static function normalize(string $username): string
    {
        return mb_strtolower(trim($username));
    }
}
