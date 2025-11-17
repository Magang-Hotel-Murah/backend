<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Company;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $code = strtoupper(Str::random(6));
        $host = env('FRONTEND_URL, http://localhost:5173');
        return [
            'name' => fake()->company(),
            'code' => $code, // kode unik 6 huruf
            'display_url' => $host . '/api/meeting-display/' . $code,
        ];
    }
}
