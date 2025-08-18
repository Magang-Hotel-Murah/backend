<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Transaction;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $paymentStatus = ['unpaid', 'paid', 'failed'];
        $paymentMethods = ['credit_card', 'paypal', 'bank_transfer', null];

        return [
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'currency' => 'USD',
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'payment_status' => $this->faker->randomElement($paymentStatus),
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
