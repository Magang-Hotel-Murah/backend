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
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Str;
use App\Models\Company;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * POST api/register
     *
     * This endpoint does not require authentication.
     * @group Authentication
     * @unauthenticated
     * @bodyParam name string required User's name. Example: John Doe
     * @bodyParam email string required User's email. Example: 4kV3b@example.com
     * @bodyParam password string required User's password. Example: secret123
     * @bodyParam password_confirmation string required Confirm user's password. Example: secret123
     * @response 201 {
     *   "success": true,
     *   "message": "User registered successfully, please verify your email",
     *   "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "4kV3b@example.com",
     *       "role": "user",
     *       "email_verified_at": null,
     *       "created_at": "2021-01-01T00:00:00.000000Z",
     *       "updated_at": "2021-01-01T00:00:00.000000Z"
     *   }
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *       "name": ["The name field is required."],
     *       "email": ["The email field is required."],
     *       "password": ["The password field is required."],
     *       "password_confirmation": ["The password confirmation field is required."]
     *   }
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *       "email": ["The email has already been taken."],
     *       "password": ["The password confirmation does not match."]
     *   }
     * }
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user'
        ]);

        $user->profile()->create([
            'division_id' => null,
            'position_id' => null,
            'address' => null,
            'phone' => null,
            'photo' => null,
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

    public function registerAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'company_name' => 'required|string|max:255',
        ]);

        // Buat company baru
        $company = Company::create([
            'name' => $validated['company_name'],
            'code' => strtoupper(Str::random(6)), // kode unik perusahaan
            'invite_url' => null, // nanti bisa generate link invite
        ]);

        // Buat admin perusahaan
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        // buat profile kosong
        $user->profile()->create([
            'division_id' => null,
            'position_id' => null,
            'address' => null,
            'phone' => null,
            'photo' => null,
        ]);

        // generate email verification link
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => 'Admin perusahaan berhasil didaftarkan. Silakan verifikasi email.',
            'user' => $user,
            'company' => $company,
            // 'verify_url' => $verifyUrl,
        ], 201);
    }

    public function registerUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'company_code' => 'required|string', // kode perusahaan dari invite
        ]);

        $company = Company::where('code', $validated['company_code'])->first();
        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Kode perusahaan tidak valid.'
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'company_id' => $company->id,
        ]);

        $user->profile()->create([
            'division_id' => null,
            'position_id' => null,
            'address' => null,
            'phone' => null,
            'photo' => null,
        ]);

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => 'User berhasil didaftarkan. Silakan verifikasi email.',
            'user' => $user,
            'company' => $company,
            // 'verify_url' => $verifyUrl,
        ], 201);
    }

    /**
     * GET api/email/verify/{id}/{hash}
     *
     * This endpoint does not require authentication.
     *
     * @group Authentication
     * @unauthenticated
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  string  $hash
     * @return \Illuminate\Http\Response
     * @response 200 {
     *  "success": true,
     *  "message": "Email verified successfully."
     * }
     */

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

    /**
     * POST api/login
     *
     * This endpoint does not require authentication.
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam email string required User's email. Example: john@example.com
     * @bodyParam password string required User's password. Example: secret123
     * @response 200 {
     *   "success": true,
     *   "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "    john@example.com",
     *       "profile": {
     *           "id": 1,
     *           "user_id": 1,
     *           "division_id": 2,
     *           "position_id": 3,
     *           "address": "123 Main St",
     *           "phone": "555-1234",
     *           "photo": null,
     *           "created_at": "2024-09-18T07:07:57.000000Z",
     *           "updated_at": "2024-09-18T07:07:57.000000Z",
     *           "division": {
     *               "id": 2,
     *               "name": "Marketing"
     *           },
     *           "position": {
     *               "id": 3,
     *               "name": "Manager"
     *           }
     *       }
     *   },
     *   "token": "1|qwertyuiopasdfghjklzxcvbnm1234567890"
     * }
     * @response 401 {
     *   "success": false,
     *   "message": "Incorrect email or password"
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Please verify your email address",
     *   "token": "1|qwertyuiopasdfghjklzxcvbnm1234567890"
     * }
     * @response 500 {
     *   "success": false,
     *   "message": "An error occurred while processing your request."
     * }
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->with(
            'profile.division:id,name',
            'profile.position:id,name'
        )->first();

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

    /**
     * POST api/logout
     *
     * This endpoint requires authentication.
     *
     * @group Authentication
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "message": "Logged out successfully"
     * }
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * POST api/forgot-password
     *
     * This endpoint does not require authentication.
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam email string required User's email. Example: john@example.com
     * @response 200 {
     *   "success": true,
     *   "message": "Kode OTP reset password sudah dikirim ke email."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "OTP tidak valid"
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "OTP sudah kadaluarsa, silakan minta ulang."
     * }
     */

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

    /**
     * POST api/reset-password
     *
     * This endpoint does not require authentication.
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam email string required User's email. Example: john@example.com
     * @bodyParam otp string required OTP code. Example: 123456
     * @bodyParam password string required New password. Example: secret123
     * @bodyParam password_confirmation string required Confirm new password. Example: secret123
     * @response 200 {
     *   "success": true,
     *   "message": "Password berhasil direset."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "OTP tidak valid"
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "OTP sudah kadaluarsa, silakan minta ulang."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "An error occurred while processing your request."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *       "email": ["The email field is required."],
     *       "otp": ["The otp field is required."],
     *       "password": ["The password field is required."],
     *       "password_confirmation": ["The password confirmation field is required."]
     *   }
     * }
     */
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
