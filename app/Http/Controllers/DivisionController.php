<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Division;
use App\Models\Position;
use Illuminate\Support\Facades\Auth;

/**
 * @group Divisions
 */
class DivisionController extends Controller
{
    public function index()
    {
        $divisions = Division::with('positions')->get();

        $data = $divisions->map(function ($division) {
            return [
                'id' => $division->id,
                'name' => $division->name,
                'company_id' => $division->company_id,
                'positions' => $division->positions->map(function ($pos) {
                    return [
                        'id' => $pos->id,
                        'name' => $pos->name,
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Daftar divisi berhasil diambil.',
            'data' => $data,
        ]);
    }

    public function show($id)
    {
        $division = Division::findOrFail($id);

        if (!$division) {
            return response()->json(['message' => 'Division not found'], 404);
        }

        return response()->json($division->load('positions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'position_ids' => 'nullable|array',
            'position_ids.*' => 'integer|exists:positions,id',
            'new_positions' => 'nullable|array',
            'new_positions.*' => 'string|max:100',
        ]);

        $user = Auth::user();

        $existingDivision = Division::whereRaw('LOWER(name) = ?', [strtolower($request->name)])
            ->first();

        if ($existingDivision) {
            return response()->json([
                'message' => 'Nama divisi sudah digunakan di perusahaan Anda.',
            ], 422);
        }

        if ($request->filled('position_ids')) {
            $invalid = Position::withoutGlobalScopes()
                ->whereIn('id', $request->position_ids)
                ->where('company_id', '!=', $user->company_id)
                ->exists();

            if ($invalid) {
                return response()->json([
                    'message' => 'Beberapa posisi tidak valid untuk perusahaan Anda.',
                ], 422);
            }
        }

        $division = Division::create([
            'name' => $request->name,
            'company_id' => $user->company_id,
        ]);

        $allPositionIds = [];

        if ($request->filled('position_ids')) {
            $allPositionIds = Position::whereIn('id', $request->position_ids)
                ->pluck('id')
                ->toArray();
        }

        if ($request->filled('new_positions')) {
            foreach ($request->new_positions as $posName) {
                $existing = Position::where('company_id', $user->company_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($posName)])
                    ->first();

                if ($existing) {
                    $allPositionIds[] = $existing->id;
                } else {
                    $position = Position::create([
                        'name' => $posName,
                        'company_id' => $user->company_id,
                    ]);
                    $allPositionIds[] = $position->id;
                }
            }
        }

        if (!empty($allPositionIds)) {
            $division->positions()->syncWithoutDetaching($allPositionIds);
        }

        return response()->json([
            'message' => 'Divisi berhasil dibuat.',
            'data' => $division->load('positions'),
        ]);
    }

    public function update(Request $request, Division $division)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'position_ids' => 'nullable|array',
            'position_ids.*' => 'integer|exists:positions,id',
            'new_positions' => 'nullable|array',
            'new_positions.*' => 'string|max:100',
            'remove_positions' => 'nullable|array',
            'remove_positions.*' => 'integer|exists:positions,id',
        ]);

        $user = Auth::user();

        if ($division->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Divisi tidak ditemukan untuk perusahaan Anda.',
            ], 403);
        }

        $exists = Division::where('company_id', $user->company_id)
            ->whereRaw('LOWER(name) = ?', [strtolower($request->name)])
            ->where('id', '!=', $division->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Nama divisi sudah digunakan di perusahaan Anda.',
            ], 422);
        }

        if ($request->filled('position_ids')) {
            $invalid = Position::withoutGlobalScopes()
                ->whereIn('id', $request->position_ids)
                ->where('company_id', '!=', $user->company_id)
                ->exists();

            if ($invalid) {
                return response()->json([
                    'message' => 'Beberapa posisi tidak valid untuk perusahaan Anda.',
                ], 422);
            }
        }

        $division->update([
            'name' => $request->name,
        ]);

        $allPositionIds = [];

        if ($request->filled('position_ids')) {
            $allPositionIds = Position::whereIn('id', $request->position_ids)
                ->pluck('id')
                ->toArray();
        }

        if ($request->filled('new_positions')) {
            foreach ($request->new_positions as $posName) {
                $existing = Position::where('company_id', $user->company_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($posName)])
                    ->first();

                if ($existing) {
                    $allPositionIds[] = $existing->id;
                } else {
                    $position = Position::create([
                        'name' => $posName,
                        'company_id' => $user->company_id,
                    ]);
                    $allPositionIds[] = $position->id;
                }
            }
        }

        if (!empty($allPositionIds)) {
            $division->positions()->syncWithoutDetaching($allPositionIds);
        }

        if ($request->filled('remove_positions')) {
            $division->positions()->detach($request->remove_positions);
        }

        return response()->json([
            'message' => 'Divisi berhasil diperbarui.',
            'data' => $division->load('positions'),
        ]);
    }


    public function destroy($id)
    {
        $division = Division::findOrFail($id);
        $division->delete();

        return response()->json([
            'message' => 'Divisi berhasil dihapus.',
        ]);
    }
}
