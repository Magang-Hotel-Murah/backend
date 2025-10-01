<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelReservation extends Model
{
    use HasFactory;

    protected $casts = [
        'guest_details' => 'array',
    ];

    protected $table = 'hotel_reservations';

    protected $fillable = [
        'user_id',
        'booking_code',
        'hotel_name',
        'hotel_id',
        'check_in_date',
        'check_out_date',
        'guest_details',
        'currency',
        'total_price',
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
