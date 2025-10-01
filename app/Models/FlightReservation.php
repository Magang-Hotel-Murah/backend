<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightReservation extends Model
{
    use HasFactory;

    protected $casts = [
        'passenger_details' => 'array',
    ];

    protected $table = 'flight_reservations';

    protected $fillable = [
        'user_id',
        'booking_code',
        'flight_number',
        'origin',
        'destination',
        'departure_time',
        'arrival_time',
        'passenger_details',
        'total_price',
        'currency',
        'status',
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
