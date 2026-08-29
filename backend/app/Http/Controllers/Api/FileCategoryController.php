<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFileCategoryRequest;
use App\Models\AuditLog;
use App\Models\FileCategory;
use Illuminate\Http\JsonResponse;

class FileCategoryController extends Controller
{
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

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'file_category_created',
            'entity_type' => FileCategory::class,
            'entity_id' => $category->id,
            'after' => $category->toArray(),
        ]);

        return response()->json(['data' => $category], 201);
    }
}
