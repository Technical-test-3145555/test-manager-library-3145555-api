<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\LoanReader;
use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\DateRange;

/**
 * HU-10: one aggregated query over the frozen values of the loan lines (ADR-004).
 * No members and no librarians appear in the result (privacy).
 */
final class MostBorrowedReportService
{
    public function __construct(private readonly LoanReader $reader)
    {
    }

    /** @return array{from: string, to: string, rows: list<array>} */
    public function report(?string $from, ?string $to): array
    {
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value === null || $value === '') {
                throw DomainError::validation("{$field} es obligatorio.", [['field' => $field, 'issue' => 'obligatorio']]);
            }
        }

        $range = new DateRange(LoanQueryService::date($from, 'from'), LoanQueryService::date($to, 'to'));

        return ['from' => $from, 'to' => $to, 'rows' => $this->reader->mostBorrowed($range)];
    }
}
