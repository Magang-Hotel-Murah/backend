<?php

namespace App\Http\Services;

use App\Models\MeetingRoomReservation;
use App\Models\MeetingRequest;
use App\Models\MeetingRoom;
use App\Models\MeetingParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // public function getMeetingDisplay($request)
    // {
    //     $company = Company::where('code', $request->company_code)->firstOrFail();
    //     $companyId = $company->id;
    //     $now = Carbon::now();

    //     $filter = $request->get('filter', 'day');

    //     switch ($filter) {
    //         case 'week':
    //             $startDate = $now->copy()->startOfWeek();
    //             $endDate   = $now->copy()->endOfWeek();
    //             break;
    //         case 'month':
    //             $startDate = $now->copy()->startOfMonth();
    //             $endDate   = $now->copy()->endOfMonth();
    //             break;
    //         case 'year':
    //             $startDate = $now->copy()->startOfYear();
    //             $endDate   = $now->copy()->endOfYear();
    //             break;
    //         default:
    //             $startDate = $now->copy()->startOfDay();
    //             $endDate   = $now->copy()->endOfDay();
    //             break;
    //     }

    //     if ($request->filled('start_date') && $request->filled('end_date')) {
    //         $startDate = Carbon::parse($request->start_date)->startOfDay();
    //         $endDate   = Carbon::parse($request->end_date)->endOfDay();
    //     }

    //     $query = MeetingRoomReservation::withoutGlobalScope(CompanyScope::class)
    //         ->select('id', 'user_id', 'company_id', 'title', 'meeting_room_id', 'start_time', 'end_time', 'participants', 'status')
    //         ->with([
    //             'user:id,name',
    //             'user.profile:id,user_id,division_id,position_id',
    //             'user.profile.division:id,name',
    //             'user.profile.position:id,name',
    //             'room:id,name',
    //             'company:id,code,name',
    //         ])
    //         ->where('company_id', $companyId)
    //         ->whereBetween('start_time', [$startDate, $endDate]);

    //     if (!$request->filled('status')) {
    //         $query->where('status', 'approved');
    //     }

    //     if ($request->filled('room_id')) {
    //         $query->where('meeting_room_id', $request->room_id);
    //     }

    //     if ($request->filled('division_id')) {
    //         $query->whereHas('user.profile', function ($q) use ($request) {
    //             $q->where('division_id', $request->division_id);
    //         });
    //     }

    //     if ($request->filled('status')) {
    //         $status = $request->status;

    //         $query->when($status === 'ongoing', function ($q) use ($now) {
    //             $q->where('start_time', '<=', $now)
    //                 ->where('end_time', '>=', $now)
    //                 ->where('status', 'approved');
    //         });

    //         $query->when($status === 'upcoming', function ($q) use ($now) {
    //             $q->where('start_time', '>', $now)
    //                 ->where('status', 'approved');
    //         });

    //         $query->when($status === 'finished', function ($q) use ($now) {
    //             $q->where('end_time', '<', $now)
    //                 ->where('status', 'approved');
    //         });

    //         $query->when($status === 'cancelled', function ($q) {
    //             $q->where('status', 'cancelled');
    //         });
    //     }

    //     $reservations = $query->orderBy('start_time', 'asc')->get();

    //     return [
    //         'filter' => $filter,
    //         'date_range' => [
    //             'start' => $startDate->toDateTimeString(),
    //             'end'   => $endDate->toDateTimeString(),
    //         ],
    //         'total' => $reservations->count(),
    //         'reservations' => $reservations,
    //     ];
    // }

    public function getMeetingDisplay($request)
    {
        // 🏢 1. Ambil data perusahaan dari kode
        $company = Company::where('code', $request->company_code)->firstOrFail();
        $companyId = $company->id;

        // 🕒 2. Tentukan range waktu berdasarkan filter
        [$startDate, $endDate, $filter] = $this->getDateRange($request);

        // 🧾 3. Bangun query dasar
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

        // 💡 4. Tambahkan filter tambahan
        $this->applyFilters($query, $request, Carbon::now());

        // 📅 5. Ambil hasil akhir
        $reservations = $query->orderBy('start_time', 'asc')->get();

        // 🔁 6. Kembalikan hasil terstruktur
        return [
            'now' => Carbon::now()->toDateTimeString(),
            'filter' => $filter,
            'date_range' => [
                'start' => $startDate->toDateTimeString(),
                'end'   => $endDate->toDateTimeString(),
            ],
            'total' => $reservations->count(),
            'reservations' => $reservations,
        ];
    }

    protected function getDateRange($request)
    {
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

        return [$startDate, $endDate, $filter];
    }

    protected function applyFilters($query, $request, $now)
    {
        if (blank($request->status)) {
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
    }

    public function getReservationsByRoom($room_id)
    {
        $reservation = MeetingRoomReservation::with(
            'user:id,name',
            'room:id,name'
        )
            ->where('meeting_room_id', $room_id)
            ->orderBy('start_time', 'asc')
            ->get();

        return $reservation;
    }

    public function getReservationDetails($reservation_id)
    {
        $reservation = MeetingRoomReservation::with([
            'user:id,name',
            'user.profile:id,user_id,division_id,position_id',
            'user.profile.division:id,name',
            'user.profile.position:id,name',
            'room:id,name',
            'participants:id,reservation_id,user_id,name,email,whatsapp_number',
            'participants.user:id,name,email',
            'participants.user.profile:user_id,phone',
            'request:reservation_id,funds_amount,funds_reason,snacks,equipment',
        ])->findOrFail($reservation_id);

        $participants = $reservation->participants()->get()->map(function ($participant) {
            return [
                'id'               => $participant->id,
                'user_id'         => $participant->user_id,
                'name'            => $participant->user_id ? $participant->user->name : $participant->name,
                'email'           => $participant->user_id ? $participant->user->email : $participant->email,
                'whatsapp_number' => $participant->user_id ? $participant->user->profile->phone ?? null : $participant->whatsapp_number,
            ];
        });

        $reservation->setRelation('participants', $participants);

        return $reservation;
    }

    public function createReservation(array $validated)
    {
        $conflictMessage = $this->checkConflict(
            $validated['meeting_room_id'],
            $validated['start_time'],
            $validated['end_time']
        );

        if ($conflictMessage) {
            throw new \Exception($conflictMessage, 409);
        }

        $reservation = DB::transaction(function () use ($validated) {
            $reservation = MeetingRoomReservation::create([
                'user_id'          => Auth::id(),
                'company_id'       => Auth::user()->company_id,
                'meeting_room_id'  => $validated['meeting_room_id'],
                'title'            => $validated['title'],
                'description'      => $validated['description'] ?? null,
                'start_time'       => $validated['start_time'],
                'end_time'         => $validated['end_time'],
                'status'           => 'pending',
            ]);

            $this->saveParticipants($reservation, $validated['participants'] ?? []);
            $this->saveRequest($reservation, $validated['request'] ?? []);

            return $this->getReservationDetails($reservation->id);
        });

        return $reservation;
    }

    public function updateReservation(array $validated, $id)
    {
        return DB::transaction(function () use ($validated, $id) {
            $reservation = MeetingRoomReservation::with(['request'])->findOrFail($id);

            if ($reservation->status === 'approved' && isset($validated['status']) && $validated['status'] !== $reservation->status) {
                throw new \Exception('Status tidak dapat diubah karena reservasi sudah disetujui.');
            }

            if (isset($validated['status']) && $validated['status'] === 'cancelled') {
                $reservation->request()->update(['status' => 'cancelled']);
            }

            $hasParticipantsKey = array_key_exists('participants', $validated);

            $updateData = [
                'meeting_room_id' => $validated['meeting_room_id'],
                'title'           => $validated['title'],
                'description'     => $validated['description'] ?? null,
                'start_time'      => $validated['start_time'],
                'end_time'        => $validated['end_time'],
            ];

            if ($hasParticipantsKey) {
                $updateData['participants'] = count($validated['participants'] ?? []);
            }

            $updateData['status'] = $reservation->status === 'approved'
                ? $reservation->status
                : ($validated['status'] ?? $reservation->status);

            $reservation->update($updateData);

            if ($hasParticipantsKey) {
                $this->saveParticipants($reservation, $validated['participants'] ?? []);
            }

            if (array_key_exists('request', $validated)) {
                $this->saveRequest($reservation, $validated['request'] ?? []);
            }

            return $reservation->load(['participants', 'request', 'room']);
        });
    }

    public function updateStatus(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $reservation = MeetingRoomReservation::with(['room', 'request'])->findOrFail($id);

            switch ($data['status']) {
                case 'approved':
                    // ✅ Cek bentrok jadwal
                    if ($conflictMessage = $this->checkConflict(
                        $reservation->meeting_room_id,
                        $reservation->start_time,
                        $reservation->end_time,
                        $reservation->id
                    )) {
                        throw new \Exception("Tidak dapat menyetujui reservasi. {$conflictMessage}");
                    }

                    // ✅ Update status utama
                    $reservation->update([
                        'status' => 'approved',
                        'approved_by' => Auth::id(),
                        'rejection_reason' => null,
                    ]);

                    // ✅ Update request terkait
                    if ($reservation->request) {
                        $reservation->request->update([
                            'status' => $reservation->request->funds_amount > 0
                                ? 'waiting_finance'
                                : 'approved',
                        ]);
                    }

                    // ✅ Tolak otomatis reservasi lain yang bentrok
                    $rejectionReason = $data['rejection_reason']
                        ?? 'Bentrok dengan jadwal yang telah disetujui otomatis oleh sistem.';

                    MeetingRoomReservation::where('status', 'pending')
                        ->conflict(
                            $reservation->meeting_room_id,
                            $reservation->start_time,
                            $reservation->end_time,
                            $reservation->id
                        )
                        ->update([
                            'status' => 'rejected',
                            'rejection_reason' => $rejectionReason,
                            'approved_by' => Auth::id(),
                        ]);

                    break;

                case 'rejected':
                    // ❌ Ditolak oleh admin
                    $reservation->update([
                        'status' => 'rejected',
                        'rejection_reason' => $data['rejection_reason'] ?? 'Ditolak oleh admin perusahaan.',
                        'approved_by' => Auth::id(),
                    ]);

                    if ($reservation->request) {
                        $reservation->request->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'] ?? 'Ditolak karena booking tidak disetujui.',
                        ]);
                    }
                    break;

                default: // pending
                    // ⏳ Kembalikan ke status pending
                    $reservation->update([
                        'status' => 'pending',
                        'approved_by' => null,
                        'rejection_reason' => null,
                    ]);
                    break;
            }

            return $reservation->refresh()->load(['participants', 'request', 'room']);
        });
    }

    public function rules(): array
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

    public function checkConflict(int $roomId, string $start, string $end, int $excludeId = null): ?string
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

    public function saveParticipants(MeetingRoomReservation $reservation, array $participants): void
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

    public function saveRequest(MeetingRoomReservation $reservation, array $requestData): void
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
