<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\LoanReader;
use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DbLoanReader implements LoanReader
{
    private const TS = 'Y-m-d H:i:sP';

    private static function iso(?string $timestamp): ?string
    {
        return $timestamp === null ? null : (new DateTimeImmutable($timestamp))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** open: unreturned and none overdue; overdue: some unreturned past due; closed: all returned (computed, Article VII). */
    private static function statusOf(array $items, DateTimeImmutable $now): string
    {
        $pending = array_filter($items, fn ($i) => $i['returned_at'] === null);

        if ($pending === []) {
            return 'closed';
        }

        foreach ($pending as $item) {
            if (new DateTimeImmutable($item['due_at']) < $now) {
                return 'overdue';
            }
        }

        return 'open';
    }

    /** @param list<string> $loanIds @return array<string, list<array>> */
    private function itemsByLoan(array $loanIds): array
    {
        $rows = DB::table('library.loan_item')
            ->whereIn('loan_id', $loanIds)
            ->orderBy('due_at')->orderBy('book_title')->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->loan_id][] = [
                'id' => $row->id,
                'book_id' => $row->book_id,
                'book_title' => $row->book_title,
                'isbn' => trim($row->isbn),
                'category_name' => $row->category_name,
                'due_at' => self::iso($row->due_at),
                'returned_at' => self::iso($row->returned_at),
            ];
        }

        return $grouped;
    }

    /** @param iterable<object> $loans @return list<array> */
    private function shape(iterable $loans, DateTimeImmutable $now): array
    {
        $loans = collect($loans)->values();
        $items = $this->itemsByLoan($loans->pluck('id')->all());

        return $loans->map(function ($loan) use ($items, $now) {
            $lines = $items[$loan->id] ?? [];

            return [
                'id' => $loan->id,
                'member_id' => $loan->member_id,
                'loaned_at' => self::iso($loan->loaned_at),
                'status' => self::statusOf($lines, $now),
                'items' => $lines,
            ];
        })->all();
    }

    public function find(string $loanId, DateTimeImmutable $now): ?array
    {
        $loan = DB::table('library.loan')->where('id', $loanId)->first();

        return $loan === null ? null : $this->shape([$loan], $now)[0];
    }

    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $endsBefore, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): array
    {
        $nowTs = $now->format(self::TS);
        $unreturned = fn (Builder $q) => $q->selectRaw('1')->from('library.loan_item as i')->whereColumn('i.loan_id', 'l.id')->whereNull('i.returned_at');
        $overdue = fn (Builder $q) => $unreturned($q)->where('i.due_at', '<', $nowTs);

        $query = DB::table('library.loan as l');

        if ($from !== null) {
            $query->where('l.loaned_at', '>=', $from->format(self::TS));
        }

        if ($endsBefore !== null) {
            $query->where('l.loaned_at', '<', $endsBefore->format(self::TS));
        }

        if ($memberId !== null) {
            $query->where('l.member_id', $memberId);
        }

        match ($status) {
            'open' => $query->whereExists($unreturned)->whereNotExists($overdue),
            'overdue' => $query->whereExists($overdue),
            'closed' => $query->whereNotExists($unreturned),
            default => null,
        };

        $total = (clone $query)->count();
        $loans = $query->orderByDesc('l.loaned_at')->orderBy('l.id')->forPage($page, $size)->get(['l.id', 'l.member_id', 'l.loaned_at']);

        return ['items' => $this->shape($loans, $now), 'total' => $total];
    }

    public function overdue(DateTimeImmutable $now, int $page, int $size): array
    {
        $query = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('i.returned_at')
            ->where('i.due_at', '<', $now->format(self::TS));

        $total = (clone $query)->count();
        $rows = $query->orderBy('i.due_at')->orderBy('i.id')->forPage($page, $size)
            ->get(['i.id as item_id', 'i.loan_id', 'i.book_title', 'm.full_name as member_name', 'i.due_at']);

        $items = $rows->map(function ($row) use ($now) {
            $due = new DateTimeImmutable($row->due_at);

            return [
                'loan_id' => $row->loan_id,
                'item_id' => $row->item_id,
                'book_title' => $row->book_title,
                'member_name' => $row->member_name,
                'due_at' => self::iso($row->due_at),
                // DT-13: whole days since the due date, at least 1 while overdue.
                'days_late' => max(1, intdiv($now->getTimestamp() - $due->getTimestamp(), 86400)),
            ];
        })->all();

        return ['items' => $items, 'total' => $total];
    }

    public function mostBorrowed(DateRange $range): array
    {
        $rows = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.loaned_at', '>=', $range->startsAt->format(self::TS))
            ->where('l.loaned_at', '<', $range->endsBefore->format(self::TS))
            ->groupBy('i.book_id', 'i.book_title', 'i.category_name')
            ->orderByDesc(DB::raw('count(*)'))->orderBy('i.book_title')->orderBy('i.category_name')
            ->get(['i.book_id', 'i.book_title', 'i.category_name', DB::raw('count(*) as times_borrowed')]);

        return $rows->map(fn ($r) => [
            'book_id' => $r->book_id,
            'book_title' => $r->book_title,
            'category_name' => $r->category_name,
            'times_borrowed' => (int) $r->times_borrowed,
        ])->all();
    }
}
