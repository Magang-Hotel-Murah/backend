<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\MeetingRoom;
use App\Models\MeetingRoomReservation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class MeetingRoomReservationFactory extends Factory
{
    protected $model = MeetingRoomReservation::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-3 days', '+5 days');

        $startHour = $this->faker->numberBetween(9, 16);
        $startMinute = $this->faker->randomElement([0, 15, 30, 45]); // lebih realistis (kelipatan 15 menit)
        $start = Carbon::instance($date)->setTime($startHour, $startMinute);

        $end = (clone $start)->addHours($this->faker->numberBetween(1, 2));
        if ($end->hour > 17) {
            $end->setTime(17, 0);
        }

        return [
            'company_id'       => Company::factory(),
            'user_id'          => null,
            'meeting_room_id'  => MeetingRoom::factory(),
            'title'            => $this->faker->sentence(3),
            'description'      => $this->faker->optional()->paragraph(),
            'start_time'       => $start,
            'end_time'         => $end,
            'status'           => $this->faker->randomElement(['pending', 'approved', 'rejected', 'cancelled']),
        ];
    }

    public function forCompany($company)
    {
        return $this->state([
            'company_id' => $company->id,
        ])->afterMaking(function (MeetingRoomReservation $reservation) use ($company) {
            if ($reservation->meeting_room && $reservation->meeting_room->company_id !== $company->id) {
                $reservation->meeting_room = MeetingRoom::factory()->forCompany($company)->create();
                $reservation->meeting_room_id = $reservation->meeting_room->id;
            }
        });
    }
}
