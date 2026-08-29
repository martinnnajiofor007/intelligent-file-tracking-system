<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFileRequest;
use App\Models\File;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
    }
    public function index(Request $request): JsonResponse
    {
        $files = File::query()
            ->with(['category', 'confirmedDepartment', 'confirmedHolder', 'registeredBy'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($query) use ($search) {
                    $query->where('file_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('confirmed_department_id', $request->input('department_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('registered_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $files->getCollection()->map(fn (File $file) => $this->serializeFile($file))->values(),
            'meta' => [
                'current_page' => $files->currentPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
                'last_page' => $files->lastPage(),
            ],
        ]);
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        $this->authorize('register-files');

        $file = File::create(array_merge($request->validated(), [
            'status' => File::STATUS_ACTIVE,
            'registered_by_user_id' => $request->user()->id,
            'registered_at' => now(),
        ]))->load(['category', 'confirmedDepartment', 'confirmedHolder', 'registeredBy']);

        $this->audit->record(
            $request->user(),
            'file_registered',
            File::class,
            $file->id,
            null,
            $file
        );

        return response()->json([
            'data' => $this->serializeFile($file),
        ], 201);
    }

    public function show(File $file): JsonResponse
    {
        $file->load(['category', 'confirmedDepartment', 'confirmedHolder', 'registeredBy', 'pendingTransfer.toDepartment', 'pendingTransfer.toHolder']);

        return response()->json([
            'data' => $this->serializeFile($file),
        ]);
    }

    private function serializeFile(File $file): array
    {
        return [
            'id' => $file->id,
            'file_number' => $file->file_number,
            'title' => $file->title,
            'description' => $file->description,
            'category' => $file->category ? [
                'id' => $file->category->id,
                'name' => $file->category->name,
                'default_due_days' => $file->category->default_due_days,
            ] : null,
            'status' => $file->status,
            'confirmed_department' => $file->confirmedDepartment ? [
                'id' => $file->confirmedDepartment->id,
                'name' => $file->confirmedDepartment->name,
            ] : null,
            'confirmed_holder' => $file->confirmedHolder ? [
                'id' => $file->confirmedHolder->id,
                'name' => $file->confirmedHolder->name,
                'email' => $file->confirmedHolder->email,
            ] : null,
            'registered_by' => $file->registeredBy ? [
                'id' => $file->registeredBy->id,
                'name' => $file->registeredBy->name,
                'email' => $file->registeredBy->email,
            ] : null,
            'registered_at' => optional($file->registered_at)->toISOString(),
            'created_at' => optional($file->created_at)->toISOString(),
            'updated_at' => optional($file->updated_at)->toISOString(),
            'pending_transfer' => $file->pendingTransfer ? [
                'id' => $file->pendingTransfer->id,
                'status' => $file->pendingTransfer->status,
                'intended_department' => $file->pendingTransfer->toDepartment ? [
                    'id' => $file->pendingTransfer->toDepartment->id,
                    'name' => $file->pendingTransfer->toDepartment->name,
                ] : null,
                'intended_holder' => $file->pendingTransfer->toHolder ? [
                    'id' => $file->pendingTransfer->toHolder->id,
                    'name' => $file->pendingTransfer->toHolder->name,
                    'email' => $file->pendingTransfer->toHolder->email,
                ] : null,
                'due_at' => optional($file->pendingTransfer->due_at)->toISOString(),
                'is_overdue' => $file->pendingTransfer->isOverdue(),
            ] : null,
        ];
    }
}
