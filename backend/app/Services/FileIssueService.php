<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\FileIssue;
use App\Models\User;
use InvalidArgumentException;

class FileIssueService
{
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

        AuditLog::create([
            'actor_user_id' => $actor->id,
            'action' => 'issue_created',
            'entity_type' => FileIssue::class,
            'entity_id' => $issue->id,
            'after' => $issue->toArray(),
        ]);

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

        AuditLog::create([
            'actor_user_id' => $actor->id,
            'action' => 'issue_status_changed',
            'entity_type' => FileIssue::class,
            'entity_id' => $issue->id,
            'before' => $before,
            'after' => $after,
        ]);

        if ($newStatus === FileIssue::STATUS_RESOLVED) {
            AuditLog::create([
                'actor_user_id' => $actor->id,
                'action' => 'issue_resolved',
                'entity_type' => FileIssue::class,
                'entity_id' => $issue->id,
                'before' => $before,
                'after' => $after,
            ]);
        } elseif ($newStatus === FileIssue::STATUS_DISMISSED) {
            AuditLog::create([
                'actor_user_id' => $actor->id,
                'action' => 'issue_dismissed',
                'entity_type' => FileIssue::class,
                'entity_id' => $issue->id,
                'before' => $before,
                'after' => $after,
            ]);
        }

        return $issue->fresh();
    }
}
