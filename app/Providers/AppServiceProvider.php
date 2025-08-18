<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mapping type polymorphic biar lebih singkat
        Relation::morphMap([
            'hotel' => \App\Models\HotelReservation::class,
            // 'flight' => \App\Models\FlightReservation::class,
        ]);
    }
}
