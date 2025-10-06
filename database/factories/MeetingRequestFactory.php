<?php

namespace Database\Factories;

use App\Models\MeetingRequest;
use App\Models\MeetingRoomReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRequestFactory extends Factory
{
    protected $model = MeetingRequest::class;

    public function definition(): array
    {
        return [
            'reservation_id' => MeetingRoomReservation::factory(),
            'funds_amount' => $this->faker->optional()->randomFloat(2, 100000, 5000000),
            'funds_reason' => $this->faker->optional()->sentence(),
            'snacks' => $this->faker->optional()->randomElements([
                'Snack Box',
                'Kopi',
                'Teh',
                'Air Mineral',
                'Kue Basah'
            ], $this->faker->numberBetween(1, 3)),
            'equipment' => $this->faker->optional()->randomElements([
                'Projector',
                'Microphone',
                'Speaker',
                'Laptop'
            ], $this->faker->numberBetween(1, 2)),
        ];
    }
}
