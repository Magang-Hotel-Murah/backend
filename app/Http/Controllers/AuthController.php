<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Verified;
use App\Mail\VerifyUserMail;
use Illuminate\Support\Str;
use App\Models\Company;
use Carbon\Carbon;
use App\Mail\ForgotPasswordMail;

class AuthController extends Controller
{
    public function getMe(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized or session expired.',
            ], 401);
        }

        $token = $user->currentAccessToken();

        return response()->json([
            'message' => 'User session is valid.',
            'data' => $user,
            'token_expired_at' => $token?->expires_at,
            'remember' => in_array('remember', $token?->abilities ?? []),
        ]);
    }

    public function registerAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'company_name' => 'required|string|max:255|unique:companies,name',
        ]);

        DB::beginTransaction();

        try {
            $code = strtoupper(Str::random(6));
            $host = env('FRONTEND_URL, http://localhost:5173');

            $company = Company::create([
                'name' => $validated['company_name'],
                'code' => $code,
                'display_url' => $host . '/api/meeting-display/' . $code,
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'company_admin',
                'company_id' => $company->id,
            ]);

            $user->profile()->create([
                'division_id' => null,
                'position_id' => null,
                'address' => null,
                'phone' => null,
                'photo' => null,
            ]);

            Mail::to($user->email)->send(new VerifyUserMail($user));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil. Silakan verifikasi email untuk mengaktifkan akun Anda.',
                'data' => [
                    'company' => $company,
                    'user' => $user,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendaftarkan admin perusahaan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link.'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.'
            ], 200);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.'
        ], 200);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean'
        ]);

        $remember = $request->boolean('remember', false);
        $user = User::where('email', $request->email)->with(
            'profile.division:id,name',
            'profile.position:id,name',
            'company:id,name,code'
        )->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect email or password'
            ], 401);
        }

        $abilities = $remember ? ['remember'] : [];
        $token = $user->createToken('auth_token', $abilities)->plainTextToken;
        $tokenModel = $user->tokens()->latest('id')->first();

        $expiry = $remember ? now()->addDays(30) : now()->addHours(8);

        $tokenModel->expires_at = $expiry;
        $tokenModel->save();

        if (!$user->hasVerifiedEmail()) {
            $verifyToken = $user->createToken('verify_token', ['verify-email'])->plainTextToken;

            $verifyModel = $user->tokens()->latest('id')->first();
            $verifyModel->expires_at = now()->addMinutes(15);
            $verifyModel->save();

            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address',
                'token' => $verifyToken
            ], 403);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $otp,
                'created_at' => Carbon::now()
            ]
        );

        Mail::to($request->email)->send(new ForgotPasswordMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP reset password sudah dikirim ke email.'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:6|confirmed',
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->otp)
            ->first();

        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid'
            ], 400);
        }

        if (Carbon::parse($tokenData->created_at)->addMinutes(5)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP sudah kadaluarsa, silakan minta ulang.'
            ], 400);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset.'
        ], 200);
    }
}
