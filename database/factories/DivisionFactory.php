<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Company;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        $divisions = ['IT', 'HR', 'Finance', 'Marketing', 'Operations', 'Support'];

        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->randomElement($divisions),
        ];
    }
}
