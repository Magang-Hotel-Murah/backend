<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        $positions = ['Manager', 'Staff', 'Intern', 'Director', 'Coordinator'];

        return [
            'name' => $this->faker->unique()->randomElement($positions),
        ];
    }
}
