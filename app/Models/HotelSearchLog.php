<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HotelSearchLog extends Model
{
    use HasFactory;

    protected $table = 'hotels_search_logs';

    protected $fillable = [
        'user_id',
        'search_type',
        'params',
    ];

    protected $casts = [
        'params' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
