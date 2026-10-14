<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Ports\Outbound\UnitOfWork;
use App\Domain\Book;
use App\Domain\Exception\DomainError;
use App\Domain\Loan;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use Ramsey\Uuid\Uuid;

/**
 * HU-06. Validation order (DT-07): input, member exists, overdue, limit, books and copies.
 * The loan is all or nothing: if any copy is gone nothing is registered (CA-06.3).
 */
final class LendBooksService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly LoanRepository $loans,
        private readonly LoanReader $reader,
        private readonly UnitOfWork $transaction,
        private readonly Clock $clock,
    ) {
    }

    /** @param list<string> $bookIds */
    public function lend(string $memberId, string $userId, array $bookIds): array
    {
        $this->assertItems($bookIds);

        $this->members->find($memberId) ?? throw DomainError::notFound('El socio');

        $now = $this->clock->now();
        $stats = $this->loans->openStats($memberId, $now);

        if ($stats['overdue'] > 0) {
            throw DomainError::memberHasOverdue();
        }

        if ($stats['pending'] + count($bookIds) > Loan::MAX_ITEMS) {
            throw DomainError::memberLimitExceeded($stats['pending']);
        }

        $rows = [];
        foreach ($bookIds as $bookId) {
            $rows[$bookId] = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
        }

        $loanId = Uuid::uuid4()->toString();

        $this->transaction->run(function () use ($loanId, $memberId, $userId, $now, $rows) {
            $loan = new Loan($loanId, $memberId, $userId, $now);

            foreach ($rows as $bookId => $row) {
                $book = new Book($bookId, $row['title'], $row['author'], new Isbn($row['isbn']), $row['category']['id'], new Quantity($row['total_copies']), $row['available_copies']);

                // The domain takes the copy and builds the frozen line (RN-4); the repository enforces it atomically (ADR-002).
                $loan->addItem(Uuid::uuid4()->toString(), $book, $row['category']['name']);

                if (! $this->loans->withdrawCopy($bookId)) {
                    throw DomainError::bookNotAvailable($row['title']);
                }
            }

            $this->loans->insert($loan);
        });

        return $this->reader->find($loanId, $now);
    }

    /** @param list<string> $bookIds */
    private function assertItems(array $bookIds): void
    {
        if (count($bookIds) < 1 || count($bookIds) > Loan::MAX_ITEMS) {
            throw DomainError::validation('Un préstamo lleva de 1 a 3 libros.', [['field' => 'items', 'issue' => 'debe tener de 1 a 3 elementos']]);
        }

        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw DomainError::duplicateBookInLoan();
        }
    }
}
