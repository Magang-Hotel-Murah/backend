<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PPOBTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invoice_number' => 'INV-' . now()->timestamp . '-' . Str::upper(Str::random(6)),
            'service_type' => fake()->randomElement(['PLN_PASCABAYAR', 'PULSA_TELKOMSEL', 'PDAM_KOTA_A']),
            'customer_number' => fake()->numerify('0812########'),
            'total_price' => fake()->randomElement([50000, 100000, 200000, 500000]),
            'currency' => 'IDR',
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
        ];
    }
}
