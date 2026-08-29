<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\FileIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FileIssueFactory extends Factory
{
    protected $model = FileIssue::class;

    public function definition()
    {
        return [
            'file_id' => File::factory(),
            'issue_type' => 'damage',
            'description' => $this->faker->paragraph(),
            'status' => FileIssue::STATUS_OPEN,
            'reported_by_user_id' => User::factory(),
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ];
    }
}
