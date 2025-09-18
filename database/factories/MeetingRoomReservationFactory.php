<?php

namespace Database\Factories;

use App\Models\MeetingRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRoomReservationFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+0 days', '+7 days');
        $end = (clone $start)->modify('+2 hours');

        return [
            'meeting_room_id' => MeetingRoom::inRandomOrder()->first()->id ?? MeetingRoom::factory(),
            'user_id' => User::inRandomOrder()->first()->id ?? User::factory(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }
}
