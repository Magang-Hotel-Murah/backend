<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Transaction;
use App\Observers\TransactionObserver;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap([
            'hotel' => \App\Models\HotelReservation::class,
            // 'flight' => \App\Models\FlightReservation::class,
        ]);

        Transaction::observe(TransactionObserver::class);
    }
}
