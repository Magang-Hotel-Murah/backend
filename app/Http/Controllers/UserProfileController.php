<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use App\Http\Services\CloudinaryService;

/**
 * @group User Profiles
 */
class UserProfileController extends Controller
{
    public function index()
    {
        $profiles = UserProfile::with(['user:id,name,email', 'division:id,name', 'position:id,name'])->get();
        return response()->json($profiles);
    }

    public function show($id = null)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            $id = $user->id;
        } else {
            if (!$id) {
                $id = $user->id;
            }
        }

        $profile = UserProfile::with([
            'user:id,name,email',
            'division:id,name',
            'position:id,name'
        ])->where('user_id', $id)->firstOrFail();

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


    /**
     * PUT api/user-profiles/{userId}
     *
     * This endpoint allows you to update user profile information such as
     * division, position, address, phone, and optionally profile photo.
     *
     * @group User Profiles
     * @urlParam id integer required The ID of the user whose profile will be updated. Example: 1
     *
     * @bodyParam division_id integer The ID of the division. Example: 2
     * @bodyParam position_id integer The ID of the position. Example: 5
     * @bodyParam address string The address of the user. Example: "Jl. Merdeka No. 123"
     * @bodyParam phone string The phone number of the user. Example: "08123456789"
     * @bodyParam photo file The profile photo (image file: jpg, jpeg, png, max 2MB).
     *
     */
    public function update(Request $request, $id, CloudinaryService $cloudinary)
    {
        $profile = UserProfile::where('user_id', $id)->firstOrFail();

        $request->validate([
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        $data = array_filter($request->only([
            'division_id',
            'position_id',
            'address',
            'phone',
        ]), fn($value) => $value !== null && $value !== '' && $value !== 'NaN');


        if ($request->hasFile('photo')) {
            if ($profile->photo) {
                $cloudinary->destroy($profile->photo);
            }

            $upload = $cloudinary->upload($request->file('photo'), 'profiles');
            $data['photo'] = $upload['url'];
        }

        $profile->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile->load(['division:id,name', 'position:id,name']),
        ]);
    }
}
