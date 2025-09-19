<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;

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
        $position = Position::findOrFail($id);
        return response()->json($position);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $position = Position::create($request->all());
        return response()->json($position, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $position = Position::findOrFail($id);
        $position->update($request->all());

        return response()->json($position);
    }

    public function destroy($id)
    {
        $position = Position::findOrFail($id);
        $position->delete();

        return response()->json(null, 204);
    }
}
