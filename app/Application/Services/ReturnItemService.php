<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\UnitOfWork;
use App\Domain\Exception\DomainError;

/** HU-07: one line at a time; returning twice never restocks twice. */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly LoanReader $reader,
        private readonly UnitOfWork $transaction,
        private readonly Clock $clock,
    ) {
    }

    /** @return array the returned line */
    public function return(string $loanId, string $itemId): array
    {
        $now = $this->clock->now();

        $result = $this->transaction->run(fn () => $this->loans->returnItem($loanId, $itemId, $now));

        match ($result) {
            'missing' => throw DomainError::notFound('La línea del préstamo'),
            'already_returned' => throw DomainError::itemAlreadyReturned(),
            default => null,
        };

        foreach ($this->reader->find($loanId, $now)['items'] as $item) {
            if ($item['id'] === $itemId) {
                return $item;
            }
        }

        throw DomainError::notFound('La línea del préstamo');
    }
}
