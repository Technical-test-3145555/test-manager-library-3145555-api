<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DbLoanRepository implements LoanRepository
{
    private const TS = 'Y-m-d H:i:sP';

    public function openStats(string $memberId, DateTimeImmutable $now): array
    {
        $unreturned = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('i.returned_at');

        return [
            'pending' => (clone $unreturned)->count(),
            'overdue' => (clone $unreturned)->where('i.due_at', '<', $now->format(self::TS))->count(),
        ];
    }

    /**
     * ADR-002: one atomic conditional statement. Two clerks lending the last copy at once:
     * one updates a row, the other updates none and gets BOOK_NOT_AVAILABLE.
     */
    public function withdrawCopy(string $bookId): bool
    {
        return DB::update(
            'UPDATE library.book SET available_copies = available_copies - 1 WHERE id = ? AND available_copies > 0 AND deleted_at IS NULL',
            [$bookId],
        ) === 1;
    }

    public function insert(Loan $loan): void
    {
        DB::table('library.loan')->insert([
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_by_user_id' => $loan->loanedByUserId,
            'loaned_at' => $loan->loanedAt->format(self::TS),
        ]);

        foreach ($loan->items() as $item) {
            DB::table('library.loan_item')->insert([
                'id' => $item->id,
                'loan_id' => $loan->id,
                'book_id' => $item->bookId,
                'book_title' => $item->bookTitle,
                'isbn' => $item->isbn,
                'category_name' => $item->categoryName,
                'due_at' => $item->dueAt->format(self::TS),
                'returned_at' => null,
            ]);
        }
    }

    public function returnItem(string $loanId, string $itemId, DateTimeImmutable $at): string
    {
        $item = DB::table('library.loan_item')->where('id', $itemId)->where('loan_id', $loanId)->lockForUpdate()->first();

        if ($item === null) {
            return 'missing';
        }

        if ($item->returned_at !== null) {
            return 'already_returned';
        }

        DB::table('library.loan_item')->where('id', $itemId)->update(['returned_at' => $at->format(self::TS)]);

        // Never above the total (CA-07.3): the CHECK is the last barrier, the condition keeps it from firing.
        DB::update('UPDATE library.book SET available_copies = available_copies + 1 WHERE id = ? AND available_copies < total_copies', [$item->book_id]);

        return 'returned';
    }
}
