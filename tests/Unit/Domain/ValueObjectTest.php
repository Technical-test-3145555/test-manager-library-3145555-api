<?php

namespace Tests\Unit\Domain;

use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\DateRange;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ValueObjectTest extends TestCase
{
    public function test_isbn_accepts_thirteen_digits(): void
    {
        $this->assertSame('9780307474728', (new Isbn(' 9780307474728 '))->value);
    }

    public function test_isbn_rejects_wrong_length_and_letters(): void
    {
        foreach (['978030747472', '97803074747281', '97803074747ab'] as $bad) {
            try {
                new Isbn($bad);
                $this->fail("ISBN {$bad} should be rejected");
            } catch (DomainError $e) {
                $this->assertSame('VALIDATION_ERROR', $e->errorCode);
            }
        }
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $this->assertSame(1, (new Quantity(1))->value);
        $this->expectException(DomainError::class);
        new Quantity(0);
    }

    public function test_date_range_is_inclusive_in_utc(): void
    {
        $range = new DateRange(new DateTimeImmutable('2026-10-01T15:00:00Z'), new DateTimeImmutable('2026-10-31T01:00:00Z'));

        $this->assertSame('2026-10-01T00:00:00+00:00', $range->startsAt->format('c'));
        $this->assertSame('2026-11-01T00:00:00+00:00', $range->endsBefore->format('c'));
    }

    public function test_date_range_rejects_an_end_before_the_start(): void
    {
        try {
            new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-09'));
            $this->fail('An inverted range should be rejected');
        } catch (DomainError $e) {
            $this->assertSame('INVALID_DATE_RANGE', $e->errorCode);
        }
    }
}
