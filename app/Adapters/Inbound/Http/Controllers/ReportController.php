<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Services\MostBorrowedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        return response()->json($service->report($request->query('from'), $request->query('to')));
    }
}
