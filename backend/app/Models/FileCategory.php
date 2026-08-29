<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_due_days',
    ];

    protected $casts = [
        'default_due_days' => 'integer',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'category_id');
    }
}
