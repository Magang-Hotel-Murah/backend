<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'flight' => \App\Models\FlightReservation::class,
            'ppob' => \App\Models\PPOBTransaction::class,
        ]);

        Transaction::observe(TransactionObserver::class);

        Carbon::setLocale('id');

        DB::listen(function ($query) {
            Log::info('SQL: ' . json_encode([
                'query' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]));
        });
    }
}
