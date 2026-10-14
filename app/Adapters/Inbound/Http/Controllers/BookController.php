<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\MultipartFile;
use App\Application\Services\CoverService;
use App\Application\Services\CreateBookService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use App\Domain\Exception\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BookController
{
    private const RULES = [
        'title' => ['required', 'string'],
        'author' => ['required', 'string'],
        'category_id' => ['required', 'string'],
        'total_copies' => ['required', 'integer'],
    ];

    public function index(Request $request, SearchBooksService $service): JsonResponse
    {
        return response()->json($service->search(
            $request->query('q'),
            $request->query('category_id'),
            $request->query('page'),
            $request->query('size'),
        ));
    }

    public function show(string $id, SearchBooksService $service): JsonResponse
    {
        return response()->json($service->get($id));
    }

    public function store(Request $request, CreateBookService $service): JsonResponse
    {
        $data = $request->validate(self::RULES + ['isbn' => ['required', 'string']]);

        return response()->json($service->create($data['title'], $data['author'], $data['isbn'], $data['category_id'], $data['total_copies']), 201);
    }

    public function update(string $id, Request $request, UpdateBookService $service): JsonResponse
    {
        $data = $request->validate(self::RULES);

        return response()->json($service->update($id, $data['title'], $data['author'], $data['category_id'], $data['total_copies']));
    }

    public function destroy(string $id, DeleteBookService $service): Response
    {
        $service->delete($id);

        return response()->noContent();
    }

    public function uploadCover(string $id, Request $request, CoverService $service): JsonResponse
    {
        $contents = MultipartFile::contents($request, 'file');

        if ($contents === null || $contents === '') {
            throw DomainError::validation('Falta el archivo de portada o no se pudo recibir.', [['field' => 'file', 'issue' => 'obligatorio']]);
        }

        return response()->json($service->upload($id, $contents));
    }

    public function removeCover(string $id, CoverService $service): Response
    {
        $service->remove($id);

        return response()->noContent();
    }

    public function cover(string $id, CoverService $service): Response
    {
        $cover = $service->get($id);

        return response($cover['contents'], 200, ['Content-Type' => $cover['mime']]);
    }
}
