<?php

namespace App\Application\Ports\Outbound;

/** Cover binaries live outside the database; the database keeps only an opaque key (D-08). */
interface CoverStorage
{
    /** @return string the opaque key */
    public function put(string $contents, string $extension): string;

    /** @return string|null the binary, or null when the key is unknown */
    public function get(string $key): ?string;

    public function delete(string $key): void;
}
