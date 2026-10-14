<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\Clock;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class SystemClock implements Clock
{
    public function __construct(private readonly int $offsetDays = 0)
    {
        if ($offsetDays !== 0 && app()->environment('production')) {
            throw new RuntimeException('CLOCK_OFFSET_DAYS is not allowed in production.');
        }
    }

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->offsetDays === 0 ? $now : $now->modify("{$this->offsetDays} days");
    }
}
