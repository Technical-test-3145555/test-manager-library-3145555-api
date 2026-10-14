<?php

namespace App\Application\Ports\Outbound;

use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;

/** Read side of loans and the report. Dates come back as ISO 8601 UTC strings with `Z`. */
interface LoanReader
{
    /** @return array|null loan with its lines and computed status (CA-08.1) */
    public function find(string $loanId, DateTimeImmutable $now): ?array;

    /**
     * @param 'open'|'overdue'|'closed'|null $status
     * @return array{items: list<array>, total: int} newest first (CA-08.2)
     */
    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $endsBefore, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): array;

    /** @return array{items: list<array>, total: int} most days late first (CA-09.2) */
    public function overdue(DateTimeImmutable $now, int $page, int $size): array;

    /** @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}> (CA-10.1) */
    public function mostBorrowed(DateRange $range): array;
}
