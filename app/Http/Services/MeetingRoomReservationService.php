<?php

namespace App\Http\Services;

use App\Models\MeetingRoomReservation;
use App\Models\MeetingRequest;
use App\Models\MeetingRoom;
use App\Models\MeetingParticipant;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;

class MeetingRoomReservationService
{
    public function getReservations($request)
    {
        $perPage = $request->get('per_page', 10);

        $query = MeetingRoomReservation::with([
            'user:id,name',
            'room:id,name',
        ]);

        if ($request->has('user')) {
            $query->where('user_id', Auth::user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('start_time', 'asc');

        return $query->paginate($perPage);
    }

    public function getMeetingDisplay($request)
    {
        $company = Company::where('code', $request->company_code)->firstOrFail();
        $companyId = $company->id;
        $now = Carbon::now();

        $filter = $request->get('filter', 'day');

        switch ($filter) {
            case 'week':
                $startDate = $now->copy()->startOfWeek();
                $endDate   = $now->copy()->endOfWeek();
                break;
            case 'month':
                $startDate = $now->copy()->startOfMonth();
                $endDate   = $now->copy()->endOfMonth();
                break;
            case 'year':
                $startDate = $now->copy()->startOfYear();
                $endDate   = $now->copy()->endOfYear();
                break;
            default:
                $startDate = $now->copy()->startOfDay();
                $endDate   = $now->copy()->endOfDay();
                break;
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate   = Carbon::parse($request->end_date)->endOfDay();
        }

        $query = MeetingRoomReservation::withoutGlobalScope(CompanyScope::class)
            ->select('id', 'user_id', 'company_id', 'title', 'meeting_room_id', 'start_time', 'end_time', 'participants', 'status')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,division_id,position_id',
                'user.profile.division:id,name',
                'user.profile.position:id,name',
                'room:id,name',
                'company:id,code,name',
            ])
            ->where('company_id', $companyId)
            ->whereBetween('start_time', [$startDate, $endDate]);

        if (!$request->filled('status')) {
            $query->where('status', 'approved');
        }

        if ($request->filled('room_id')) {
            $query->where('meeting_room_id', $request->room_id);
        }

        if ($request->filled('division_id')) {
            $query->whereHas('user.profile', function ($q) use ($request) {
                $q->where('division_id', $request->division_id);
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;

            $query->when($status === 'ongoing', function ($q) use ($now) {
                $q->where('start_time', '<=', $now)
                    ->where('end_time', '>=', $now)
                    ->where('status', 'approved');
            });

            $query->when($status === 'upcoming', function ($q) use ($now) {
                $q->where('start_time', '>', $now)
                    ->where('status', 'approved');
            });

            $query->when($status === 'finished', function ($q) use ($now) {
                $q->where('end_time', '<', $now)
                    ->where('status', 'approved');
            });

            $query->when($status === 'cancelled', function ($q) {
                $q->where('status', 'cancelled');
            });
        }

        $reservations = $query->orderBy('start_time', 'asc')->get();

        return [
            'filter' => $filter,
            'date_range' => [
                'start' => $startDate->toDateTimeString(),
                'end'   => $endDate->toDateTimeString(),
            ],
            'total' => $reservations->count(),
            'reservations' => $reservations,
        ];
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
        if (empty($participants)) {
            return;
        }

        $reservation->participants()->delete();

        foreach ($participants as $p) {
            $data = ['reservation_id' => $reservation->id];
            if (isset($p['user_id'])) {
                $data['user_id'] = $p['user_id'];
            } else {
                $data = array_merge($data, [
                    'name'            => $p['name'] ?? null,
                    'email'           => $p['email'] ?? null,
                    'whatsapp_number' => $p['whatsapp_number'] ?? null,
                ]);
            }
            MeetingParticipant::create($data);
        }
    }

    private function saveRequest(MeetingRoomReservation $reservation, array $requestData): void
    {
        if (empty($requestData)) return;

        $existingRequest = $reservation->request;

        if ($existingRequest) {
            $existingRequest->update([
                'funds_amount'   => $requestData['funds_amount'] ?? null,
                'funds_reason'   => $requestData['funds_reason'] ?? null,
                'snacks'         => $requestData['snacks'] ?? [],
                'equipment'      => $requestData['equipment'] ?? [],
            ]);
        } else {
            MeetingRequest::create([
                'reservation_id' => $reservation->id,
                'company_id'     => $reservation->company_id,
                'funds_amount'   => $requestData['funds_amount'] ?? null,
                'funds_reason'   => $requestData['funds_reason'] ?? null,
                'snacks'         => $requestData['snacks'] ?? [],
                'equipment'      => $requestData['equipment'] ?? [],
            ]);
        }
    }
}
