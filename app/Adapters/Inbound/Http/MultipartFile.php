<?php

namespace App\Adapters\Inbound\Http;

use Illuminate\Http\Request;

/**
 * PHP does not parse multipart/form-data for PUT, and the contract says PUT /books/{id}/cover.
 * Uses the parsed file when there is one (POST, tests); otherwise reads the field from the raw body.
 */
final class MultipartFile
{
    public static function contents(Request $request, string $field): ?string
    {
        $file = $request->file($field);

        if ($file !== null) {
            return $file->isValid() ? file_get_contents($file->getRealPath()) : null;
        }

        if (! preg_match('/boundary="?([^";]+)"?/i', (string) $request->header('Content-Type'), $m)) {
            return null;
        }

        $raw = $request->getContent();

        foreach (explode('--'.$m[1], $raw) as $part) {
            $split = strpos($part, "\r\n\r\n");

            if ($split === false) {
                continue;
            }

            $headers = substr($part, 0, $split);

            if (! preg_match('/name="'.preg_quote($field, '/').'"/i', $headers)) {
                continue;
            }

            return preg_replace('/\r\n$/', '', substr($part, $split + 4));
        }

        return null;
    }
}
