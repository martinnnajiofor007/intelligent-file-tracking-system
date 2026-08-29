<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexNotificationRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(IndexNotificationRequest $request): JsonResponse
    {
        $query = $request->user()->notifications();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (Notification $n) => $this->serialize($n))->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $this->assertOwned($request, $notification);

        return response()->json([
            'data' => $this->serialize($notification),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->assertOwned($request, $notification);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'data' => $this->serialize($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['updated' => $updated],
        ]);
    }

    private function assertOwned(Request $request, Notification $notification): void
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(404);
        }
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->input('per_page', 15), 100));
    }

    private function serialize(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'related_type' => $notification->related_type,
            'related_id' => $notification->related_id,
            'metadata' => $notification->metadata,
            'read_at' => optional($notification->read_at)->toISOString(),
            'is_read' => $notification->isRead(),
            'created_at' => optional($notification->created_at)->toISOString(),
            'updated_at' => optional($notification->updated_at)->toISOString(),
        ];
    }
}
