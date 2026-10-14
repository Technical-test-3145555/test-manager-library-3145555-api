<?php

namespace App\Adapters\Outbound\Storage;

use App\Application\Ports\Outbound\CoverStorage;
use Ramsey\Uuid\Uuid;

final class DiskCoverStorage implements CoverStorage
{
    private const KEY_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(jpg|png)$/';

    public function __construct(private readonly string $directory)
    {
    }

    public function put(string $contents, string $extension): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $key = Uuid::uuid4()->toString().'.'.$extension;
        file_put_contents($this->path($key), $contents);

        return $key;
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);

        return is_file($path) ? file_get_contents($path) : null;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /** The key is opaque, never a path: anything that is not one of our keys is refused. */
    private function path(string $key): string
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return $this->directory.'/__invalid__';
        }

        return $this->directory.'/'.$key;
    }
}
