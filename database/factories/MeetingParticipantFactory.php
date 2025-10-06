<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\MeetingParticipant;
use App\Models\MeetingRoomReservation;
use Illuminate\Database\Eloquent\Factories\Factory;


class MeetingParticipantFactory extends Factory
{
    protected $model = MeetingParticipant::class;

    public function definition(): array
    {
        return [
            'reservation_id'   => MeetingRoomReservation::factory(),
            'user_id'          => null,
            'name'             => $this->faker->name(),
            'email'            => $this->faker->safeEmail(),
            'whatsapp_number'  => $this->faker->numerify('+628##########'),
        ];
    }

    /**
     * State untuk peserta yang merupakan user terdaftar
     */
    public function withUser()
    {
        return $this->afterCreating(function (MeetingParticipant $participant) {
            $user = User::factory()
                ->has(UserProfile::factory(), 'profile')
                ->create();

            $participant->update([
                'user_id'         => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'whatsapp_number' => $user->profile->phone ?? $this->faker->numerify('+628##########'),
            ]);
        });
    }
}
