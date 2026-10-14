<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Services\LendBooksService;
use App\Application\Services\LoanQueryService;
use App\Application\Services\ReturnItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController
{
    public function store(Request $request, LendBooksService $service): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array'],
            'items.*.book_id' => ['required', 'uuid'],
        ]);

        $bookIds = array_map(fn (array $item) => $item['book_id'], array_values($data['items']));

        return response()->json($service->lend($data['member_id'], $request->attributes->get('auth_user_id'), $bookIds), 201);
    }

    public function index(Request $request, LoanQueryService $service): JsonResponse
    {
        $request->validate(['member_id' => ['nullable', 'uuid']]);

        return response()->json($service->list(
            $request->query('from'),
            $request->query('to'),
            $request->query('status'),
            $request->query('member_id'),
            $request->query('page'),
            $request->query('size'),
        ));
    }

    public function show(string $id, LoanQueryService $service): JsonResponse
    {
        return response()->json($service->get($id));
    }

    public function overdue(Request $request, LoanQueryService $service): JsonResponse
    {
        return response()->json($service->overdue($request->query('page'), $request->query('size')));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return response()->json($service->return($loanId, $itemId));
    }
}
