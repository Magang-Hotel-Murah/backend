<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\UserProfile;
use App\Models\User;
use App\Models\Division;
use App\Models\Position;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(), // fallback
            'division_id' => Division::inRandomOrder()->first()?->id ?? Division::factory(),
            'position_id' => Position::inRandomOrder()->first()?->id ?? Position::factory(),
            'address' => $this->faker->address(),
            'phone' => '+62' . $this->faker->numerify('8##########'),
            'photo' => 'https://i.pravatar.cc/200?u=' . $this->faker->unique()->randomNumber(),
        ];
    }
}
