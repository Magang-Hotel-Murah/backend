<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\HotelReservation;
use App\Models\MeetingRoomReservation;
use App\Models\MeetingRoom;
use App\Models\Division;
use App\Models\Position;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed divisions dulu
        $divisions = ['IT', 'HR', 'Finance', 'Marketing', 'Operations', 'Support'];
        foreach ($divisions as $division) {
            Division::firstOrCreate(['name' => $division]);
        }

        // 2. Seed positions
        $positions = ['Manager', 'Staff', 'Intern', 'Director', 'Coordinator'];
        foreach ($positions as $position) {
            Position::firstOrCreate(['name' => $position]);
        }

        // Ambil semua division id
        $divisionIds = Division::pluck('id')->toArray();

        // Ambil semua position id
        $positionIds = Position::pluck('id')->toArray();

        // 2. Seed admin user dengan division random
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin',
            'division_id' => $divisionIds[array_rand($divisionIds)],
            'position_id' => $positionIds[array_rand($positionIds)],
        ]);

        // 3. Seed 10 user random dengan division random
        User::factory(10)->create([
            'division_id' => fn() => $divisionIds[array_rand($divisionIds)],
            'position_id' => fn() => $positionIds[array_rand($positionIds)],
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
