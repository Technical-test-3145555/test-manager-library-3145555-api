<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Exception\DomainError;
use Ramsey\Uuid\Uuid;

/** HU-05: members are data, not users; they are created and read, never deleted (CA-05.3). */
final class MemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function create(string $fullName, string $documentNumber): array
    {
        $fullName = trim($fullName);
        $documentNumber = trim($documentNumber);

        if ($fullName === '' || mb_strlen($fullName) > 200) {
            throw DomainError::validation('El nombre es obligatorio y admite hasta 200 caracteres.', [['field' => 'full_name', 'issue' => 'inválido']]);
        }

        if ($documentNumber === '' || mb_strlen($documentNumber) > 30) {
            throw DomainError::validation('El documento es obligatorio y admite hasta 30 caracteres.', [['field' => 'document_number', 'issue' => 'inválido']]);
        }

        $id = Uuid::uuid4()->toString();

        if (! $this->members->create($id, $fullName, $documentNumber)) {
            throw DomainError::documentTaken();
        }

        return ['id' => $id, 'full_name' => $fullName, 'document_number' => $documentNumber];
    }

    public function get(string $id): array
    {
        return $this->members->find($id) ?? throw DomainError::notFound('El socio');
    }

    /** @return array{items: list<array>, page: int, size: int, total: int} */
    public function search(?string $q, mixed $page, mixed $size): array
    {
        [$page, $size] = Pagination::parse($page, $size);
        $result = $this->members->search($q === null || trim($q) === '' ? null : trim($q), $page, $size);

        return ['items' => $result['items'], 'page' => $page, 'size' => $size, 'total' => $result['total']];
    }
}
