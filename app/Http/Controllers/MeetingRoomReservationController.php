<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MeetingRoomReservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Meeting Room Reservations
 */
class MeetingRoomReservationController extends Controller
{
    public function index()
    {
        $reservations = MeetingRoomReservation::with(
            'user:id,name',
            'user.profile:id,user_id,division_id,position_id',
            'user.profile.division:id,name',
            'user.profile.position:id,name',
            'room:id,name'
        )
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($reservations);
    }

    public function show($id)
    {
        $reservation = MeetingRoomReservation::with(
            'user:id,name',
            'user.profile:id,user_id,division_id,position_id',
            'user.profile.division:id,name',
            'user.profile.position:id,name',
            'room:id,name'
        )
            ->where('meeting_room_id', $id)
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($reservation);
    }

    public function store(Request $request)
    {
        $request->validate([
            'meeting_room_id' => 'required|exists:meeting_rooms,id',
            'start_time'      => 'required|date|after:now',
            'end_time'        => 'required|date|after:start_time',
        ]);

        // cek apakah sudah ada reservasi approved yang bentrok
        $hasApproved = MeetingRoomReservation::where('meeting_room_id', $request->meeting_room_id)
            ->where('status', 'approved')
            ->conflict($request->start_time, $request->end_time)
            ->exists();

        if ($hasApproved) {
            return response()->json([
                'message' => 'Ruangan sudah ada reservasi yang disetujui pada waktu tersebut.'
            ], 422);
        }

        $reservation = MeetingRoomReservation::create([
            'meeting_room_id' => $request->meeting_room_id,
            'user_id'         => Auth::id(),
            'start_time'      => $request->start_time,
            'end_time'        => $request->end_time,
            'status'          => 'pending',
        ]);

        return response()->json($reservation, 201);
    }


    public function updateStatus(Request $request, $id)
    {
        $reservation = MeetingRoomReservation::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $reservation->status = $request->status;
        $reservation->save();

        // kalau status = approved → reject semua reservasi lain yang bentrok
        if ($reservation->status === 'approved') {
            MeetingRoomReservation::where('meeting_room_id', $reservation->meeting_room_id)
                ->where('id', '!=', $reservation->id)
                ->where('status', 'pending')
                ->conflict($reservation->start_time, $reservation->end_time)
                ->update(['status' => 'rejected']);
        }

        return response()->json($reservation, 200);
    }
}
