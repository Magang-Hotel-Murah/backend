<?php

namespace Database\Factories;

use App\Models\MeetingRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingRoomFactory extends Factory
{
    protected $model = MeetingRoom::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => 'Room ' . $this->faker->unique()->word(),
            'capacity' => $this->faker->numberBetween(4, 30),
            'facilities' => $this->faker->randomElements([
                'Projector',
                'Whiteboard',
                'TV',
                'Conference Call',
                'AC',
                'HDMI Cable'
            ], $this->faker->numberBetween(2, 4)),
            'status' => $this->faker->randomElement(['available', 'maintenance']),
            'type' => $this->faker->randomElement(['main', 'sub']),
        ];
    }

    /**
     * Subroom (child room) factory state
     */
    public function subRoom($parentId)
    {
        return $this->state([
            'type' => 'sub',
            'parent_id' => $parentId,
        ]);
    }
}
