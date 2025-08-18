<?php

namespace App\Http\Controllers;

use App\Models\HotelReservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * List semua reservasi
     */
    public function index()
    {
        $reservations = HotelReservation::with('user', 'transactions')->get();
        return response()->json($reservations, 200);
    }

    /**
     * Tampilkan detail reservasi
     */
    public function show($id)
    {
        $reservation = HotelReservation::with('user', 'transactions')->find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        return response()->json($reservation, 200);
    }

    /**
     * Buat reservasi baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'hotel_name' => 'required|string|max:255',
            'amadeus_hotel_id' => 'required|string|max:255',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
            'adults' => 'required|integer|min:1',
            'status' => 'in:pending,confirmed,cancelled',
        ]);

        $reservation = HotelReservation::create($validated);

        return response()->json($reservation, 201);
    }

    /**
     * Update reservasi
     */
    public function update(Request $request, $id)
    {
        $reservation = HotelReservation::find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        $validated = $request->validate([
            'hotel_name' => 'sometimes|string|max:255',
            'amadeus_hotel_id' => 'sometimes|string|max:255',
            'check_in_date' => 'sometimes|date',
            'check_out_date' => 'sometimes|date|after_or_equal:check_in_date',
            'adults' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:pending,confirmed,cancelled',
        ]);

        $reservation->update($validated);

        return response()->json($reservation, 200);
    }

    /**
     * Hapus reservasi
     */
    public function destroy($id)
    {
        $reservation = HotelReservation::find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted'], 200);
    }
}
