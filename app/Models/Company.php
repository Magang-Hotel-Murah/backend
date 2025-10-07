<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'invite_url',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function meetingRooms()
    {
        return $this->hasMany(MeetingRoom::class);
    }

    public function meetingRoomReservations()
    {
        return $this->hasMany(MeetingRoomReservation::class);
    }

    public function meetingRequests()
    {
        return $this->hasMany(MeetingRequest::class);
    }

    public function meetingParticipants()
    {
        return $this->hasMany(MeetingParticipant::class);
    }
}
