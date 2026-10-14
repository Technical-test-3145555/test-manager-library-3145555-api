<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\MemberRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbMemberRepository implements MemberRepository
{
    public function create(string $id, string $fullName, string $documentNumber): bool
    {
        try {
            DB::transaction(fn () => DB::table('library.member')->insert(['id' => $id, 'full_name' => $fullName, 'document_number' => $documentNumber]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function find(string $id): ?array
    {
        $row = DB::table('library.member')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $active = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $id)
            ->whereNull('i.returned_at')
            ->count();

        return [
            'id' => $row->id,
            'full_name' => $row->full_name,
            'document_number' => $row->document_number,
            'active_loans' => $active,
        ];
    }

    public function search(?string $q, int $page, int $size): array
    {
        $query = DB::table('library.member');

        if ($q !== null) {
            $like = LikePattern::contains($q);
            $query->where(fn (Builder $w) => $w
                ->whereRaw('full_name ILIKE ? '.LikePattern::ESCAPE, [$like])
                ->orWhereRaw('document_number ILIKE ? '.LikePattern::ESCAPE, [$like]));
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->forPage($page, $size)->get(['id', 'full_name', 'document_number']);

        return [
            'items' => $rows->map(fn ($r) => ['id' => $r->id, 'full_name' => $r->full_name, 'document_number' => $r->document_number])->all(),
            'total' => $total,
        ];
    }
}
