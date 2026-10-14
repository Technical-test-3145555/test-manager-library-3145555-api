<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController
{
    public function index(Request $request, MemberService $service): JsonResponse
    {
        return response()->json($service->search($request->query('q'), $request->query('page'), $request->query('size')));
    }

    public function show(string $id, MemberService $service): JsonResponse
    {
        return response()->json($service->get($id));
    }

    public function store(Request $request, MemberService $service): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string'],
            'document_number' => ['required', 'string'],
        ]);

        return response()->json($service->create($data['full_name'], $data['document_number']), 201);
    }
}
