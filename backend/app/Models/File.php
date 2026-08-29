<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class File extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'file_number',
        'title',
        'description',
        'category_id',
        'confirmed_department_id',
        'confirmed_holder_user_id',
        'status',
        'registered_by_user_id',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FileCategory::class, 'category_id');
    }

    public function confirmedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'confirmed_department_id');
    }

    public function confirmedHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_holder_user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(FileIssue::class);
    }

    public function pendingTransfer(): HasOne
    {
        return $this->hasOne(Transfer::class)
            ->where('status', Transfer::STATUS_PENDING)
            ->latestOfMany();
    }
}
