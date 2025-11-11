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
        $images = [
            'https://www.swissotel.com/assets/0/92/2119/6442452030/6442452073/6442452075/6442452201/aa5bb78e-fe06-432a-9894-8f0c308f81fd.jpg',
            'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRgO3Ss9VXGc5eUkvkirLsze8UHeBxAh0qp86PaTZfryvoqAC6goCNaZTDQGrqVOlkysA4&usqp=CAU',
            'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRSc9Emu8LXPPoM23FfIzFICU8MELb042KYsKnqqDTZN8xC5RowLirgjLwn7aKF3mFdD0k&usqp=CAU',
            'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRSOgwNsP1z3Z05jYV47aNv1aEgugNMKLH2AeKhkie-gqY-yspAVGy5wOpXhEPtXcrfo9g&usqp=CAU',
            'https://go-work-web.storage.googleapis.com/product/3/slider/slider-3.webp',
            'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR2-MyKj3m1D2T4HaNGG5QaGcC17p5CPeb2nkM_NWNqx9WpNeP_EWlxIOBRzSzjOUjdk9o&usqp=CAU',
            'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQikjwoGwjUYZB2qILsNtJfSWoLlRj1xqUpzvvY-EQkrVkwxRZHw9ftFUfRbBZwpbE2xVE&usqp=CAU',
            'https://twp-staging.s3.ap-southeast-1.amazonaws.com/image_upload/marketing_site/20252002/1740042528_QuayQuarter_Meeting-Room.jpg',
            'https://officebanao.com/wp-content/uploads/2025/03/Office-Conference-Room-Interior.jpg',
            'https://framerusercontent.com/images/Bm4GMt0OFtirBFP8UdoQQrPD7QE.jpg?width=2048&height=1365'
        ];
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
            'location'   => $this->faker->unique()->address(),
            'type'       => $this->faker->randomElement(['main', 'sub']),
            'images' => array_values(
                $this->faker->randomElements($images, $this->faker->numberBetween(1, 3))
            ),
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
