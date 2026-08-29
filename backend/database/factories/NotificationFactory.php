<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'type' => 'transfer_created',
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->sentence(),
            'related_type' => null,
            'related_id' => null,
            'metadata' => null,
            'read_at' => null,
        ];
    }
}
