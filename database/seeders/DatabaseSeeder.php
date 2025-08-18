<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\HotelReservation;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin'
        ]);

        // 2. Seed hotel reservations
        HotelReservation::factory(5)->create();

        // 3. Seed hotel reservations dengan transaction
        HotelReservation::factory()
            ->count(10)
            ->hasTransactions(1)
            ->create();
    }
}
