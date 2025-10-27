<?php

namespace App\Http\Controllers;

use App\Models\MeetingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Services\MeetingRoomReservationService;

class MeetingRequestController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'period'     => 'nullable|string|in:all,week,month,year',
            'status'     => 'nullable|string|in:approved,waiting_finance',
        ]);

        $user = Auth::user();
        $period = $request->get('period', 'all');
        $status = $request->get('status');
        $isSummary = $request->get('summary', false) ? true : false;
        $isTrash = $request->get('trash', false) ? true : false;


        $baseQuery = MeetingRequest::query()
            ->where('funds_amount', '>', 0)
            ->whereIn('status', ['approved', 'waiting_finance']);

        if ($status) {
            $baseQuery->where('status', $status);
        }

        if ($request->filled(['start_date', 'end_date'])) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate   = Carbon::parse($request->end_date)->endOfDay();
        } elseif ($period !== 'all') {
            [$startDate, $endDate] = $this->getDateRange($period);
        } else {
            $startDate = null;
            $endDate = null;
        }

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($isSummary) {
            $funds = (clone $baseQuery)
                ->withTrashed()
                ->selectRaw('status, SUM(funds_amount) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $approvedFunds = $funds['approved'] ?? 0;
            $pendingFunds  = $funds['waiting_finance'] ?? 0;
            $totalFunds    = $approvedFunds + $pendingFunds;

            if (!$startDate || !$endDate) {
                $startDate = (clone $baseQuery)->min('created_at');
                $endDate   = (clone $baseQuery)->max('created_at');
            }

            return response()->json([
                'message'         => 'Rekap dana meeting berhasil diambil.',
                'period'          => $period,
                'start_date'      => $startDate ? Carbon::parse($startDate)->toDateString() : null,
                'end_date'        => $endDate ? Carbon::parse($endDate)->toDateString() : null,
                'total_funds'     => $totalFunds,
                'approved_funds'  => $approvedFunds,
                'pending_funds'   => $pendingFunds,
            ]);
        }

        if ($isTrash) {
            $baseQuery->withTrashed();
        }

        $meetings = $baseQuery->with(['reservation', 'approvedBy'])->get();

        if (!$startDate || !$endDate) {
            $startDate = $meetings->min('created_at');
            $endDate   = $meetings->max('created_at');
        }

        return response()->json([
            'message'     => 'Data meeting request berhasil diambil.',
            'period'      => $period,
            'start_date'  => $startDate ? Carbon::parse($startDate)->toDateString() : null,
            'end_date'    => $endDate ? Carbon::parse($endDate)->toDateString() : null,
            'count'       => $meetings->count(),
            'data'        => $meetings,
        ]);
    }

    protected function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    public function show($id)
    {
        $meetingRequest = MeetingRequest::with([
            'reservation',
            'reservation.user:id,name',
            'reservation.room:id,name,location',
            'reservation.user.profile:id,user_id,division_id,position_id',
            'reservation.user.profile.division:id,name',
            'reservation.user.profile.position:id,name',
            'approvedBy',
            'company:id,code,name'
        ])->find($id);

        if (!$meetingRequest) {
            return response()->json(['message' => 'Meeting request not found'], 404);
        }
        return response()->json($meetingRequest);
    }

    public function update(Request $request, MeetingRequest $meetingRequest)
    {
        $meetingRequest->update($request->all());

        return response()->json($meetingRequest);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|string|nullable',
        ], [
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi jika status ditolak.',
        ]);

        $meetingRequest = MeetingRequest::with('reservation')->findOrFail($id);

        if ($meetingRequest->status !== 'waiting_finance') {
            return response()->json([
                'message' => 'Status tidak dapat diubah karena status saat tidak menunggu persetujuan finance.',
            ], 403);
        }

        $updateData = [
            'status' => $request->status,
            'approved_by' => Auth::id(),
        ];

        if ($request->status === 'rejected') {
            $updateData['rejection_reason'] = $request->rejection_reason ?? 'Ditolak oleh finance.';
            $reservation = $meetingRequest->reservation()
                ->with([
                    'participants.user.profile',
                    'participants.user',
                    'room'
                ])
                ->first();

            app(\App\Http\Services\NotificationService::class)->sendRejectionNotification($reservation, $request->rejection_reason ?? 'Ditolak oleh finance.');
        }

        $meetingRequest->update($updateData);

        if ($request->status === 'approved') {
            $reservation = $meetingRequest->reservation()
                ->with([
                    'participants.user.profile',
                    'participants.user',
                    'room'
                ])->first();

            app(\App\Http\Services\NotificationService::class)->sendApprovalNotification($reservation);
        }

        return response()->json([
            'message' => 'Status request berhasil diperbarui.',
            'data' => $meetingRequest->fresh(['reservation']),
        ]);
    }

    public function destroy($id)
    {
        $meetingRequest = MeetingRequest::withTrashed()->findOrFail($id);

        if ($meetingRequest->trashed()) {
            $meetingRequest->restore();
            $message = 'Meeting request restored successfully.';
        } else {
            $meetingRequest->delete();
            $message = 'Meeting request deleted successfully.';
        }

        return response()->json(['message' => $message]);
    }
}
