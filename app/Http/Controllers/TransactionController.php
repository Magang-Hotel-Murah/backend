<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

use function PHPSTORM_META\type;

/**
 * @group Transactions
 */
class TransactionController extends Controller
{
    /**
     * Get all transactions
     *
     * @group Transactions
     *
     * @queryParam type string Optional. Filter transactions by type. Available: hotel, flight, ppob. Example: hotel
     */
    public function index(Request $request)
    {
        $query = Transaction::with('transactionable');
        $type = $request->query('type');

        if ($type && in_array($type, ['hotel', 'flight', 'ppob'])) {
            $query->where('transactionable_type', $type);
        }

        return response()->json($query->get(), 200);
    }
    /**
     * Get /api/transactions/{id}
     *
     * @group Transactions
     *
     * @urlParam id string ID, external ID, booking code, or invoice number of the transaction. Example: 1
     */
    public function show($key, Request $request)
    {
        $transaction = Transaction::with([
            'transactionable',
            'transactionable.user'
        ])
            ->where('id', $key)
            ->orWhere('external_id', $key)
            ->orWhereHasMorph(
                'transactionable',
                ['hotel', 'flight'],
                function ($query) use ($key) {
                    $query->where('booking_code', $key);
                }
            )
            ->orWhereHasMorph(
                'transactionable',
                ['ppob'],
                function ($query) use ($key) {
                    $query->where('invoice_number', $key);
                }
            )
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        return response()->json($transaction, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transactionable_id' => 'required|integer',
            'transactionable_type' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string|max:5',
            'payment_method' => 'nullable|string|max:50',
            'payment_status' => 'in:unpaid,paid,failed',
            'transaction_date' => 'nullable|date',
        ]);

        $transaction = Transaction::create($validated);
        return response()->json($transaction, 201);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric',
            'currency' => 'sometimes|string|max:5',
            'payment_method' => 'nullable|string|max:50',
            'payment_status' => 'sometimes|in:unpaid,paid,failed',
            'transaction_date' => 'nullable|date',
        ]);

        $transaction->update($validated);

        return response()->json($transaction, 200);
    }

    public function destroy($id)
    {
        $transaction = Transaction::findOrFail($id);
        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully'
        ]);
    }
}
