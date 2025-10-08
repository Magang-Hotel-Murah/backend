<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $room = MeetingRoom::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:main,sub',
            'location' => 'sometimes|string|max:255',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('meeting_rooms', 'id'),
                function ($attribute, $value, $fail) use ($request, $room) {
                    // ambil type dari request kalau dikirim, kalau tidak ambil dari DB
                    $type = $request->input('type', $room->type);

                    if ($type === 'main' && $value !== null) {
                        $fail('Ruangan utama tidak boleh memiliki parent_id.');
                    }

                    if ($type === 'sub' && $value === null) {
                        $fail('Ruangan sub harus memiliki parent_id.');
                    }
                }
            ],
            'facilities' => 'sometimes|array',
            'facilities.*' => 'string|max:100',
            'capacity' => 'sometimes|integer|min:1',
        ]);

        $data = $request->only([
            'name',
            'type',
            'location',
            'parent_id',
            'facilities',
            'capacity',
        ]);

        if (isset($data['facilities']) && is_array($data['facilities'])) {
            $data['facilities'] = json_encode($data['facilities']);
        }

        $room->update($data);

        return response()->json([
            'message' => 'Data ruangan berhasil diperbarui',
            'data' => $room->fresh(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:main,sub',
            'location' => 'nullable|string|max:255',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('meeting_rooms', 'id'),
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->type === 'main' && $value !== null) {
                        $fail('Ruangan utama tidak boleh memiliki parent_id.');
                    }
                    if ($request->type === 'sub' && $value === null) {
                        $fail('Ruangan sub harus memiliki parent_id.');
                    }
                }
            ],
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:100',
            'capacity' => 'required|integer|min:1',
        ]);

        $data = $request->only([
            'name',
            'type',
            'location',
            'parent_id',
            'facilities',
            'capacity'
        ]);

        if (isset($data['facilities']) && is_array($data['facilities'])) {
            $data['facilities'] = json_encode($data['facilities']);
        }

        $room = MeetingRoom::create($data);

        return response()->json([
            'message' => 'Ruangan berhasil ditambahkan',
            'data' => $room,
        ], 201);
    }

    public function destroy($id)
    {
        $room = MeetingRoom::find($id);
        $room->delete();
        return response()->json(null, 204);
    }
}
