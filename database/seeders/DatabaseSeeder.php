<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\HotelReservation;
use App\Models\MeetingRoomReservation;
use App\Models\MeetingRoom;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
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
        Division::factory(6)->create();

        // 2. Seed positions
        Position::factory(5)->create();

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

        // 4. Seed 20 user biasa
        $users = User::factory(20)->create()->each(function ($user) use ($divisionIds, $positionIds) {
            UserProfile::factory()->create([
                'user_id'     => $user->id,
                'division_id' => $divisionIds[array_rand($divisionIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
            ]);
        });

        $users = User::all(); // ambil semua user termasuk admin

        // 5. Seed hotel reservations dengan transaksi
        HotelReservation::factory(100)
            ->recycle($users)
            ->has(Transaction::factory()->state(function (array $attributes, HotelReservation $reservation) {
                return ['amount' => $reservation->total_price];
            }))
            ->create();

        // 6. Seed flight reservations
        FlightReservation::factory(100)
            ->recycle($users)
            ->has(Transaction::factory()->state(function (array $attributes, FlightReservation $reservation) {
                return ['amount' => $reservation->total_price];
            }))
            ->create();

        // 7. Seed PPOB transactions
        PPOBTransaction::factory(100)
            ->recycle($users)
            ->has(Transaction::factory()->state(function (array $attributes, PPOBTransaction $bill) {
                return ['amount' => $bill->total_price];
            }))
            ->create();

        // 8. Seed meeting rooms
        $rooms = MeetingRoom::factory(5)->create();

        // 9️⃣ Seed meeting reservations + participants + requests
        $reservations = MeetingRoomReservation::factory(30)->make()->each(function ($reservation) use ($users, $rooms) {
            $reservation->user_id = $users->random()->id;
            $reservation->meeting_room_id = $rooms->random()->id;
            $reservation->save();

            // --- 🧑‍💼 Tambahkan peserta internal ---
            $internalParticipants = $users
                ->where('id', '!=', $reservation->user_id)
                ->random(rand(2, 5));

            foreach ($internalParticipants as $participant) {
                MeetingParticipant::create([
                    'reservation_id' => $reservation->id,
                    'user_id'        => $participant->id,
                ]);
            }

            // --- 🌐 Tambahkan peserta eksternal ---
            for ($i = 0; $i < rand(1, 3); $i++) {
                MeetingParticipant::factory()->create([
                    'reservation_id' => $reservation->id,
                    'user_id'        => null, // eksternal
                    'name'           => fake()->name(),
                    'email'          => fake()->safeEmail(),
                    'whatsapp_number' => fake()->numerify('+628##########'),
                ]);
            }

            // Tambahkan MeetingRequest untuk setiap reservation
            MeetingRequest::factory()->create([
                'reservation_id' => $reservation->id,
            ]);
        });
    }
}
