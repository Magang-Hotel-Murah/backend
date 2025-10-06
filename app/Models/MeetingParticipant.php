<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'user_id',
        'name',
        'email',
        'whatsapp_number',
    ];

    public function reservation()
    {
        return $this->belongsTo(MeetingRoomReservation::class, 'reservation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
