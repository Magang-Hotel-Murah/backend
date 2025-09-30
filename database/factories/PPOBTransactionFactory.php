<?php

namespace Database\Factories;

use App\Models\PPOBTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PPOBTransactionFactory extends Factory
{
    protected $model = PPOBTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_type' => $this->faker->randomElement(['pulsa', 'listrik', 'air', 'internet']),
            'customer_number' => $this->faker->numerify('##########'),
            'amount' => $this->faker->randomFloat(2, 20000, 500000),
            'status' => $this->faker->randomElement(['pending', 'success', 'failed']),
        ];
    }
}
