<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;

use App\Models\User;

class UserProfileController extends Controller
{
    public function index()
    {
        $profiles = UserProfile::with(['user:id,name,email', 'division:id,name', 'position:id,name'])->get();
        return response()->json($profiles);
    }

    public function show($id)
    {
        $profile = UserProfile::with(['user:id,name,email', 'division:id,name', 'position:id,name'])
            ->where('user_id', $id)
            ->firstOrFail();

        return response()->json($profile);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'position_id' => 'required|exists:positions,id',
            'address'     => 'nullable|string',
            'phone'       => 'nullable|string',
        ]);

        $user = Auth::user();

        if ($user->profile) {
            return response()->json([
                'message' => 'Profile already exists for this user.'
            ], 400);
        }

        $profile = UserProfile::create([
            'user_id'     => $user->id,
            'division_id' => $validated['division_id'],
            'position_id' => $validated['position_id'],
            'address'     => $validated['address'] ?? null,
            'phone'       => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'message' => 'Profile created successfully',
            'data'    => $profile
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $profile = UserProfile::where('user_id', $id)->firstOrFail();

        $profile->update($request->only([
            'division_id',
            'position_id',
            'address',
            'phone',
            'photo'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile->load(['division:id,name', 'position:id,name'])
        ]);
    }
}
