<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\HotelReservation;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->transactionable_type === 'hotel') {
            $reservation = HotelReservation::find($transaction->transactionable_id);

            if ($reservation) {
                $status = match ($transaction->payment_status) {
                    'paid' => 'confirmed',
                    'failed' => 'cancelled',
                    default => 'pending',
                };

                $reservation->update(['status' => $status]);
            }
        }
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        if ($transaction->transactionable_type === 'hotel') {
            $reservation = HotelReservation::find($transaction->transactionable_id);

            if ($reservation) {
                $status = match ($transaction->payment_status) {
                    'paid' => 'confirmed',
                    'failed' => 'cancelled',
                    default => 'pending',
                };

                if ($reservation->status !== $status) {
                    $reservation->update(['status' => $status]);
                }
            }
        }
    }
}
