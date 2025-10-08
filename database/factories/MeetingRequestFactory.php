<?php

namespace Database\Factories;

use App\Models\MeetingRequest;
use App\Models\MeetingRoomReservation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRequestFactory extends Factory
{
    protected $model = MeetingRequest::class;

    public function definition(): array
    {
        return [
            'company_id'     => Company::factory(),
            'reservation_id' => MeetingRoomReservation::factory(),
            'funds_amount'   => $this->faker->optional()->randomFloat(2, 100000, 5000000),
            'funds_reason'   => $this->faker->optional()->sentence(),
            'snacks'         => $this->faker->optional()->randomElements([
                'Snack Box',
                'Kopi',
                'Teh',
                'Air Mineral',
                'Kue Basah'
            ], $this->faker->numberBetween(1, 3)),
            'equipment'      => $this->faker->optional()->randomElements([
                'Projector',
                'Microphone',
                'Speaker',
                'Laptop'
            ], $this->faker->numberBetween(1, 2)),
            'status'         => 'pending',
            'rejection_reason' => null,
            'approved_by'    => null,

        ];
    }

    public function forCompany($company)
    {
        return $this->state([
            'company_id' => $company->id,
        ])->afterMaking(function (MeetingRequest $request) use ($company) {
            // Pastikan reservation-nya punya company_id yang sama
            if ($request->reservation && $request->reservation->company_id !== $company->id) {
                $reservation = MeetingRoomReservation::factory()->forCompany($company)->create();
                $request->reservation_id = $reservation->id;
            }
        });
    }
}
