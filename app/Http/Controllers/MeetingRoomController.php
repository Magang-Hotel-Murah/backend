<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

/**
 * @group Meeting Rooms
 */
class MeetingRoomController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:main,sub',
        ]);

        if ($request->has('type')) {
            $type = $validated['type'];
            $rooms = MeetingRoom::where('type', $type)->get();
            return response()->json($rooms);
        }

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
                    $type = $request->input('type', $room->type);
                    $user = Auth::user();
                    $companyId = $user->company_id ?? null;

                    if ($type === 'main' && $value !== null) {
                        $fail('Ruangan utama tidak boleh memiliki parent_id.');
                    }

                    if ($type === 'sub') {
                        if ($value === null) {
                            $fail('Ruangan sub harus memiliki parent_id.');
                            return;
                        }

                        $hasMain = MeetingRoom::where('company_id', $companyId)
                            ->where('type', 'main')
                            ->where('id', '!=', $room->id)
                            ->exists();

                        if (!$hasMain && $room->type !== 'main') {
                            $fail('Tidak bisa membuat ruangan sub sebelum ada ruangan main untuk company ini.');
                            return;
                        }

                        $parentRoom = MeetingRoom::find($value);
                        if ($parentRoom && $parentRoom->type !== 'main') {
                            $fail('Parent ruangan harus bertipe main.');
                        }
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
                    $user = Auth::user();
                    $companyId = $user->company_id ?? null;

                    if ($request->type === 'main' && $value !== null) {
                        $fail('Ruangan utama tidak boleh memiliki parent_id.');
                    }

                    if ($request->type === 'sub') {
                        if ($value === null) {
                            $fail('Ruangan sub harus memiliki parent_id.');
                        } else {
                            $hasMain = MeetingRoom::where('company_id', $companyId)
                                ->where('type', 'main')
                                ->exists();

                            if (!$hasMain) {
                                $fail('Tidak bisa membuat ruangan sub sebelum ada ruangan main untuk company ini.');
                            }

                            $parentRoom = MeetingRoom::find($value);
                            if ($parentRoom && $parentRoom->type !== 'main') {
                                $fail('Parent ruangan harus bertipe main.');
                            }
                        }
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
