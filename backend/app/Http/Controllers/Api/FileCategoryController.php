<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFileCategoryRequest;
use App\Models\FileCategory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class FileCategoryController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
    }
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => FileCategory::orderBy('name')->get(),
        ]);
    }

    public function store(StoreFileCategoryRequest $request): JsonResponse
    {
        $this->authorize('manage-organization-data');

        $category = FileCategory::create($request->validated());

        $this->audit->record(
            $request->user(),
            'file_category_created',
            FileCategory::class,
            $category->id,
            null,
            $category
        );

        return response()->json(['data' => $category], 201);
    }
}
