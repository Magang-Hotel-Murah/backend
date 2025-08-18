<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // List semua transaksi
    public function index()
    {
        return response()->json(Transaction::with('transactionable')->get(), 200);
    }

    // Detail transaksi
    public function show($id)
    {
        $transaction = Transaction::with('transactionable')->find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($transaction, 200);
    }

    // Buat transaksi baru
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

    // Update transaksi
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

    // Hapus transaksi
    public function destroy($id)
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted'], 200);
    }
}
