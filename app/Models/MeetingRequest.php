<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;

class MeetingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'funds_amount',
        'funds_reason',
        'snacks',
        'equipment',
        'company_id',
    ];

    protected $casts = [
        'snacks' => 'array',
        'equipment' => 'array',
        'funds_amount' => 'decimal:2',
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
}
