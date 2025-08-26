<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transactionable_id',
        'transactionable_type',
        'external_id',
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
