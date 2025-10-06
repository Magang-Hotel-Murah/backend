<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'funds_amount',
        'funds_reason',
        'snacks',
        'equipment',
    ];

    protected $casts = [
        'snacks' => 'array',
        'equipment' => 'array',
        'funds_amount' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(MeetingRoomReservation::class, 'reservation_id');
    }
}
