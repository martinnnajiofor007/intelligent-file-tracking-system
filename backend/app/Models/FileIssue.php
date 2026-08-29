<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileIssue extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    private const TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_DISMISSED],
        self::STATUS_IN_PROGRESS => [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_DISMISSED],
        self::STATUS_RESOLVED => [self::STATUS_OPEN],
        self::STATUS_DISMISSED => [self::STATUS_OPEN],
    ];

    protected $fillable = [
        'file_id',
        'issue_type',
        'description',
        'status',
        'reported_by_user_id',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
