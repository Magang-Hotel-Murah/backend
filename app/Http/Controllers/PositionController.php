<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;
use Illuminate\Support\Facades\Auth;

/**
 * @group Positions
 */
class PositionController extends Controller
{
    public function index()
    {
        $positions = Position::all();
        return response()->json($positions);
    }

    public function show($id)
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json(['message' => 'Posisi tidak ditemukan atau tidak milik perusahaan Anda.'], 404);
        }

        return response()->json($position);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        $existing = Position::where('company_id', $user->company_id)
            ->whereRaw('LOWER(name) = ?', [strtolower($request->name)])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Nama posisi sudah digunakan di perusahaan Anda.',
            ], 422);
        }

        $position = Position::create([
            'name' => $request->name,
            'company_id' => $user->company_id,
        ]);

        return response()->json($position);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $position = Position::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$position) {
            return response()->json([
                'message' => 'Posisi tidak ditemukan atau tidak milik perusahaan Anda.',
            ], 404);
        }

        $duplicate = Position::where('company_id', $user->company_id)
            ->whereRaw('LOWER(name) = ?', [strtolower($request->name)])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Nama posisi sudah digunakan di perusahaan Anda.',
            ], 422);
        }

        $position->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Posisi berhasil diperbarui.',
            'data' => $position,
        ]);
    }

    public function destroy($id)
    {
        $position = Position::findOrFail($id);
        $position->delete();

        return response()->json(null, 204);
    }
}
