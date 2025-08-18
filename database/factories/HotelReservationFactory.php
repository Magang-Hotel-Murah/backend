<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\HotelReservation;

class HotelReservationFactory extends Factory
{
    protected $model = HotelReservation::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'hotel_name' => $this->faker->company,
            'hotel_id' => $this->faker->uuid,
            'check_in_date' => $this->faker->date(),
            'check_out_date' => $this->faker->date(),
            'adults' => $this->faker->numberBetween(1, 4),
            'total_price' => $this->faker->randomFloat(2, 100, 500), // total price for the reservation
            'status' => 'pending',
        ];
    }
}
