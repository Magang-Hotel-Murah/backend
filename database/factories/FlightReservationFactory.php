<?php

namespace Database\Factories;

use App\Models\FlightReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlightReservationFactory extends Factory
{
    protected $model = FlightReservation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'flight_number' => strtoupper($this->faker->bothify('GA###')),
            'origin' => $this->faker->city(),
            'destination' => $this->faker->city(),
            'departure_time' => $this->faker->dateTimeBetween('+1 days', '+5 days'),
            'arrival_time' => $this->faker->dateTimeBetween('+5 days', '+10 days'),
            'passenger_count' => $this->faker->numberBetween(1, 5),
            'price' => $this->faker->randomFloat(2, 500000, 3000000),
        ];
    }
}
