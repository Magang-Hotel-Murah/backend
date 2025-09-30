<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\HotelReservation;
use App\Models\MeetingRoomReservation;
use App\Models\MeetingRoom;
use App\Models\Division;
use App\Models\Position;
use App\Models\FlightReservation;
use App\Models\PPOBTransaction;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed divisions
        Division::factory()->count(6)->create();

        // 2. Seed positions
        Position::factory()->count(5)->create();

        $divisionIds = Division::pluck('id')->toArray();
        $positionIds = Position::pluck('id')->toArray();

        // 3. Seed admin user
        $admin = User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
            'role'  => 'admin',
        ]);

        UserProfile::factory()->create([
            'user_id'     => $admin->id,
            'division_id' => $divisionIds[array_rand($divisionIds)],
            'position_id' => $positionIds[array_rand($positionIds)],
        ]);

        // 4. Seed 10 user dengan profile
        User::factory(10)->create()->each(function ($user) use ($divisionIds, $positionIds) {
            UserProfile::factory()->create([
                'user_id'     => $user->id,
                'division_id' => $divisionIds[array_rand($divisionIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
            ]);
        });

        // 5. Seed hotel reservations
        HotelReservation::factory(5)->create();

        HotelReservation::factory()
            ->count(100)
            ->hasTransactions(1)
            ->create();

        // 6. Seed flight reservations
        FlightReservation::factory()
            ->count(100)
            ->hasTransactions(1)
            ->create();

        // 7. Seed PPOB transactions
        PPOBTransaction::factory()
            ->count(100)
            ->hasTransactions(1)
            ->create();

        // 8. Seed meeting rooms
        $rooms = MeetingRoom::factory()->count(2)->create();

        // 9. Seed meeting room reservations
        MeetingRoomReservation::factory(5)->create();
    }
}
