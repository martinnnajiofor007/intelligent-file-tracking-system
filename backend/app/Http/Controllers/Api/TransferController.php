<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTransferRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\File;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TransferController extends Controller
{
    public function __construct(private TransferService $transfers)
    {
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $this->authorize('create-transfers');

        $file = File::findOrFail($request->input('file_id'));

        try {
            $transfer = $this->transfers->create($request->user(), $file, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serializeTransfer($transfer->load($this->relations())),
        ], 201);
    }

    public function show(Transfer $transfer): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeTransfer($transfer->load($this->relations())),
        ]);
    }

    public function index(File $file): JsonResponse
    {
        $transfers = $file->transfers()
            ->with($this->relations())
            ->orderByDesc('requested_at')
            ->get();

        return response()->json([
            'data' => $transfers->map(fn (Transfer $transfer) => $this->serializeTransfer($transfer))->values(),
        ]);
    }

    public function overdue(Request $request): JsonResponse
    {
        $transfers = Transfer::query()
            ->overdue()
            ->with($this->relations())
            ->orderByDesc('due_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $transfers->getCollection()->map(fn (Transfer $transfer) => $this->serializeTransfer($transfer))->values(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
                'last_page' => $transfers->lastPage(),
            ],
        ]);
    }

    public function indexAll(IndexTransferRequest $request): JsonResponse
    {
        $transfers = Transfer::query()
            ->with($this->relations())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('overdue'), function ($query) use ($request) {
                if ($request->boolean('overdue')) {
                    $query->overdue();
                }
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->whereHas('file', function ($fileQuery) use ($search) {
                    $fileQuery->where('file_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('requested_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $transfers->getCollection()->map(fn (Transfer $transfer) => $this->serializeTransfer($transfer))->values(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
                'last_page' => $transfers->lastPage(),
            ],
        ]);
    }

    public function acknowledge(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $this->transfers->canActOn($request->user(), $transfer)) {
            return response()->json([
                'message' => 'You are not authorized to acknowledge this transfer.',
            ], 403);
        }

        try {
            $transfer = $this->transfers->acknowledge($request->user(), $transfer);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serializeTransfer($transfer->load($this->relations())),
        ]);
    }

    public function reject(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $this->transfers->canActOn($request->user(), $transfer)) {
            return response()->json([
                'message' => 'You are not authorized to reject this transfer.',
            ], 403);
        }

        try {
            $transfer = $this->transfers->reject($request->user(), $transfer);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serializeTransfer($transfer->load($this->relations())),
        ]);
    }

    private function relations(): array
    {
        return [
            'file',
            'fromDepartment',
            'fromHolder',
            'toDepartment',
            'toHolder',
            'requestedBy',
            'acknowledgedBy',
            'rejectedBy',
        ];
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->input('per_page', 15), 100));
    }

    private function serializeTransfer(Transfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'file_id' => $transfer->file_id,
            'from_department' => $this->department($transfer->fromDepartment),
            'from_holder' => $this->user($transfer->fromHolder),
            'to_department' => $this->department($transfer->toDepartment),
            'to_holder' => $this->user($transfer->toHolder),
            'requested_by' => $this->user($transfer->requestedBy),
            'requested_at' => optional($transfer->requested_at)->toISOString(),
            'status' => $transfer->status,
            'acknowledged_by' => $this->user($transfer->acknowledgedBy),
            'acknowledged_at' => optional($transfer->acknowledged_at)->toISOString(),
            'rejected_by' => $this->user($transfer->rejectedBy),
            'rejected_at' => optional($transfer->rejected_at)->toISOString(),
            'due_at' => optional($transfer->due_at)->toISOString(),
            'is_overdue' => $transfer->isOverdue(),
            'created_at' => optional($transfer->created_at)->toISOString(),
            'updated_at' => optional($transfer->updated_at)->toISOString(),
        ];
    }

    private function department($department): ?array
    {
        return $department ? [
            'id' => $department->id,
            'name' => $department->name,
        ] : null;
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
