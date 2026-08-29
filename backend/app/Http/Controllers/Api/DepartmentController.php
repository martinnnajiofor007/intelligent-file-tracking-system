<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Models\Department;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
    }
    public function index(): JsonResponse
    {
        $departments = Department::with('parent')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->serializeDepartment($department));

        return response()->json(['data' => $departments]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('manage-organization-data');

        $department = Department::create($request->validated())->load('parent');

        $this->audit->record(
            $request->user(),
            'department_created',
            Department::class,
            $department->id,
            null,
            $department
        );

        return response()->json([
            'data' => $this->serializeDepartment($department),
        ], 201);
    }

    private function serializeDepartment(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'parent' => $department->parent ? [
                'id' => $department->parent->id,
                'name' => $department->parent->name,
            ] : null,
            'created_at' => optional($department->created_at)->toISOString(),
            'updated_at' => optional($department->updated_at)->toISOString(),
        ];
    }
}
