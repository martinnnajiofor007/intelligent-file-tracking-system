<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileIssue;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use InvalidArgumentException;

class FileIssueService
{
    public function __construct(
        private AuditLogService $audit,
        private NotificationService $notifications
    ) {
    }
    /**
     * Report a new issue against a file. The reporter is always the actor.
     */
    public function create(User $actor, File $file, array $data): FileIssue
    {
        $issue = FileIssue::create([
            'file_id' => $file->id,
            'issue_type' => $data['issue_type'],
            'description' => $data['description'],
            'status' => FileIssue::STATUS_OPEN,
            'reported_by_user_id' => $actor->id,
        ]);

        $this->audit->record(
            $actor,
            'issue_created',
            FileIssue::class,
            $issue->id,
            null,
            $issue
        );

        $this->notifications->notifyIssueReported($issue);

        return $issue;
    }

    /**
     * Transition an issue to a new status, enforcing the lifecycle and
     * managing resolution fields server-side.
     */
    public function updateStatus(User $actor, FileIssue $issue, string $newStatus): FileIssue
    {
        if ($newStatus === $issue->status) {
            throw new InvalidArgumentException('The issue is already in this status.');
        }

        if (! $issue->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException("Cannot transition issue from '{$issue->status}' to '{$newStatus}'.");
        }

        $before = $issue->toArray();

        $data = ['status' => $newStatus];

        if ($newStatus === FileIssue::STATUS_RESOLVED) {
            $data['resolved_by_user_id'] = $actor->id;
            $data['resolved_at'] = now();
        } elseif ($newStatus === FileIssue::STATUS_OPEN) {
            $data['resolved_by_user_id'] = null;
            $data['resolved_at'] = null;
        }

        $issue->update($data);

        $after = $issue->fresh()->toArray();

        $this->audit->record(
            $actor,
            'issue_status_changed',
            FileIssue::class,
            $issue->id,
            $before,
            $after
        );

        if ($newStatus === FileIssue::STATUS_RESOLVED) {
            $this->audit->record(
                $actor,
                'issue_resolved',
                FileIssue::class,
                $issue->id,
                $before,
                $after
            );
        } elseif ($newStatus === FileIssue::STATUS_DISMISSED) {
            $this->audit->record(
                $actor,
                'issue_dismissed',
                FileIssue::class,
                $issue->id,
                $before,
                $after
            );
        }

        $this->notifications->notifyIssueStatusChanged($issue->fresh(), $newStatus);

        return $issue->fresh();
    }
}
