<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FileCategoryFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word(),
            'default_due_days' => $this->faker->optional()->numberBetween(1, 30),
        ];
    }
}
