<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\FileIssue;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->when($request->filled('actor_user_id'), fn ($query) => $query->where('actor_user_id', $request->input('actor_user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->input('entity_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AuditLog $log) => $this->serializeLog($log))->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function fileAuditLogs(File $file, Request $request): JsonResponse
    {
        $transferIds = $file->transfers()->pluck('id');
        $issueIds = $file->issues()->pluck('id');

        $logs = AuditLog::query()
            ->with('actor')
            ->where(function ($query) use ($file, $transferIds, $issueIds) {
                $query->where(function ($q) use ($file) {
                    $q->where('entity_type', File::class)
                        ->where('entity_id', $file->id);
                });

                if ($transferIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($transferIds) {
                        $q->where('entity_type', Transfer::class)
                            ->whereIn('entity_id', $transferIds);
                    });
                }

                if ($issueIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($issueIds) {
                        $q->where('entity_type', FileIssue::class)
                            ->whereIn('entity_id', $issueIds);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AuditLog $log) => $this->serializeLog($log))->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->input('per_page', 15), 100));
    }

    private function serializeLog(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->email,
            ] : null,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'before' => $log->before,
            'after' => $log->after,
            'created_at' => optional($log->created_at)->toISOString(),
            'updated_at' => optional($log->updated_at)->toISOString(),
        ];
    }
}
