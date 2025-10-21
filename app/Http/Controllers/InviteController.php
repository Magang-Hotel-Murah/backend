<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Mail\InviteUserMail;
use Illuminate\Support\Facades\Log;


class InviteController extends Controller
{
    public function inviteUsers(Request $request)
    {
        $request->validate([
            'employees' => 'required|array|min:1',
            'employees.*.role' => 'required|in:employee,finance_officer,support_staff',
            'employees.*.emails' => 'required|array|min:1',
            'employees.*.emails.*' => 'required|email|distinct',
        ]);

        $companyId = Auth::user()->company_id;

        DB::beginTransaction();
        try {
            foreach ($request->employees as $employee) {
                foreach ($employee['emails'] as $email) {
                    if (User::where('email', $email)->exists()) continue;

                    $token = Str::uuid();

                    $invitation = Invitation::updateOrCreate(
                        ['email' => $email],
                        [
                            'company_id' => $companyId,
                            'role' => $employee['role'],
                            'token' => $token,
                            'expires_at' => now()->addDays(7),
                            'accepted_at' => null,
                        ]
                    );

                    Mail::to($email)->send(new InviteUserMail($invitation));
                    sleep(5);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Undangan berhasil dikirim ke semua email.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inviting users: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim undangan. Silakan coba lagi. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function activate(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $invitation = Invitation::where('token', $request->token)->firstOrFail();

        if ($invitation->isExpired()) {
            return response()->json(['message' => 'Link undangan sudah kadaluarsa.'], 410);
        }

        if ($invitation->accepted_at) {
            return response()->json(['message' => 'Undangan sudah digunakan.'], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => bcrypt($request->password),
            'email_verified_at' => now(),
            'role' => $invitation->role,
            'company_id' => $invitation->company_id,
        ]);

        $user->profile()->create();

        $invitation->update(['accepted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Akun Anda berhasil diaktifkan.',
            'user' => $user,
        ]);
    }
}
