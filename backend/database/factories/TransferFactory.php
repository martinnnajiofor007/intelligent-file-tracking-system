<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\File;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition()
    {
        return [
            'file_id' => File::factory(),
            'from_department_id' => Department::factory(),
            'from_holder_user_id' => User::factory(),
            'to_department_id' => Department::factory(),
            'to_holder_user_id' => User::factory(),
            'requested_by_user_id' => User::factory(),
            'requested_at' => now(),
            'status' => Transfer::STATUS_PENDING,
            'acknowledged_by_user_id' => null,
            'acknowledged_at' => null,
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'due_at' => null,
        ];
    }
}
