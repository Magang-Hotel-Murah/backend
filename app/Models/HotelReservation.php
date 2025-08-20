<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelReservation extends Model
{
    use HasFactory;

    protected $table = 'hotel_reservations';

    protected $fillable = [
        'user_id',
        'hotel_name',
        'hotel_id',
        'check_in_date',
        'check_out_date',
        'currency',
        'total_price',
        'adults',
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
