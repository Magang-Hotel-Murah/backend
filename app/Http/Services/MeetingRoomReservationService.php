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
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MeetingRoomReservationService
{
    public function getReservations($request)
    {
        $user = Auth::user();
        $role = $user->role;
        $perPage = $request->get('per_page', 10);

        $query = MeetingRoomReservation::with([
            'user:id,name',
            'room:id,name',
        ]);

        if ($role === 'support_staff') {
            $query->where('status', 'approved')
                ->whereHas('request', function ($q) {
                    $q->where('status', 'approved');
                });
        }

        if ($request->has('user')) {
            $query->where('user_id', $user->id);
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

        [$startDate, $endDate, $filter] = $this->getDateRange($request);

        $query = MeetingRoomReservation::withoutGlobalScope(CompanyScope::class)
            ->select('id', 'user_id', 'company_id', 'title', 'meeting_room_id', 'start_time', 'end_time', 'status')
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

        $this->applyFilters($query, $request, Carbon::now());

        $reservations = $query->orderBy('start_time', 'asc')->get();

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
            'company:id,code,name',
            'user:id,name',
            'user.profile:id,user_id,division_id,position_id',
            'user.profile.division:id,name',
            'user.profile.position:id,name',
            'approver:id,name',
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

    private function normalizePhone($number)
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (substr($number, 0, 1) === '0') {
            $number = '62' . substr($number, 1);
        } elseif (substr($number, 0, 2) === '62') {
        } elseif (substr($number, 0, 3) === '628') {
        }
        return $number;
    }

    public function createReservation(array $validated, $waNumber = null)
    {
        $waNumberNormalized = $this->normalizePhone($waNumber);

        $user = User::whereHas('profile', function ($q) use ($waNumberNormalized) {
            $q->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$waNumberNormalized]);
        })->first() ?? Auth::user();

        $participants = $this->resolveParticipants($validated);

        $conflictMessage = $this->checkConflict(
            $validated['meeting_room_id'],
            $validated['start_time'],
            $validated['end_time'],
            null,
            count($participants)
        );

        if ($conflictMessage) {
            throw new \Exception($conflictMessage, 409);
        }

        return DB::transaction(function () use ($validated, $user, $participants) {
            $reservation = MeetingRoomReservation::create([
                'user_id'          => $user->id,
                'company_id'       => $user->company_id,
                'meeting_room_id'  => $validated['meeting_room_id'],
                'title'            => $validated['title'],
                'description'      => $validated['description'] ?? null,
                'start_time'       => $validated['start_time'],
                'end_time'         => $validated['end_time'],
                'status'           => 'pending',
            ]);

            $this->saveParticipants($reservation, $participants);
            $this->saveRequest($reservation, $validated['request'] ?? []);
            app(NotificationService::class)->sendReservationCreated($reservation);

            return $reservation;
        });
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
            $reservation = MeetingRoomReservation::with([
                'room',
                'user.profile',
                'participants.user.profile',
            ])->findOrFail($id);

            switch ($data['status']) {
                case 'approved':
                    $this->handleApproval($reservation, $data);
                    break;

                case 'rejected':
                    $this->handleRejection($reservation, $data);
                    break;

                default:
                    $this->resetStatus($reservation);
                    break;
            }

            return $reservation->refresh()->load([
                'participants.user.profile',
                'room'
            ]);
        });
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
        $request->validate([
            'status' => 'nullable|in:ongoing,upcoming,finished'
        ]);

        $query->where('status', 'approved')
            ->where(function ($q) {
                $q->whereDoesntHave('request')
                    ->orWhereHas('request', function ($r) {
                        $r->where('status', 'approved');
                    });
            });

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
                    ->where('end_time', '>=', $now);
            });

            $query->when($status === 'upcoming', function ($q) use ($now) {
                $q->where('start_time', '>', $now);
            });

            $query->when($status === 'finished', function ($q) use ($now) {
                $q->where('end_time', '<', $now);
            });

            $query->when($status === 'cancelled', function ($q) {
                $q->where('status', 'cancelled');
            });
        }
    }


    public function rules(): array
    {
        return [
            'meeting_room_id'       => 'required|exists:meeting_rooms,id',
            'title'                 => 'required|string',
            'description'           => 'nullable|string',
            'start_time'            => 'required|date|after_or_equal:now',
            'end_time'              => 'required|date|after:start_time',

            'participants'                => 'nullable|array',
            'participants.*.user_id'      => 'nullable|exists:users,id',
            'participants.*.name'         => 'nullable|string',
            'participants.*.email'        => 'nullable|email',
            'participants.*.whatsapp_number' => 'nullable|string',

            'division_ids'                => 'nullable|array',
            'division_ids.*'              => 'exists:user_profiles,division_id',

            'position_ids'                => 'nullable|array',
            'position_ids.*'              => 'exists:user_profiles,position_id',

            'position_combinations' => 'nullable|array',
            'position_combinations.*.position_id' => 'required|exists:user_profiles,position_id',
            'position_combinations.*.division_ids' => 'required|array',
            'position_combinations.*.division_ids.*' => 'exists:user_profiles,division_id',

            'division_combinations' => 'nullable|array',
            'division_combinations.*.division_id' => 'required|exists:user_profiles,division_id',
            'division_combinations.*.position_ids' => 'required|array',
            'division_combinations.*.position_ids.*' => 'exists:user_profiles,position_id',

            'all_users'                 => 'nullable|boolean',

            'request'                     => 'nullable|array',
            'request.funds_amount'        => 'nullable|numeric|min:0',
            'request.funds_reason'        => 'nullable|string|required_with:request.funds_amount',
            'request.snacks'              => 'nullable|array',
            'request.equipment'           => 'nullable|array',
        ];
    }

    private function resolveParticipants(array $validated): array
    {
        $participants = $validated['participants'] ?? [];

        if (!empty($validated['all_users'])) {
            $participants = array_merge(
                $participants,
                User::pluck('id')->map(fn($id) => ['user_id' => $id])->toArray()
            );
        }

        if (!empty($validated['division_ids'])) {
            $participants = array_merge(
                $participants,
                User::whereHas(
                    'profile',
                    fn($q) =>
                    $q->whereIn('division_id', $validated['division_ids'])
                )->pluck('id')->map(fn($id) => ['user_id' => $id])->toArray()
            );
        }

        if (!empty($validated['position_ids'])) {
            $participants = array_merge(
                $participants,
                User::whereHas(
                    'profile',
                    fn($q) =>
                    $q->whereIn('position_id', $validated['position_ids'])
                )->pluck('id')->map(fn($id) => ['user_id' => $id])->toArray()
            );
        }

        if (!empty($validated['position_combinations'])) {
            foreach ($validated['position_combinations'] as $combo) {
                $participants = array_merge(
                    $participants,
                    User::whereHas(
                        'profile',
                        fn($q) =>
                        $q->where('position_id', $combo['position_id'])
                            ->whereIn('division_id', $combo['division_ids'])
                    )->pluck('id')->map(fn($id) => ['user_id' => $id])->toArray()
                );
            }
        }

        if (!empty($validated['division_combinations'])) {
            foreach ($validated['division_combinations'] as $combo) {
                $participants = array_merge(
                    $participants,
                    User::whereHas(
                        'profile',
                        fn($q) =>
                        $q->where('division_id', $combo['division_id'])
                            ->whereIn('position_id', $combo['position_ids'])
                    )->pluck('id')->map(fn($id) => ['user_id' => $id])->toArray()
                );
            }
        }

        return collect($participants)->unique('user_id')->values()->toArray();
    }

    public function checkConflict(
        MeetingRoom|int $room,
        string $start,
        string $end,
        int $excludeId = null,
        ?int $participantCount = null
    ): ?string {
        $roomId = $room instanceof MeetingRoom ? $room->id : $room;

        $bufferMinutes = 14;
        $startWithBuffer = Carbon::parse($start)->subMinutes($bufferMinutes)->toDateTimeString();
        $endWithBuffer = Carbon::parse($end)->addMinutes($bufferMinutes)->toDateTimeString();

        $conflictQuery = MeetingRoomReservation::with('room')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('status', 'approved')
                        ->whereDoesntHave('request', function ($r) {
                            $r->where('funds_amount', '>', 0);
                        });
                })
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'approved')
                            ->whereHas('request', function ($r) {
                                $r->where('funds_amount', '>', 0)
                                    ->where('status', 'approved');
                            });
                    });
            })
            ->conflict($roomId, $startWithBuffer, $endWithBuffer, $excludeId);

        Log::info('Check conflict params', [
            'roomId' => $roomId,
            'start' => $start,
            'end' => $end,
            'count' => $conflictQuery->count(),
            'sql' => $conflictQuery->toSql(),
            'bindings' => $conflictQuery->getBindings(),
        ]);
        if ($conflictQuery->exists()) {
            $conflicting = $conflictQuery->first();
            $room = MeetingRoom::with('parent:id,name', 'children:id,parent_id,name')->find($roomId);
            $conflictRoom = $conflicting->room;

            if ($room->parent_id && $room->parent_id === $conflictRoom->id) {
                return "Ruangan ini adalah bagian dari ruangan {$conflictRoom->name} yang sudah terpakai.";
            } elseif ($room->id === $conflictRoom->parent_id) {
                return "Salah satu subruangan dari {$room->name} sudah memiliki jadwal di waktu yang sama.";
            } elseif ($room->id === $conflictRoom->id) {
                return "Ruangan {$room->name} sudah memiliki reservasi yang disetujui pada waktu tersebut.";
            }

            return "Ruangan ini sudah memiliki reservasi yang bentrok.";
        }

        if ($participantCount !== null) {
            $room = MeetingRoom::select('id', 'capacity')->find($roomId);
            if (!$room) return 'Ruangan tidak ditemukan.';

            $totalExisting = MeetingRoomReservation::where('status', 'approved')
                ->conflict($roomId, $startWithBuffer, $endWithBuffer, $excludeId)
                ->withCount('participants')
                ->get()
                ->sum('participants_count');

            $totalAfter = $totalExisting + $participantCount;

            if ($totalAfter > $room->capacity) {
                return "Kapasitas ruangan tidak mencukupi. Kapasitas maksimal {$room->capacity}, total peserta {$totalAfter}.";
            }
        }

        return null;
    }

    public function saveParticipants(MeetingRoomReservation $reservation, array $participants): void
    {
        if (empty($participants)) return;

        $reservation->participants()->delete();
        $companyId = $reservation->user->company_id;

        $data = collect($participants)->map(function ($p) use ($reservation, $companyId) {
            return [
                'reservation_id'   => $reservation->id,
                'company_id'       => $companyId,
                'user_id'          => $p['user_id'] ?? null,
                'name'             => $p['name'] ?? null,
                'email'            => $p['email'] ?? null,
                'whatsapp_number'  => $p['whatsapp_number'] ?? null,
            ];
        })->toArray();

        MeetingParticipant::insert($data);
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

    private function handleApproval($reservation, array $data)
    {
        if ($conflictMessage = $this->checkConflict(
            $reservation->meeting_room_id,
            $reservation->start_time,
            $reservation->end_time,
            $reservation->id
        )) {
            throw new \Exception("Tidak dapat menyetujui reservasi. {$conflictMessage}");
        }

        $reservation->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        $this->updateRequestStatus($reservation);
        app(NotificationService::class)->sendApprovalNotification($reservation);
        $this->rejectConflictingReservations($reservation, $data);
    }

    private function handleRejection($reservation, array $data)
    {
        $reason = $data['rejection_reason'] ?? 'Ditolak oleh admin perusahaan.';

        $reservation->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => Auth::id(),
        ]);

        if ($reservation->request) {
            $reservation->request->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);
        }

        app(NotificationService::class)->sendRejectionNotification($reservation, $reason);
    }

    private function resetStatus($reservation)
    {
        $reservation->update([
            'status' => 'pending',
            'approved_by' => null,
            'rejection_reason' => null,
        ]);
    }

    private function updateRequestStatus($reservation)
    {
        if (!$reservation->request) {
            return;
        }

        $newStatus = $reservation->request->funds_amount > 0
            ? 'waiting_finance'
            : 'approved';

        $reservation->request->update(['status' => $newStatus]);
    }

    private function rejectConflictingReservations($reservation, array $data)
    {
        $rejectionReason = $data['rejection_reason']
            ?? 'Bentrok dengan jadwal yang telah disetujui otomatis oleh sistem.';

        $conflictingReservations = MeetingRoomReservation::where('status', 'pending')
            ->conflict(
                $reservation->meeting_room_id,
                $reservation->start_time,
                $reservation->end_time,
                $reservation->id
            );

        foreach ($conflictingReservations->get() as $conflict) {
            $conflict->update([
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'approved_by' => Auth::id(),
            ]);

            app(NotificationService::class)->sendConflictRejectionNotification($conflict);
        }
    }
}
