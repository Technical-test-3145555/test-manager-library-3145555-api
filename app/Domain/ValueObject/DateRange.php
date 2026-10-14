<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\DomainError;
use DateTimeImmutable;

/**
 * Inclusive calendar range in UTC (DT-12): [from 00:00:00, to + 1 day 00:00:00).
 */
final readonly class DateRange
{
    public DateTimeImmutable $startsAt;

    public DateTimeImmutable $endsBefore;

    public function __construct(DateTimeImmutable $from, DateTimeImmutable $to)
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        if ($to < $from) {
            throw DomainError::invalidDateRange();
        }

        $this->startsAt = $from;
        $this->endsBefore = $to->modify('+1 day');
    }
}
