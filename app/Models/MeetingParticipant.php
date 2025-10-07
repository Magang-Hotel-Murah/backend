<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;

class MeetingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'user_id',
        'name',
        'email',
        'whatsapp_number',
        'company_id',
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

    public function reservation()
    {
        return $this->belongsTo(MeetingRoomReservation::class, 'reservation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
