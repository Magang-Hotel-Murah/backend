<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\User;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            'company_id' => null, // default
        ];
    }

    public function admin(): static
    {
        return $this->state(function () {
            $company = Company::factory()->create();

            return [
                'role' => 'admin',
                'company_id' => $company->id,
            ];
        });
    }

    public function forCompany(Company $company): static
    {
        return $this->state([
            'role' => 'user',
            'company_id' => $company->id,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
