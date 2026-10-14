<?php

use App\Adapters\Inbound\Http\Controllers\AuthController;
use App\Adapters\Inbound\Http\Controllers\BookController;
use App\Adapters\Inbound\Http\Controllers\CategoryController;
use App\Adapters\Inbound\Http\Controllers\LoanController;
use App\Adapters\Inbound\Http\Controllers\MemberController;
use App\Adapters\Inbound\Http\Controllers\ReportController;
use App\Adapters\Inbound\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.token')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);

    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show'])->whereUuid('id');
    Route::get('/books/{id}/cover', [BookController::class, 'cover'])->whereUuid('id');

    Route::middleware('role:admin,librarian')->group(function () {
        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        // Fixed route before the variable one, which is also restricted to UUIDs (DT-14).
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
        Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem'])->whereUuid(['loan_id', 'item_id']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show'])->whereUuid('id');
        Route::post('/members', [MemberController::class, 'store']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);

        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update'])->whereUuid('id');
        Route::delete('/books/{id}', [BookController::class, 'destroy'])->whereUuid('id');
        Route::put('/books/{id}/cover', [BookController::class, 'uploadCover'])->whereUuid('id');
        Route::delete('/books/{id}/cover', [BookController::class, 'removeCover'])->whereUuid('id');
    });
});
