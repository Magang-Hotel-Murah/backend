<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\MeetingRoom;
use App\Models\MeetingRoomReservation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRoomReservationFactory extends Factory
{
    protected $model = MeetingRoomReservation::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+0 days', '+5 days');
        $end   = (clone $start)->modify('+1 hour');

        return [
            'company_id'       => Company::factory(),
            'user_id'          => null,
            'meeting_room_id'  => MeetingRoom::factory(),
            'title'            => $this->faker->sentence(3),
            'description'      => $this->faker->optional()->paragraph(),
            'start_time'       => $start,
            'end_time'         => $end,
            'participants'     => $this->faker->numberBetween(2, 20),
            'status'           => $this->faker->randomElement(['pending', 'approved', 'rejected', 'cancelled']),
        ];
    }

    public function forCompany($company)
    {
        return $this->state([
            'company_id' => $company->id,
        ])->afterMaking(function (MeetingRoomReservation $reservation) use ($company) {
            // Pastikan room-nya juga dari company yang sama
            if ($reservation->meeting_room && $reservation->meeting_room->company_id !== $company->id) {
                $reservation->meeting_room = MeetingRoom::factory()->forCompany($company)->create();
                $reservation->meeting_room_id = $reservation->meeting_room->id;
            }
        });
    }
}
