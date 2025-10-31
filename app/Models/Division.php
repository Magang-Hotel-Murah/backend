<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;

class Division extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'name'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class, 'division_position')
            ->using(DivisionPosition::class)
            ->withTimestamps();
    }

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
}
