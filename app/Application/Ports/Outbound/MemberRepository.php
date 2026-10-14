<?php

namespace App\Application\Ports\Outbound;

interface MemberRepository
{
    /** Returns false when the document already exists (CA-05.1). */
    public function create(string $id, string $fullName, string $documentNumber): bool;

    /** @return array{id: string, full_name: string, document_number: string, active_loans: int}|null */
    public function find(string $id): ?array;

    /** @return array{items: list<array{id: string, full_name: string, document_number: string}>, total: int} */
    public function search(?string $q, int $page, int $size): array;
}
