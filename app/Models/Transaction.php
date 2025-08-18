<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transactionable_id',
        'transactionable_type',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'transaction_date',
    ];

    public function transactionable()
    {
        return $this->morphTo();
    }
}
