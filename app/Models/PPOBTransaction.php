<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PPOBTransaction extends Model
{
    use HasFactory;

    protected $table = 'ppob_transactions';

    protected $fillable = [
        'user_id',
        'service_type',
        'customer_number',
        'amount',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
