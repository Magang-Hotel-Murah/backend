<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // return response()->json(User::withTrashed()->get());
        return response()->json(User::all());
    }

    public function show($id)
    {
        return response()->json(User::find($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'role' => 'nullable|in:customer,admin',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'restore' => 'nullable|boolean',
        ]);

        $user = User::withTrashed()->findOrFail($id);

        if ($request->has('restore') && $request->restore) {
            if ($user->trashed()) {
                $user->restore();
                return response()->json([
                    'message' => 'User restored successfully',
                    'user' => $user
                ]);
            } else {
                return response()->json([
                    'message' => 'User is not deleted'
                ], 400);
            }
        }

        $user->fill($request->only(['name', 'email', 'role']));
        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
}
