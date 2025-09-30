<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        $divisions = ['IT', 'HR', 'Finance', 'Marketing', 'Operations', 'Support'];

        return [
            'name' => $this->faker->unique()->randomElement($divisions),
        ];
    }
}
