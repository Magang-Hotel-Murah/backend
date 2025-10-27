<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
        $room = MeetingRoom::with(
            'company:id,code,name',
        )->find($id);
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

                    if ($room->type === 'main' && $type === 'sub') {
                        $hasSubRooms = MeetingRoom::where('parent_id', $room->id)->exists();
                        if ($hasSubRooms) {
                            $fail('Tidak bisa mengubah ruangan main menjadi sub karena masih memiliki sub-ruangan.');
                        }
                    }

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

        $room = MeetingRoom::withTrashed()->findOrFail($id);

        if ($room->type === 'main') {
            $hasSubRooms = MeetingRoom::where('parent_id', $room->id)->exists();
            if ($hasSubRooms) {
                return response()->json([
                    'message' => 'Tidak bisa menghapus ruangan main karena masih memiliki sub-ruangan.'
                ], 400);
            }
        }

        if (!$room->trashed()) {
            $room->delete();
            $message = 'Ruangan berhasil dihapus';
        } else {
            $room->restore();
            $message = 'Ruangan berhasil dipulihkan';
        }

        return response()->json([
            'message' => $message,
            'data' => $room->fresh(),
        ]);
    }

    public function searchAvailableRooms(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'participants_count' => 'required|integer|min:1',
            'facilities' => 'array',
        ]);

        $startDateTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $endDateTime = Carbon::parse($validated['date'] . ' ' . $validated['end_time']);

        $rooms = MeetingRoom::with(['reservations' => function ($q) use ($startDateTime, $endDateTime) {
            $q->where('status', 'approved')
                ->where(function ($q2) use ($startDateTime, $endDateTime) {
                    $q2->whereBetween('start_time', [$startDateTime, $endDateTime])
                        ->orWhereBetween('end_time', [$startDateTime, $endDateTime])
                        ->orWhere(function ($q3) use ($startDateTime, $endDateTime) {
                            $q3->where('start_time', '<=', $startDateTime)
                                ->where('end_time', '>=', $endDateTime);
                        });
                });
        }])
            ->select('id', 'name', 'capacity', 'facilities', 'location', 'type')
            ->where('capacity', '>=', $validated['participants_count'])
            ->when(!empty($validated['facilities']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    foreach ($validated['facilities'] as $facility) {
                        $q->orWhereJsonContains('facilities', $facility);
                    }
                });
            })
            ->get();

        $available = $rooms->filter(fn($r) => $r->reservations->isEmpty())->values();

        return response()->json([
            'message' => 'Ruangan tersedia',
            'data' => [
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'available_room_count' => $available->count(),
                'available_rooms' => $available,
            ],
        ]);
    }
}
