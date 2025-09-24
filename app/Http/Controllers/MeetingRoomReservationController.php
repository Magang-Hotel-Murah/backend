<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MeetingRoomReservation;
use Illuminate\Support\Facades\Auth;

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
            'meeting_room_id'   => 'required|exists:meeting_rooms,id',
            'start_time'  => 'required|date|after:now',
            'end_time'    => 'required|date|after:start_time',
        ]);

        $reservation = MeetingRoomReservation::create([
            'meeting_room_id'  => $request->meeting_room_id,
            'user_id'    => Auth::id(),
            'start_time' => $request->start_time,
            'end_time'   => $request->end_time,
            'status'     => 'pending',
        ]);

        return response()->json($reservation, 201);
    }

    public function updateStatus(Request $request, MeetingRoomReservation $reservation)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $reservation->update(['status' => $request->status]);

        return response()->json($reservation);
    }

    public function destroy(MeetingRoomReservation $reservation)
    {
        $reservation->delete();

        return response()->json(null, 204);
    }
}
