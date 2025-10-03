<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FlightReservationFactory extends Factory
{
    public function definition(): array
    {
        $departure = fake()->dateTimeBetween('+3 days', '+3 months');
        $arrival = (clone $departure)->modify('+' . fake()->numberBetween(1, 12) . ' hours');

        return [
            'user_id' => User::factory(),
            'booking_code' => 'FLIGHT-' . Str::upper(Str::random(8)),
            'flight_number' => fake()->randomElement(['GA', 'QZ', 'JT', 'ID']) . fake()->numerify('###'),
            'airline' => fake()->randomElement(['Garuda Indonesia', 'Lion Air', 'Batik Air', 'Citilink']),
            'origin' => $this->faker->city(),
            'destination' => $this->faker->city(),
            'departure_time' => $departure,
            'arrival_time' => $arrival,
            'passenger_details' => [ // <-- Hapus fungsi json_encode()
                ['name' => fake()->name(), 'type' => 'adult'],
                ['name' => fake()->name(), 'type' => 'adult'],
            ],
            'total_price' => fake()->randomFloat(2, 1000000, 15000000),
            'currency' => 'IDR',
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'expired']),
        ];
    }
}
