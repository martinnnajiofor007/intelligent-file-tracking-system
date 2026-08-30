<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_REGISTRY_STAFF = 'registry_staff';
    public const ROLE_DEPARTMENT_STAFF = 'department_staff';
    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_REGISTRY_STAFF,
        self::ROLE_DEPARTMENT_STAFF,
        self::ROLE_SUPERVISOR,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function registeredFiles(): HasMany
    {
        return $this->hasMany(File::class, 'registered_by_user_id');
    }

    public function confirmedFiles(): HasMany
    {
        return $this->hasMany(File::class, 'confirmed_holder_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderByDesc('created_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function canRegisterFiles(): bool
    {
        return in_array($this->role, ['admin', 'registry_staff'], true);
    }

    public function canCreateTransfers(): bool
    {
        return in_array($this->role, ['admin', 'registry_staff', 'supervisor'], true);
    }
}
