<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;

use function Laravel\Prompts\select;

/**
 * @group Meeting Rooms
 */
class MeetingRoomController extends Controller
{
    public function index()
    {
        $rooms = MeetingRoom::all();
        return response()->json($rooms);
    }

    public function show($id)
    {
        $room = MeetingRoom::find($id);
        return response()->json($room);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $room = MeetingRoom::find($id);
        $room->update($request->only('name', 'description'));
        return response()->json($room);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $room = MeetingRoom::create($request->only('name', 'description'));
        return response()->json($room, 201);
    }

    public function destroy($id)
    {
        $room = MeetingRoom::find($id);
        $room->delete();
        return response()->json(null, 204);
    }
}
