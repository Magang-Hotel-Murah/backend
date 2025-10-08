<?php

namespace App\Http\Controllers;

use App\Models\MeetingRequest;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class MeetingRequestController extends Controller
{
    public function index()
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

    public function show(MeetingRequest $meetingRequest)
    {
        return response()->json($meetingRequest->load(['reservation', 'approvedBy']));
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
            'rejection_reason' => 'nullable|string'
        ]);

        $requestData = MeetingRequest::with('reservation')->where('status', 'waiting_finance')->findOrFail($id);

        $updateData = [
            'status' => $request->status,
            'approved_by' => Auth::id(),
        ];

        if ($request->status === 'rejected') {
            $updateData['rejection_reason'] = $request->rejection_reason ?? 'Ditolak oleh finance.';
        }

        $requestData->update($updateData);

        return response()->json([
            'message' => 'Status request berhasil diperbarui.',
            'data' => $requestData->fresh(['reservation']),
        ]);
    }

    public function destroy(MeetingRequest $meetingRequest)
    {
        $meetingRequest->delete();

        return response()->json(['message' => 'Meeting request deleted']);
    }
}
