<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HotelReservationFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 day', '+1 month');
        $checkOut = (clone $checkIn)->modify('+' . fake()->numberBetween(1, 7) . ' days');

        return [
            'user_id' => User::factory(),
            'booking_code' => 'HOTEL-' . Str::upper(Str::random(8)),
            'hotel_id' => fake()->numerify('HOTEL####'),
            'hotel_name' => fake()->company() . ' Hotel & Resort',
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'guest_details' => [
                ['adult' => fake()->numberBetween(1, 3)],
                ['child' => fake()->numberBetween(0, 2)],
            ],
            'total_price' => fake()->randomFloat(2, 500000, 10000000),
            'currency' => 'IDR',
            'status' => fake()->randomElement(['pending', 'confirmed', 'cancelled']),
        ];
    }
}
