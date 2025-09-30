<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'flight_number',
        'origin',
        'destination',
        'departure_time',
        'arrival_time',
        'passenger_count',
        'price',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
