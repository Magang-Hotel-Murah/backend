<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MeetingRoomReservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingRoom;

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

    public function show($room_id)
    {
        $reservation = MeetingRoomReservation::with(
            'user:id,name',
            'user.profile:id,user_id,division_id,position_id',
            'user.profile.division:id,name',
            'user.profile.position:id,name',
            'room:id,name'
        )
            ->where('meeting_room_id', $room_id)
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($reservation);
    }

    public function detail($reservation_id)
    {
        $reservation = MeetingRoomReservation::select('id', 'user_id', 'meeting_room_id', 'title', 'description', 'start_time', 'end_time', 'status', 'participants')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,division_id,position_id',
                'user.profile.division:id,name',
                'user.profile.position:id,name',
                'room:id,name',
                'participants:id,reservation_id,user_id,name,email,whatsapp_number',
                'participants.user:id,name,email',
                'participants.user.profile:user_id,phone',
                'request:reservation_id,funds_amount,funds_reason,snacks,equipment',
            ])
            ->findOrFail($reservation_id);

        return response()->json($reservation);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        if ($conflictMessage = $this->checkConflict(
            $validated['meeting_room_id'],
            $validated['start_time'],
            $validated['end_time']
        )) {
            return response()->json(['message' => "Tidak dapat membuat reservasi. {$conflictMessage}"], 422);
        }

        $reservation = DB::transaction(function () use ($validated) {
            $reservation = MeetingRoomReservation::create([
                'meeting_room_id' => $validated['meeting_room_id'],
                'user_id'         => Auth::id(),
                'title'           => $validated['title'],
                'description'     => $validated['description'] ?? null,
                'start_time'      => $validated['start_time'],
                'end_time'        => $validated['end_time'],
                'participants'    => count($validated['participants'] ?? []),
                'status'          => 'pending',
            ]);

            $this->saveParticipants($reservation, $validated['participants'] ?? []);
            $this->saveRequest($reservation, $validated['request'] ?? []);

            return $reservation->load(['participants', 'request', 'room']);
        });

        return response()->json([
            'message' => 'Reservasi ruangan berhasil dibuat.',
            'data' => $reservation,
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'rejection_reason' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($request, $id) {
            $reservation = MeetingRoomReservation::with('room')->findOrFail($id);
            $room = $reservation->room;

            if ($request->status === 'approved') {
                if ($conflictMessage = $this->checkConflict(
                    $reservation->meeting_room_id,
                    $reservation->start_time,
                    $reservation->end_time,
                    $reservation->id
                )) {
                    return response()->json(['message' => "Tidak dapat menyetujui reservasi. {$conflictMessage}"], 422);
                }

                $reservation->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'rejection_reason' => null,
                ]);

                $rejectionReason = $request->rejection_reason
                    ?: 'Bentrok dengan jadwal yang telah disetujui otomatis oleh sistem.';

                MeetingRoomReservation::where('status', 'pending')
                    ->conflict($reservation->meeting_room_id, $reservation->start_time, $reservation->end_time, $reservation->id)
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => $rejectionReason,
                        'approved_by' => Auth::id(),
                    ]);
            } elseif ($request->status === 'rejected') {
                $reservation->update([
                    'status' => 'rejected',
                    'rejection_reason' => $request->rejection_reason ?? 'Ditolak oleh HR.',
                    'approved_by' => Auth::id(),
                ]);
            } else {
                $reservation->update([
                    'status' => 'pending',
                    'approved_by' => null,
                    'rejection_reason' => null,
                ]);
            }

            $reservation->refresh()->load(['participants', 'request', 'room']);

            return response()->json([
                'message' => 'Status reservasi diperbarui.',
                'data' => $reservation,
            ]);
        });
    }

    private function rules(): array
    {
        return [
            'meeting_room_id'       => 'required|exists:meeting_rooms,id',
            'title'                 => 'required|string',
            'description'           => 'nullable|string',
            'start_time'            => 'required|date|after:now',
            'end_time'              => 'required|date|after:start_time',

            'participants'                => 'nullable|array',
            'participants.*.user_id'      => 'nullable|exists:users,id',
            'participants.*.name'         => 'nullable|string',
            'participants.*.email'        => 'nullable|email',
            'participants.*.whatsapp_number' => 'nullable|string',

            'request'                     => 'nullable|array',
            'request.funds_amount'        => 'nullable|numeric|min:0',
            'request.funds_reason'        => 'nullable|string|required_with:request.funds_amount',
            'request.snacks'              => 'nullable|array',
            'request.equipment'           => 'nullable|array',
        ];
    }

    private function checkConflict(int $roomId, string $start, string $end, int $excludeId = null): ?string
    {
        $conflictQuery = MeetingRoomReservation::where('status', 'approved')
            ->conflict($roomId, $start, $end, $excludeId);

        if (!$conflictQuery->exists()) {
            return null;
        }

        $conflicting = $conflictQuery->first();
        $room = MeetingRoom::with('parent', 'children')->find($roomId);
        $conflictRoom = $conflicting->room;

        if ($room->parent_id && $room->parent_id === $conflictRoom->id) {
            return "Ruangan ini adalah bagian dari ruangan $conflictRoom->name yang sudah terpakai.";
        } elseif ($room->id === $conflictRoom->parent_id) {
            return "Salah satu subruangan dari $room->name sudah memiliki jadwal di waktu yang sama.";
        } elseif ($room->id === $conflictRoom->id) {
            return "Ruangan $room->name sudah memiliki reservasi yang disetujui pada waktu tersebut.";
        }

        return "Ruangan ini sudah memiliki reservasi yang bentrok.";
    }

    private function saveParticipants(MeetingRoomReservation $reservation, array $participants): void
    {
        foreach ($participants as $p) {
            $data = ['reservation_id' => $reservation->id];
            if (isset($p['user_id'])) {
                $data['user_id'] = $p['user_id'];
            } else {
                $data = array_merge($data, [
                    'name' => $p['name'] ?? null,
                    'email' => $p['email'] ?? null,
                    'whatsapp_number' => $p['whatsapp_number'] ?? null,
                ]);
            }
            MeetingParticipant::create($data);
        }
    }

    private function saveRequest(MeetingRoomReservation $reservation, array $requestData): void
    {
        if (empty($requestData)) return;

        MeetingRequest::create([
            'reservation_id' => $reservation->id,
            'funds_amount'   => $requestData['funds_amount'] ?? null,
            'funds_reason'   => $requestData['funds_reason'] ?? null,
            'snacks'         => $requestData['snacks'] ?? [],
            'equipment'      => $requestData['equipment'] ?? [],
        ]);
    }
}
