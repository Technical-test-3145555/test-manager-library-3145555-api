<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Services\CreateLibrarianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    public function store(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string'],
        ]);

        return response()->json($service->create($data['username'], $data['password'], $data['role']), 201);
    }
}
