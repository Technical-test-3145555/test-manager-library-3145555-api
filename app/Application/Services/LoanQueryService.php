<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanReader;
use App\Domain\Exception\DomainError;
use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;
use DateTimeZone;

/** HU-08 and HU-09: reads. The status is computed, never stored (CA-08.3). */
final class LoanQueryService
{
    private const STATUSES = ['open', 'overdue', 'closed'];

    public function __construct(private readonly LoanReader $reader, private readonly Clock $clock)
    {
    }

    public function get(string $loanId): array
    {
        return $this->reader->find($loanId, $this->clock->now()) ?? throw DomainError::notFound('El préstamo');
    }

    /** @return array{items: list<array>, page: int, size: int, total: int} */
    public function list(?string $from, ?string $to, ?string $status, ?string $memberId, mixed $page, mixed $size): array
    {
        [$page, $size] = Pagination::parse($page, $size);

        if ($status !== null && ! in_array($status, self::STATUSES, true)) {
            throw DomainError::validation('status debe ser open, overdue o closed.', [['field' => 'status', 'issue' => 'valor no admitido']]);
        }

        $start = $from === null ? null : self::date($from, 'from');
        $end = $to === null ? null : self::date($to, 'to');

        if ($start !== null && $end !== null) {
            $range = new DateRange($start, $end);
            $start = $range->startsAt;
            $endsBefore = $range->endsBefore;
        } else {
            $endsBefore = $end?->modify('+1 day');
        }

        $result = $this->reader->list($start, $endsBefore, $status, $memberId, $this->clock->now(), $page, $size);

        return ['items' => $result['items'], 'page' => $page, 'size' => $size, 'total' => $result['total']];
    }

    /** @return array{items: list<array>, page: int, size: int, total: int} */
    public function overdue(mixed $page, mixed $size): array
    {
        [$page, $size] = Pagination::parse($page, $size);
        $result = $this->reader->overdue($this->clock->now(), $page, $size);

        return ['items' => $result['items'], 'page' => $page, 'size' => $size, 'total' => $result['total']];
    }

    public static function date(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw DomainError::validation("{$field} debe ser una fecha con formato AAAA-MM-DD.", [['field' => $field, 'issue' => 'formato inválido']]);
        }

        return $date;
    }
}
