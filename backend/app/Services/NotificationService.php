<?php

namespace App\Services;

use App\Models\FileIssue;
use App\Models\Notification;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\QueryException;

class NotificationService
{
    public const TYPE_TRANSFER_CREATED = 'transfer_created';
    public const TYPE_TRANSFER_ACKNOWLEDGED = 'transfer_acknowledged';
    public const TYPE_TRANSFER_REJECTED = 'transfer_rejected';
    public const TYPE_TRANSFER_OVERDUE = 'transfer_overdue';
    public const TYPE_ISSUE_REPORTED = 'issue_reported';
    public const TYPE_ISSUE_STATUS_CHANGED = 'issue_status_changed';

    /**
     * Keys that must never be persisted in notification metadata.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'password_hash',
        'remember_token',
        'api_key',
        'api_token',
        'access_token',
        'refresh_token',
        'auth_token',
        'client_secret',
        'token',
        'secret',
        'authorization',
    ];

    private const SENSITIVE_SUBSTRINGS = [
        'password',
        'token',
        'secret',
        'authorization',
    ];

    /**
     * Create a notification for a recipient. Recipient and entity info are
     * always server-controlled.
     *
     * @param  string|null  $dedupKey  Optional unique key used to prevent
     *                                 duplicate notifications for the same
     *                                 event. Null for one-shot notifications.
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $metadata = [],
        ?string $dedupKey = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'metadata' => $this->sanitize($metadata),
            'dedup_key' => $dedupKey,
        ]);
    }

    public function notifyTransferCreated(Transfer $transfer): void
    {
        $this->create(
            $transfer->to_holder_user_id,
            self::TYPE_TRANSFER_CREATED,
            'New transfer request',
            "A transfer for file #{$transfer->file_id} has been requested for you.",
            Transfer::class,
            $transfer->id,
            ['file_id' => $transfer->file_id]
        );
    }

    public function notifyTransferAcknowledged(Transfer $transfer): void
    {
        $this->create(
            $transfer->requested_by_user_id,
            self::TYPE_TRANSFER_ACKNOWLEDGED,
            'Transfer acknowledged',
            "Your transfer for file #{$transfer->file_id} was acknowledged.",
            Transfer::class,
            $transfer->id,
            ['file_id' => $transfer->file_id]
        );
    }

    public function notifyTransferRejected(Transfer $transfer): void
    {
        $this->create(
            $transfer->requested_by_user_id,
            self::TYPE_TRANSFER_REJECTED,
            'Transfer rejected',
            "Your transfer for file #{$transfer->file_id} was rejected.",
            Transfer::class,
            $transfer->id,
            ['file_id' => $transfer->file_id]
        );
    }

    /**
     * Create an overdue notification for the intended recipient. Idempotent:
     * returns false (and creates nothing) if one already exists for this
     * transfer and recipient.
     *
     * Concurrency-safe: a unique database index on `dedup_key` guarantees that
     * two concurrent executions cannot both insert a `transfer_overdue`
     * notification for the same recipient/transfer. The pre-check is a fast
     * path; the unique constraint is the authoritative guard.
     */
    public function notifyTransferOverdue(Transfer $transfer): bool
    {
        if ($this->hasOverdueNotification($transfer)) {
            return false;
        }

        try {
            $this->create(
                $transfer->to_holder_user_id,
                self::TYPE_TRANSFER_OVERDUE,
                'Overdue transfer',
                "The transfer for file #{$transfer->file_id} is overdue.",
                Transfer::class,
                $transfer->id,
                ['file_id' => $transfer->file_id],
                $this->overdueDedupKey($transfer)
            );
        } catch (QueryException $e) {
            // A concurrent execution already created this notification.
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    public function hasOverdueNotification(Transfer $transfer): bool
    {
        return Notification::where('user_id', $transfer->to_holder_user_id)
            ->where('type', self::TYPE_TRANSFER_OVERDUE)
            ->where('related_type', Transfer::class)
            ->where('related_id', $transfer->id)
            ->exists();
    }

    private function overdueDedupKey(Transfer $transfer): string
    {
        return implode(':', [
            self::TYPE_TRANSFER_OVERDUE,
            $transfer->to_holder_user_id,
            Transfer::class,
            $transfer->id,
        ]);
    }

    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_SUBSTRINGS as $substring) {
            if (str_contains($key, $substring)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notify responsible users (admin, supervisor, registry_staff) when an
     * issue is reported. The reporter is excluded to avoid self-notification.
     */
    public function notifyIssueReported(FileIssue $issue): void
    {
        $recipients = User::whereIn('role', ['admin', 'registry_staff', 'supervisor'])
            ->where('is_active', true)
            ->where('id', '!=', $issue->reported_by_user_id)
            ->pluck('id');

        foreach ($recipients as $userId) {
            $this->create(
                $userId,
                self::TYPE_ISSUE_REPORTED,
                'New file issue',
                "An issue was reported for file #{$issue->file_id}.",
                FileIssue::class,
                $issue->id,
                ['file_id' => $issue->file_id, 'issue_type' => $issue->issue_type]
            );
        }
    }

    /**
     * Notify the reporter when their issue's status changes.
     */
    public function notifyIssueStatusChanged(FileIssue $issue, string $newStatus): void
    {
        $this->create(
            $issue->reported_by_user_id,
            self::TYPE_ISSUE_STATUS_CHANGED,
            'Issue status updated',
            "The status of issue #{$issue->id} for file #{$issue->file_id} changed to {$newStatus}.",
            FileIssue::class,
            $issue->id,
            ['file_id' => $issue->file_id, 'status' => $newStatus]
        );
    }
}
