<?php

namespace App\Http\Controllers;

use App\Models\MeetingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class MeetingRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = MeetingRequest::query();

        switch ($user->role) {
            case 'super_admin':
                break;

            case 'employee':
            case 'company_admin':
                $query->whereHas(
                    'reservation',
                    fn($q) =>
                    $q->where('user_id', $user->id)
                );
                break;

            case 'finance_officer':
                $query->where('status', 'waiting_finance');
                break;

            case 'support_staff':
                $query->where('status', 'approved')
                    ->whereHas(
                        'reservation',
                        fn($q) =>
                        $q->where('status', 'approved')
                    );
                break;

            default:
                return response()->json(['message' => 'Unauthorized'], 403);
        }

        $meetings = $query->with(['reservation', 'approvedBy'])->get();

        return response()->json($meetings);
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
        }

        $meetingRequest->update($updateData);

        return response()->json([
            'message' => 'Status request berhasil diperbarui.',
            'data' => $meetingRequest->fresh(['reservation']),
        ]);
    }


    public function destroy(MeetingRequest $meetingRequest)
    {
        $meetingRequest->delete();

        return response()->json(['message' => 'Meeting request deleted']);
    }
}
