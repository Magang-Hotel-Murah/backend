<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Room A', 'Room B']),
            'description' => $this->faker->sentence(),
        ];
    }
}
