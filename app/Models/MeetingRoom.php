<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'capacity',
        'facilities',
        'location',
        'type',
        'parent_id',
        'company_id',
    ];

    protected $casts = [
        'facilities' => 'array',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

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
