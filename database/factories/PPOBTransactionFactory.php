<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PPOBTransactionFactory extends Factory
{
    public function definition(): array
    {
        $game_name = fake()->randomElement(['PUBG', 'MOBILE_LEGENDS', 'VALORANT', 'FREE_FIRE']);
        $pulsa_providers = fake()->randomElement(['TELKOMSEL', 'XL', 'INDOSAT', 'TRI', 'SMARTFREN']);
        $pdam_kota = fake()->randomElement(['BANDUNG', 'JAKARTA', 'SURABAYA']);

        $service_type = fake()->randomElement([
            'PLN_PASCABAYAR',
            "PULSA_{$pulsa_providers}",
            "PDAM_KOTA_{$pdam_kota}",
            "GAME_{$game_name}",
        ]);

        $item_name_map = [
            'GAME_PUBG' => '2000 Cash',
            'GAME_MOBILE_LEGENDS' => '1000 Diamond',
            'GAME_VALORANT' => '5000 VP',
            'GAME_FREE_FIRE' => '3000 Diamond',
            'PULSA_TELKOMSEL' => 'Pulsa Telkomsel 50K',
            'PULSA_XL' => 'Pulsa XL 50K',
            'PULSA_INDOSAT' => 'Pulsa Indosat 50K',
            'PULSA_TRI' => 'Pulsa Tri 50K',
            'PULSA_SMARTFREN' => 'Pulsa Smartfren 50K',
            'PDAM_KOTA_BANDUNG' => 'Tagihan PDAM Bandung',
            'PDAM_KOTA_JAKARTA' => 'Tagihan PDAM Jakarta',
            'PDAM_KOTA_SURABAYA' => 'Tagihan PDAM Surabaya',
            'PLN_PASCABAYAR' => 'Tagihan PLN Pascabayar',
        ];

        $item_name = $item_name_map[$service_type] ?? null;

        return [
            'user_id' => User::factory(),
            'invoice_number' => 'INV-' . now()->timestamp . '-' . Str::upper(Str::random(6)),
            'service_type' => $service_type,
            'customer_number' => fake()->bothify('########'),
            'item_name' => $item_name,
            'total_price' => fake()->randomElement([50000, 100000, 200000, 500000]),
            'currency' => 'IDR',
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'expired']),
        ];
    }
}
