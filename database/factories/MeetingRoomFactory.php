<?php

namespace Database\Factories;

use App\Models\MeetingRoom;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRoomFactory extends Factory
{
    protected $model = MeetingRoom::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_id'  => null,
            'name'       => 'Room ' . $this->faker->unique()->word(),
            'capacity'   => $this->faker->numberBetween(4, 30),
            'facilities' => $this->faker->randomElements([
                'Projector',
                'Whiteboard',
                'TV',
                'Conference Call',
                'AC',
                'HDMI Cable'
            ], $this->faker->numberBetween(2, 4)),
            'status'     => $this->faker->randomElement(['available', 'maintenance']),
            'type'       => $this->faker->randomElement(['main', 'sub']),
        ];
    }

    public function subRoom($parentId)
    {
        return $this->state([
            'type' => 'sub',
            'parent_id' => $parentId,
        ]);
    }

    public function forCompany($company)
    {
        return $this->state([
            'company_id' => $company->id,
        ]);
    }
}
