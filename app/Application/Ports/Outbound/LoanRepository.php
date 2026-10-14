<?php

namespace App\Application\Ports\Outbound;

use App\Domain\Loan;
use DateTimeImmutable;

interface LoanRepository
{
    /** @return array{pending: int, overdue: int} unreturned lines of the member and how many are overdue (Q7) */
    public function openStats(string $memberId, DateTimeImmutable $now): array;

    /**
     * Atomic, conditional copy withdrawal (ADR-002). False when no copy was left.
     */
    public function withdrawCopy(string $bookId): bool;

    public function insert(Loan $loan): void;

    /**
     * Marks the line returned and restocks its book, once (CA-07.1, CA-07.2).
     *
     * @return 'returned'|'already_returned'|'missing'
     */
    public function returnItem(string $loanId, string $itemId, DateTimeImmutable $at): string;
}
