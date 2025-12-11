<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Services\CloudinaryService;
use Illuminate\Support\Facades\Log;

/**
 * @group Meeting Rooms
 */
class MeetingRoomController extends Controller
{
    protected $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:main,sub',
            'facilities_only' => 'sometimes',
        ]);

        if ($request->boolean('facilities_only')) {
            $rooms = MeetingRoom::pluck('facilities')->toArray();

            $facilities = collect($rooms)
                ->flatten()
                ->unique()
                ->values()
                ->all();

            return response()->json(['facilities' => $facilities]);
        }

        if ($request->has('type')) {
            $rooms = MeetingRoom::where('type', $validated['type'])->get();
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
        $room = MeetingRoom::find($id);

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
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
            'images_to_remove' => 'nullable|array',
            'images_to_remove.*' => 'string',
            'replace_images' => 'nullable',
        ]);

        $data = $request->only([
            'name',
            'type',
            'location',
            'parent_id',
            'facilities',
            'capacity',
        ]);

        $existingImages = $room->images ?? [];

        if ($request->filled('images_to_remove')) {
            $imagesToRemove = $request->input('images_to_remove', []);
            $existingImages = array_values(array_diff($existingImages, $imagesToRemove));

            foreach ($imagesToRemove as $url) {
                $this->cloudinary->destroy($url);
            }
        }

        $uploadedUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $upload = $this->cloudinary->upload($image, 'meeting_rooms');
                $uploadedUrls[] = $upload['url'] ?? $upload;
            }
        }

        if ($request->replace_images === 'true') {
            $data['images'] = $uploadedUrls;
        } else {
            $data['images'] = array_merge($existingImages, $uploadedUrls);
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
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only([
            'name',
            'type',
            'location',
            'parent_id',
            'facilities',
            'capacity'
        ]);

        $uploadedUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $upload = $this->cloudinary->upload($image, 'meeting_rooms');
                $uploadedUrls[] = $upload['url'] ?? $upload;
            }
        }

        if (!empty($uploadedUrls)) {
            $data['images'] = $uploadedUrls;
        }

        $room = MeetingRoom::create($data);

        return response()->json([
            'message' => 'Ruangan berhasil ditambahkan',
            'data' => $room,
        ], 201);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        $room = MeetingRoom::withTrashed()
            ->where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$room) {
            return response()->json([
                'message' => 'Ruangan tidak ditemukan untuk perusahaan Anda.'
            ], 404);
        }

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
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'participants_count' => 'required|integer|min:1',
            'facilities' => 'array',
            'room_id' => 'nullable|integer|exists:meeting_rooms,id',
        ]);

        if (!empty($validated['start_time']) && $validated['date'] === now()->toDateString()) {
            $startDateTime = Carbon::parse("{$validated['date']} {$validated['start_time']}");
            if ($startDateTime->lt(now())) {
                return response()->json([
                    'message' => 'Waktu mulai tidak boleh di masa lalu.',
                ], 422);
            }
        }

        $hasRoomId = !empty($validated['room_id']);
        $hasTime = !empty($validated['start_time']) && !empty($validated['end_time']);
        $startDateTime = $hasTime ? Carbon::parse("{$validated['date']} {$validated['start_time']}") : null;
        $endDateTime = $hasTime ? Carbon::parse("{$validated['date']} {$validated['end_time']}") : null;

        $rooms = MeetingRoom::with(['reservations' => function ($q) use ($validated, $hasTime, $startDateTime, $endDateTime, $hasRoomId) {
            $q->select('id', 'meeting_room_id', 'title', 'start_time', 'end_time')
                ->where('status', 'approved')
                ->whereDate('start_time', $validated['date']);

            if ($hasRoomId) {
                $q->where('meeting_room_id', $validated['room_id']);
            }

            if ($hasTime) {
                $q->where(function ($q2) use ($startDateTime, $endDateTime) {
                    $q2->whereBetween('start_time', [$startDateTime, $endDateTime])
                        ->orWhereBetween('end_time', [$startDateTime, $endDateTime])
                        ->orWhere(function ($q3) use ($startDateTime, $endDateTime) {
                            $q3->where('start_time', '<=', $startDateTime)
                                ->where('end_time', '>=', $endDateTime);
                        });
                });
            }
        }])
            ->select('id', 'name', 'capacity', 'facilities', 'location', 'type', 'company_id', 'images')
            ->when($hasRoomId, function ($q) use ($validated) {
                $q->where('id', $validated['room_id']);
            })
            ->where('capacity', '>=', $validated['participants_count'])
            ->when(!empty($validated['facilities']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    foreach ($validated['facilities'] as $facility) {
                        $q->orWhereJsonContains('facilities', $facility);
                    }
                });
            })
            ->orderBy('capacity', 'asc')
            ->get();

        $generateFreeSlots = function ($reservations, $date, $dayStart = '00:00', $dayEnd = '23:59', $bufferMinutes = 15) {
            $slots = [];

            if ($date === now()->toDateString()) {
                $current = now()->copy()->ceilMinutes(5);
                $dayEndTime = Carbon::parse("{$date} {$dayEnd}");
                if ($current->gte($dayEndTime)) {
                    return [];
                }
            } else {
                $current = Carbon::parse("{$date} {$dayStart}");
            }

            $dayEndTime = Carbon::parse("{$date} {$dayEnd}");

            $reservations = $reservations
                ->filter(fn($res) => Carbon::parse($res->start_time)->toDateString() === $date)
                ->sortBy('start_time');

            foreach ($reservations as $res) {
                $resStart = Carbon::parse($res->start_time)->subMinutes($bufferMinutes);
                $resEnd = Carbon::parse($res->end_time)->addMinutes($bufferMinutes);

                if ($current < $resStart) {
                    $diffMinutes = $current->diffInMinutes($resStart);
                    if ($diffMinutes >= 15) {
                        $slots[] = [
                            'start_time' => $current->format('H:i'),
                            'end_time' => $resStart->format('H:i'),
                        ];
                    }
                }

                if ($current < $resEnd) {
                    $current = $resEnd->copy();
                }
            }

            if ($current < $dayEndTime) {
                $diffMinutes = $current->diffInMinutes($dayEndTime);
                if ($diffMinutes >= 15) {
                    $slots[] = [
                        'start_time' => $current->format('H:i'),
                        'end_time' => $dayEndTime->format('H:i'),
                    ];
                }
            }

            return $slots;
        };

        $rooms->each(function ($room) use ($generateFreeSlots, $validated) {
            $room->free_slots = $generateFreeSlots($room->reservations, $validated['date']);
        });

        if ($rooms->isEmpty()) {
            $largestRoom = MeetingRoom::with(['reservations' => function ($q) use ($validated) {
                $q->select('id', 'meeting_room_id', 'title', 'start_time', 'end_time')
                    ->where('status', 'approved')
                    ->whereDate('start_time', $validated['date']);
            }])
                ->select('id', 'name', 'capacity', 'facilities', 'location', 'type', 'company_id', 'images')
                ->orderBy('capacity', 'desc')
                ->first(['id', 'name', 'capacity', 'facilities', 'location', 'type', 'company_id']);

            $largestRoom->free_slots = $generateFreeSlots($largestRoom->reservations, $validated['date']);

            return response()->json([
                'message' => "Tidak ada ruangan yang sesuai. Ruangan terbesar saat ini memiliki kapasitas {$largestRoom->capacity} orang.",
                'data' => [
                    'date' => $validated['date'],
                    'rooms' => [$largestRoom],
                ],
            ]);
        }

        return response()->json([
            'message' => 'Ruangan tersedia.',
            'data' => [
                'date' => $validated['date'],
                'rooms' => $rooms,
            ],
        ]);
    }
}
