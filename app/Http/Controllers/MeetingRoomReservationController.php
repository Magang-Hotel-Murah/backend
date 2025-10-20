<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MeetingRoomReservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingRoom;
use App\Models\Scopes\CompanyScope;
use App\Models\Company;
use Carbon\Carbon;
use App\Http\Services\MeetingRoomReservationService;

/**
 * @group Meeting Room Reservations
 */
class MeetingRoomReservationController extends Controller
{
    protected $reservationService;

    public function __construct(MeetingRoomReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function index(Request $request)
    {
        try {
            $reservations = $this->reservationService->getReservations($request);

            return response()->json([
                'message' => 'Daftar reservasi berhasil diambil.',
                'data' => $reservations,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function meetingDisplay(Request $request)
    {
        try {
            $data = $this->reservationService->getMeetingDisplay($request);
            return response()->json([
                'message' => 'Data rapat berhasil diambil.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function indexByRoom($room_id)
    {
        try {
            $reservations = $this->reservationService->getReservationsByRoom($room_id);

            return response()->json([
                'message' => 'Daftar reservasi berhasil diambil.',
                'data' => $reservations,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function show($reservation_id)
    {
        try {
            $reservation = $this->reservationService->getReservationDetails($reservation_id);

            return response()->json([
                'message' => 'Detail reservasi berhasil diambil.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reservationService->rules());

        try {
            $result = $this->reservationService->createReservation($validated);
            return response()->json([
                'message' => 'Reservasi berhasil dibuat.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate(array_merge(
            $this->reservationService->rules(),
            ['status' => 'sometimes|in:cancelled']
        ));

        $conflictMessage = $this->reservationService->checkConflict(
            $validated['meeting_room_id'],
            $validated['start_time'],
            $validated['end_time'],
            $id
        );

        if ($conflictMessage) {
            return response()->json([
                'message' => "Tidak dapat membuat reservasi. {$conflictMessage}"
            ], 422);
        }

        try {
            $reservation = $this->reservationService->updateReservation($validated, $id);
            return response()->json([
                'message' => 'Reservasi diperbarui.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        try {
            $result = $this->reservationService->updateStatus($validated, $id);
            return response()->json([
                'message' => 'Status reservasi diperbarui.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
