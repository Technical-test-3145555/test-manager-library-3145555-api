<?php

namespace App\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class CategoryController
{
    public function index(): JsonResponse
    {
        $rows = DB::table('library.category')->orderBy('name')->get(['id', 'name']);

        return response()->json($rows->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])->all());
    }
}
