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
use App\Models\Transaction;

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

        User::factory(10)->create()->each(function ($user) use ($divisionIds, $positionIds) {
            UserProfile::factory()->create([
                'user_id'     => $user->id,
                'division_id' => $divisionIds[array_rand($divisionIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
            ]);
        });

        // Kumpulkan semua user yang sudah dibuat untuk digunakan kembali
        $users = User::all();

        // 5. Seed hotel reservations
        HotelReservation::factory(5)->create();

        HotelReservation::factory(100)
            ->recycle($users) // Gunakan user yang ada secara acak
            ->has(Transaction::factory()->state(function (array $attributes, HotelReservation $reservation) {
                // Sinkronkan jumlah transaksi dengan harga reservasi
                return ['amount' => $reservation->total_price];
            }))
            ->create();

        // 4. Seed flight reservations
        FlightReservation::factory(100)
            ->recycle($users)
            ->has(Transaction::factory()->state(function (array $attributes, FlightReservation $reservation) {
                return ['amount' => $reservation->total_price];
            }))
            ->create();

        // 5. Seed PPOB Bills
        PPOBTransaction::factory(100)
            ->recycle($users)
            ->has(Transaction::factory()->state(function (array $attributes, PPOBTransaction $bill) {
                return ['amount' => $bill->total_price];
            }))
            ->create();

        // 8. Seed meeting rooms
        $rooms = MeetingRoom::factory()->count(2)->create();

        // 9. Seed meeting room reservations
        MeetingRoomReservation::factory(5)->create();
    }
}
