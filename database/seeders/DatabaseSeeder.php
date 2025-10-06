<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Company;
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
        // 1. Seed divisions & positions
        $divisions = Division::factory(6)->create();
        $positions = Position::factory(5)->create();

        $divisionIds = $divisions->pluck('id')->toArray();
        $positionIds = $positions->pluck('id')->toArray();

        // 2. Buat company
        $company = Company::factory()->create();

        // 3. Super Admin
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'role' => 'super_admin',
            'company_id' => null,
        ]);
        dump('Super Admin created, total users: ' . User::count());

        // 4. Admin Perusahaan
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Perusahaan',
            'email' => 'admin@test.com',
            'company_id' => $company->id,
        ]);
        dump('Admin Perusahaan created, total users: ' . User::count());

        UserProfile::factory()->create([
            'user_id' => $admin->id,
            'division_id' => $divisionIds[array_rand($divisionIds)],
            'position_id' => $positionIds[array_rand($positionIds)],
        ]);

        // 5. 20 User biasa
        $users = User::factory(20)->forCompany($company)->create()->each(function ($user) use ($divisionIds, $positionIds) {
            UserProfile::factory()->create([
                'user_id' => $user->id,
                'division_id' => $divisionIds[array_rand($divisionIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
            ]);
        });
        dump('20 Users biasa created, total users: ' . User::count());

        $allUsers = User::where('company_id', $company->id)->get();

        // 6. Hotel reservations
        HotelReservation::factory(100)
            ->recycle($allUsers)
            ->has(Transaction::factory()->state(fn(array $attrs, HotelReservation $reservation) => [
                'amount' => $reservation->total_price
            ]))
            ->create();

        // 7. Flight reservations
        FlightReservation::factory(100)
            ->recycle($allUsers)
            ->has(Transaction::factory()->state(fn(array $attrs, FlightReservation $reservation) => [
                'amount' => $reservation->total_price
            ]))
            ->create();

        // 8. PPOB transactions
        PPOBTransaction::factory(100)
            ->recycle($allUsers)
            ->has(Transaction::factory()->state(fn(array $attrs, PPOBTransaction $bill) => [
                'amount' => $bill->total_price
            ]))
            ->create();

        // 9. Meeting rooms
        $rooms = MeetingRoom::factory(5)->create();
        dump('Meeting rooms created, total users: ' . User::count());

        // 10. Meeting reservations + participants + requests
        MeetingRoomReservation::factory(20)->make()->each(function ($reservation) use ($allUsers, $rooms, $divisionIds, $positionIds) {
            $reservation->user_id = $allUsers->random()->id;
            $reservation->meeting_room_id = $rooms->random()->id;
            $reservation->save();
            dump("Reservation {$reservation->id} saved, total users: " . User::count());

            // Internal participants (user terdaftar)
            $internalParticipants = $allUsers
                ->where('id', '!=', $reservation->user_id)
                ->shuffle()
                ->take(rand(2, min(5, $allUsers->count() - 1)));

            foreach ($internalParticipants as $participant) {
                MeetingParticipant::create([
                    'reservation_id' => $reservation->id,
                    'user_id' => $participant->id,
                ]);
            }

            // External participants (tidak membuat user baru)
            for ($i = 0; $i < rand(1, 3); $i++) {
                MeetingParticipant::create([
                    'reservation_id' => $reservation->id,
                    'user_id' => null,
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                    'whatsapp_number' => fake()->numerify('+628##########'),
                ]);
            }

            // Meeting request
            MeetingRequest::create([
                'reservation_id' => $reservation->id,
            ]);
        });
    }
}
