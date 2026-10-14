<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Exception\DomainError;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbBookRepository implements BookRepository
{
    private const COLUMNS = [
        'b.id', 'b.title', 'b.author', 'b.isbn', 'b.total_copies', 'b.available_copies', 'b.cover_key',
        'c.id as category_id', 'c.name as category_name',
    ];

    /** The single place where deactivated books are filtered out (ADR-003). */
    private function active(): Builder
    {
        return DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at');
    }

    private function shape(object $row): array
    {
        return [
            'id' => $row->id,
            'title' => $row->title,
            'author' => $row->author,
            'isbn' => $row->isbn,
            'category' => ['id' => $row->category_id, 'name' => $row->category_name],
            'total_copies' => (int) $row->total_copies,
            'available_copies' => (int) $row->available_copies,
            'cover_key' => $row->cover_key,
        ];
    }

    public function search(?string $q, ?string $categoryId, int $page, int $size): array
    {
        $query = $this->active();

        if ($q !== null) {
            $like = LikePattern::contains($q);
            $query->where(fn (Builder $w) => $w
                ->whereRaw('b.title ILIKE ? '.LikePattern::ESCAPE, [$like])
                ->orWhereRaw('b.author ILIKE ? '.LikePattern::ESCAPE, [$like]));
        }

        if ($categoryId !== null) {
            $query->where('b.category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('b.title')->orderBy('b.id')->forPage($page, $size)->get(self::COLUMNS);

        return ['items' => $rows->map(fn ($row) => $this->shape($row))->all(), 'total' => $total];
    }

    public function find(string $id): ?array
    {
        $row = $this->active()->where('b.id', $id)->first(self::COLUMNS);

        return $row === null ? null : $this->shape($row);
    }

    public function categoryExists(string $categoryId): bool
    {
        return DB::table('library.category')->where('id', $categoryId)->exists();
    }

    public function insert(string $id, string $title, string $author, string $isbn, string $categoryId, int $total): bool
    {
        try {
            // Own savepoint: a unique violation must not abort an outer PostgreSQL transaction.
            DB::transaction(fn () => DB::table('library.book')->insert([
                'id' => $id,
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'category_id' => $categoryId,
                'total_copies' => $total,
                'available_copies' => $total,
                'cover_key' => null,
                'deleted_at' => null,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function update(string $id, string $title, string $author, string $categoryId, int $total, int $available): void
    {
        try {
            DB::table('library.book')->where('id', $id)->update([
                'title' => $title,
                'author' => $author,
                'category_id' => $categoryId,
                'total_copies' => $total,
                'available_copies' => $available,
            ]);
        } catch (QueryException $e) {
            // The CHECK constraints are the last barrier (article VI); report them as a validation error.
            if (str_contains($e->getMessage(), 'ck_book_')) {
                throw DomainError::validation('El total no es compatible con los ejemplares prestados.', [['field' => 'total_copies', 'issue' => 'inválido']]);
            }

            throw $e;
        }
    }

    public function hasOpenLoans(string $id): bool
    {
        return DB::table('library.loan_item')->where('book_id', $id)->whereNull('returned_at')->exists();
    }

    public function softDelete(string $id, DateTimeImmutable $at): void
    {
        DB::table('library.book')->where('id', $id)->update(['deleted_at' => $at->format('Y-m-d H:i:sP')]);
    }

    public function setCoverKey(string $id, ?string $key): void
    {
        DB::table('library.book')->where('id', $id)->update(['cover_key' => $key]);
    }
}
