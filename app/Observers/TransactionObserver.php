<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\HotelReservation;
use App\Models\FlightReservation;
use App\Models\PPOBTransaction;

class TransactionObserver
{
    /**
     * Mapping status per tipe transaksi.
     */
    protected array $statusMap = [
        'hotel' => [
            'paid'   => 'completed',
            'failed' => 'failed',
            'expired' => 'expired',
            'default' => 'pending',
        ],
        'flight' => [
            'paid'   => 'completed',
            'failed' => 'failed',
            'expired' => 'expired',
            'default' => 'pending',
        ],
        'ppob' => [
            'paid'   => 'completed',
            'failed' => 'failed',
            'expired' => 'expired',
            'default' => 'pending',
        ],
    ];

    /**
     * Sinkronisasi status.
     */
    protected function syncStatus(Transaction $transaction): void
    {
        $type = $transaction->transactionable_type;
        $related = $transaction->transactionable; // morphTo langsung resolve model

        if (!$related || !isset($this->statusMap[$type])) {
            return;
        }

        $status = $this->statusMap[$type][$transaction->payment_status]
            ?? $this->statusMap[$type]['default'];

        $related->update(['status' => $status]);
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->syncStatus($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $this->syncStatus($transaction);
    }
}
