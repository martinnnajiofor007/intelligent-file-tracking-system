<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'file_id',
        'from_department_id',
        'from_holder_user_id',
        'to_department_id',
        'to_holder_user_id',
        'requested_by_user_id',
        'requested_at',
        'status',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'rejected_by_user_id',
        'rejected_at',
        'due_at',
    ];

    protected $casts = [
        'file_id' => 'integer',
        'from_department_id' => 'integer',
        'from_holder_user_id' => 'integer',
        'to_department_id' => 'integer',
        'to_holder_user_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'acknowledged_by_user_id' => 'integer',
        'rejected_by_user_id' => 'integer',
        'requested_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'rejected_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function fromHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_holder_user_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function toHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_holder_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->isPending()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }
}
