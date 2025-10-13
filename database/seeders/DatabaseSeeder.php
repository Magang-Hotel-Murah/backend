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
        function createUserWithProfile(array $userData, array $divisionIds, array $positionIds)
        {
            $user = User::factory()->create($userData);

            UserProfile::factory()->create([
                'user_id'     => $user->id,
                'division_id' => $divisionIds[array_rand($divisionIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
            ]);

            return $user;
        }

        // 1. Seed divisions & positions
        $divisions = Division::factory(6)->create();
        $positions = Position::factory(5)->create();

        $divisionIds = $divisions->pluck('id')->toArray();
        $positionIds = $positions->pluck('id')->toArray();

        // 2. Buat company
        $company = Company::factory()->create();

        // Super admin
        $superAdmin = createUserWithProfile([
            'name'       => 'Super Admin',
            'email'      => 'superadmin@example.com',
            'role'       => 'super_admin',
            'company_id' => null,
        ], $divisionIds, $positionIds);

        // Admin Perusahaan
        $admin = createUserWithProfile([
            'name'       => 'Admin Perusahaan',
            'email'      => 'companyadmin@example.com',
            'role'       => 'company_admin',
            'company_id' => $company->id,
        ], $divisionIds, $positionIds);

        // Finance Officer
        $financeOfficer = createUserWithProfile([
            'name'       => 'Finance Officer',
            'email'      => 'financeofficer@example.com',
            'role'       => 'finance_officer',
            'company_id' => $company->id,
        ], $divisionIds, $positionIds);

        // Employee
        $employee = createUserWithProfile([
            'name'       => 'Employee Test',
            'email'      => 'employee@example.com',
            'role'       => 'employee',
            'company_id' => $company->id,
        ], $divisionIds, $positionIds);

        // Support Staff
        $supportStaff = createUserWithProfile([
            'name'       => 'Support Staff',
            'email'      => 'supportstaff@example.com',
            'role'       => 'support_staff',
            'company_id' => $company->id,
        ], $divisionIds, $positionIds);

        // 5. 20 User biasa
        $users = User::factory(20)->forCompany($company)->create()->each(function ($user) use ($divisionIds, $positionIds) {
            UserProfile::factory()->create([
                'user_id' => $user->id,
                'division_id' => $divisionIds[array_rand($divisionIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
            ]);
        });

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
        $rooms = MeetingRoom::factory(5)->create([
            'company_id' => $company->id,
        ]);

        // 10. Meeting reservations + participants + requests
        MeetingRoomReservation::factory(20)->make()->each(function ($reservation) use ($allUsers, $rooms, $divisionIds, $positionIds, $company) {
            $reservation->user_id = $allUsers->random()->id;
            $reservation->meeting_room_id = $rooms->random()->id;
            $reservation->company_id = $company->id;
            $reservation->save();

            // Internal participants (user terdaftar)
            $internalParticipants = $allUsers
                ->where('id', '!=', $reservation->user_id)
                ->shuffle()
                ->take(rand(2, min(5, $allUsers->count() - 1)));

            foreach ($internalParticipants as $participant) {
                MeetingParticipant::create([
                    'reservation_id' => $reservation->id,
                    'user_id' => $participant->id,
                    'company_id' => $reservation->company_id,
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
                    'company_id' => $reservation->company_id,
                ]);
            }

            // Meeting request
            MeetingRequest::factory()
                ->for($reservation, 'reservation')
                ->forCompany($reservation->company)
                ->create();
        });
    }
}
