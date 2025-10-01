<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'paid', 'failed', 'expired']);

        return [
            // transactionable_id & transactionable_type akan diisi otomatis oleh relasi
            'external_id' => 'TRX-' . Str::uuid(),
            'amount' => fake()->randomFloat(2, 50000, 1000000), // Sebaiknya disesuaikan dengan parent
            'currency' => 'IDR',
            'payment_method' => fake()->randomElement(['credit_card', 'bank_transfer', 'gopay', 'ovo']),
            'payment_status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ];
    }
}
