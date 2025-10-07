<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\MeetingParticipant;
use App\Models\MeetingRoomReservation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingParticipantFactory extends Factory
{
    protected $model = MeetingParticipant::class;

    public function definition(): array
    {
        return [
            'company_id'      => Company::factory(),
            'reservation_id'  => MeetingRoomReservation::factory(),
            'user_id'         => null,
            'name'            => $this->faker->name(),
            'email'           => $this->faker->safeEmail(),
            'whatsapp_number' => $this->faker->numerify('+628##########'),
        ];
    }

    public function forCompany($company)
    {
        return $this->state([
            'company_id' => $company->id,
        ])->afterMaking(function (MeetingParticipant $participant) use ($company) {
            // Pastikan reservation-nya juga dari company yang sama
            if ($participant->reservation && $participant->reservation->company_id !== $company->id) {
                $reservation = MeetingRoomReservation::factory()->forCompany($company)->create();
                $participant->reservation_id = $reservation->id;
            }
        });
    }

    /**
     * State untuk peserta yang merupakan user terdaftar
     */
    public function withUser()
    {
        return $this->afterCreating(function (MeetingParticipant $participant) {
            $user = User::factory()
                ->has(UserProfile::factory(), 'profile')
                ->create([
                    'company_id' => $participant->company_id,
                ]);

            $participant->update([
                'user_id'         => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'whatsapp_number' => $user->profile->phone ?? $this->faker->numerify('+628##########'),
            ]);
        });
    }
}
