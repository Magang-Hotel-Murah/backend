<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('with_deleted') && $request->with_deleted) {
            return response()->json(User::withTrashed()
                ->with(['division:id,name'])
                ->get());
        }
        return response()->json(User::with('division:id,name')->get());
    }

    public function show($id)
    {
        return response()->json(User::withTrashed()->find($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'role' => 'nullable|in:user,admin',
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
        $user = User::withTrashed()->findOrFail($id); // pastikan bisa restore juga
        $message = '';

        if ($user->trashed()) {
            $user->restore();
            $message = 'User restored successfully';
        } else {
            $user->delete();
            $message = 'User deleted successfully';
        }

        return response()->json([
            'message' => $message,
            'user' => $user
        ]);
    }
}
