<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capacity',
        'facilities',
        'status',
        'type',
        'parent_id',
    ];

    protected $casts = [
        'facilities' => 'array',
    ];


    public function reservations()
    {
        return $this->hasMany(MeetingRoomReservation::class);
    }

    public function parent()
    {
        return $this->belongsTo(MeetingRoom::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MeetingRoom::class, 'parent_id');
    }
}
