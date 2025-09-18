<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\HotelReservation;
use App\Models\MeetingRoomReservation;
use App\Models\MeetingRoom;
use App\Models\Division;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed divisions dulu
        $divisions = ['IT', 'HR', 'Finance', 'Marketing', 'Operations', 'Support'];
        foreach ($divisions as $division) {
            Division::firstOrCreate(['name' => $division]);
        }

        // Ambil semua division id
        $divisionIds = Division::pluck('id')->toArray();

        // 2. Seed admin user dengan division random
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin',
            'division_id' => $divisionIds[array_rand($divisionIds)],
        ]);

        // 3. Seed 10 user random dengan division random
        User::factory(10)->create([
            'division_id' => fn() => $divisionIds[array_rand($divisionIds)],
        ]);

        // 4. Seed hotel reservations
        HotelReservation::factory(5)->create();

        HotelReservation::factory()
            ->count(10)
            ->hasTransactions(1)
            ->create();

        // 5. Seed meeting rooms
        $rooms = MeetingRoom::factory()->count(2)->create();

        // 6. Seed meeting room reservations
        MeetingRoomReservation::factory(5)->create();
    }
}
