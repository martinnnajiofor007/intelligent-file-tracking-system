<?php

namespace App\Services;

use App\Models\File;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferService
{
    public function __construct(
        private AuditLogService $audit,
        private NotificationService $notifications
    ) {
    }
    /**
     * Create a pending transfer. Confirmed custody is never modified here.
     *
     * The file row is locked for update so concurrent requests cannot create
     * two pending transfers for the same file.
     */
    public function create(User $actor, File $file, array $data): Transfer
    {
        return DB::transaction(function () use ($actor, $file, $data) {
            $lockedFile = File::whereKey($file->id)->lockForUpdate()->first();

            if ($lockedFile->confirmed_department_id === null || $lockedFile->confirmed_holder_user_id === null) {
                throw new InvalidArgumentException('A file must have a confirmed custodian before it can be transferred.');
            }

            if ($lockedFile->pendingTransfer()->exists()) {
                throw new InvalidArgumentException('This file already has a pending transfer.');
            }

            if ((int) $lockedFile->confirmed_department_id === (int) $data['to_department_id']
                && (int) $lockedFile->confirmed_holder_user_id === (int) $data['to_holder_user_id']) {
                throw new InvalidArgumentException('The destination is the same as the current confirmed custodian.');
            }

            $dueAt = $lockedFile->category?->default_due_days
                ? now()->addDays($lockedFile->category->default_due_days)
                : null;

            $transfer = Transfer::create([
                'file_id' => $lockedFile->id,
                'from_department_id' => $lockedFile->confirmed_department_id,
                'from_holder_user_id' => $lockedFile->confirmed_holder_user_id,
                'to_department_id' => $data['to_department_id'],
                'to_holder_user_id' => $data['to_holder_user_id'],
                'requested_by_user_id' => $actor->id,
                'requested_at' => now(),
                'status' => Transfer::STATUS_PENDING,
                'due_at' => $dueAt,
            ]);

            $this->audit->record(
                $actor,
                'transfer_created',
                Transfer::class,
                $transfer->id,
                null,
                $transfer
            );

            $this->notifications->notifyTransferCreated($transfer);

            return $transfer;
        });
    }

    /**
     * Acknowledge a pending transfer and, only then, move confirmed custody.
     *
     * The status transition is a conditional UPDATE (WHERE status = pending),
     * so only one concurrent terminal transition can succeed.
     */
    public function acknowledge(User $actor, Transfer $transfer): Transfer
    {
        if (! $this->canActOn($actor, $transfer)) {
            throw new InvalidArgumentException('You are not authorized to acknowledge this transfer.');
        }

        $succeeded = DB::transaction(function () use ($actor, $transfer) {
            $affected = Transfer::where('id', $transfer->id)
                ->where('status', Transfer::STATUS_PENDING)
                ->update([
                    'status' => Transfer::STATUS_ACKNOWLEDGED,
                    'acknowledged_by_user_id' => $actor->id,
                    'acknowledged_at' => now(),
                ]);

            if ($affected === 0) {
                return false;
            }

            $transfer->file()->update([
                'confirmed_department_id' => $transfer->to_department_id,
                'confirmed_holder_user_id' => $transfer->to_holder_user_id,
            ]);

            $this->audit->record(
                $actor,
                'transfer_acknowledged',
                Transfer::class,
                $transfer->id,
                ['status' => Transfer::STATUS_PENDING],
                $transfer->fresh()
            );

            $this->notifications->notifyTransferAcknowledged($transfer->fresh());

            return true;
        });

        if (! $succeeded) {
            throw new InvalidArgumentException('Only pending transfers can be acknowledged.');
        }

        return $transfer->fresh();
    }

    /**
     * Reject a pending transfer. Confirmed custody is never modified.
     *
     * The status transition is a conditional UPDATE (WHERE status = pending),
     * so only one concurrent terminal transition can succeed.
     */
    public function reject(User $actor, Transfer $transfer): Transfer
    {
        if (! $this->canActOn($actor, $transfer)) {
            throw new InvalidArgumentException('You are not authorized to reject this transfer.');
        }

        $succeeded = DB::transaction(function () use ($actor, $transfer) {
            $affected = Transfer::where('id', $transfer->id)
                ->where('status', Transfer::STATUS_PENDING)
                ->update([
                    'status' => Transfer::STATUS_REJECTED,
                    'rejected_by_user_id' => $actor->id,
                    'rejected_at' => now(),
                ]);

            if ($affected === 0) {
                return false;
            }

            $this->audit->record(
                $actor,
                'transfer_rejected',
                Transfer::class,
                $transfer->id,
                ['status' => Transfer::STATUS_PENDING],
                $transfer->fresh()
            );

            $this->notifications->notifyTransferRejected($transfer->fresh());

            return true;
        });

        if (! $succeeded) {
            throw new InvalidArgumentException('Only pending transfers can be rejected.');
        }

        return $transfer->fresh();
    }

    /**
     * The intended recipient, an admin, or a supervisor may act on a transfer.
     */
    public function canActOn(User $actor, Transfer $transfer): bool
    {
        return $actor->id === $transfer->to_holder_user_id
            || $actor->isAdmin()
            || $actor->isSupervisor();
    }
}
