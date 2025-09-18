<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class MeetingRoomReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_room_id',
        'user_id',
        'start_time',
        'end_time',
        'status',
    ];

    public function room()
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
