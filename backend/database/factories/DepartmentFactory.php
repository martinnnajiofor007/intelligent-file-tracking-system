<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company(),
            'parent_id' => null,
        ];
    }
}
