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

    public function scopeConflict($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_time', [$start, $end])
                ->orWhereBetween('end_time', [$start, $end])
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_time', '<=', $start)
                        ->where('end_time', '>=', $end);
                });
        });
    }
}
