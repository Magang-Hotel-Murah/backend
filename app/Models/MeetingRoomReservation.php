<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;

class MeetingRoomReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'meeting_room_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'rejection_reason',
        'approved_by',
        'status',
        'company_id',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $user = Auth::user();
            if ($user && $user->role !== 'super_admin') {
                $model->company_id = $user->company_id;
            }
        });
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function room()
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function request()
    {
        return $this->hasOne(MeetingRequest::class, 'reservation_id');
    }

    public function participants()
    {
        return $this->hasMany(MeetingParticipant::class, 'reservation_id');
    }

    public function scopeConflict($query, $roomId, $start, $end, $excludeId = null)
    {
        $room = MeetingRoom::find($roomId);

        if (!$room) return $query;

        $relatedRoomIds = collect([$roomId]);

        if ($room->parent_id) {
            $relatedRoomIds->push($room->parent_id);
        } else {
            $relatedRoomIds = $relatedRoomIds->merge($room->children()->pluck('id'));
        }

        return $query
            ->whereIn('meeting_room_id', $relatedRoomIds)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start, $end])
                    ->orWhereBetween('end_time', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_time', '<=', $start)
                            ->where('end_time', '>=', $end);
                    });
            });
    }
}
