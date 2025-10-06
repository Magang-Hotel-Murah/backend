<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\MeetingRoom;
use App\Models\MeetingRoomReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRoomReservationFactory extends Factory
{
    protected $model = MeetingRoomReservation::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+0 days', '+5 days');
        $end = (clone $start)->modify('+1 hour');

        return [
            'user_id' => null,
            'meeting_room_id' => MeetingRoom::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'start_time' => $start,
            'end_time' => $end,
            'participants' => $this->faker->numberBetween(2, 20),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected', 'cancelled']),
        ];
    }
}
