<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition()
    {
        return [
            'file_number' => strtoupper($this->faker->bothify('REG/2026/###')),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'category_id' => FileCategory::factory(),
            'confirmed_department_id' => Department::factory(),
            'confirmed_holder_user_id' => User::factory(),
            'status' => File::STATUS_ACTIVE,
            'registered_by_user_id' => User::factory()->registryStaff(),
            'registered_at' => now(),
        ];
    }
}
