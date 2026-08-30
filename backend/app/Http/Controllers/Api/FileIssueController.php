<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexIssueRequest;
use App\Http\Requests\StoreFileIssueRequest;
use App\Http\Requests\UpdateFileIssueRequest;
use App\Models\File;
use App\Models\FileIssue;
use App\Services\FileIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FileIssueController extends Controller
{
    public function __construct(private FileIssueService $issues)
    {
    }

    public function store(StoreFileIssueRequest $request, File $file): JsonResponse
    {
        try {
            $issue = $this->issues->create($request->user(), $file, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serializeIssue($issue->load($this->relations())),
        ], 201);
    }

    public function show(FileIssue $issue): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeIssue($issue->load($this->relations())),
        ]);
    }

    public function index(File $file, Request $request): JsonResponse
    {
        $issues = $file->issues()
            ->with($this->relations())
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $issues->getCollection()->map(fn (FileIssue $issue) => $this->serializeIssue($issue))->values(),
            'meta' => [
                'current_page' => $issues->currentPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'last_page' => $issues->lastPage(),
            ],
        ]);
    }

    public function indexAll(IndexIssueRequest $request): JsonResponse
    {
        $issues = FileIssue::query()
            ->with($this->relations())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->whereHas('file', function ($fileQuery) use ($search) {
                    $fileQuery->where('file_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $issues->getCollection()->map(fn (FileIssue $issue) => $this->serializeIssue($issue))->values(),
            'meta' => [
                'current_page' => $issues->currentPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'last_page' => $issues->lastPage(),
            ],
        ]);
    }

    public function update(UpdateFileIssueRequest $request, FileIssue $issue): JsonResponse
    {
        $this->authorize('manage-issues');

        try {
            $issue = $this->issues->updateStatus($request->user(), $issue, $request->input('status'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serializeIssue($issue->load($this->relations())),
        ]);
    }

    private function relations(): array
    {
        return [
            'file',
            'reportedBy',
            'resolvedBy',
        ];
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->input('per_page', 15), 100));
    }

    private function serializeIssue(FileIssue $issue): array
    {
        return [
            'id' => $issue->id,
            'file' => $issue->file ? [
                'id' => $issue->file->id,
                'file_number' => $issue->file->file_number,
                'title' => $issue->file->title,
            ] : null,
            'issue_type' => $issue->issue_type,
            'description' => $issue->description,
            'status' => $issue->status,
            'reported_by' => $this->user($issue->reportedBy),
            'resolved_by' => $this->user($issue->resolvedBy),
            'resolved_at' => optional($issue->resolved_at)->toISOString(),
            'created_at' => optional($issue->created_at)->toISOString(),
            'updated_at' => optional($issue->updated_at)->toISOString(),
        ];
    }

    private function user($user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;
    }
}
