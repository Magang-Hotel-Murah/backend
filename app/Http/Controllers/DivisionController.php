<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Division;

/**
 * @group Divisions
 */
class DivisionController extends Controller
{
    public function index()
    {
        return response()->json(Division::all());
    }

    public function show($id)
    {
        return response()->json(Division::findOrFail($id));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        return response()->json(Division::create($request->all()));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $division = Division::findOrFail($id);
        $division->update($request->all());

        return response()->json($division);
    }

    public function destroy($id)
    {
        $division = Division::findOrFail($id);
        $division->delete();

        return response()->json(null, 204);
    }
}
