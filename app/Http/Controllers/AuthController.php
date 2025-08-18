<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer'
        ]);

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully, please verify your email',
            'user' => $user,
        ], 201);
    }

    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully'
        ], 200);
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect email or password'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        if (!$user->hasVerifiedEmail()) {
            $token = $user->createToken('verify_token', ['verify-email'])->plainTextToken;
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address',
                'token' => $token
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

        Mail::raw("Gunakan kode OTP ini untuk reset password: $otp (berlaku 5 menit)", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Reset Password - Kode OTP');
        });

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
