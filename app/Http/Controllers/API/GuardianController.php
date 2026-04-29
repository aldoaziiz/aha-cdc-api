<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    // GET all
    public function index()
    {
        $guardians = Guardian::with([
            'status',
            'role',
            'children:id,guardian_id,name'
        ])->get();

        return $guardians;
    }

    // POST create
    public function store(Request $request)
    {
        $guardian = Guardian::with(['status', 'role'])
            ->create($request->all());
        return response()->json($guardian, 201);
    }

    // GET by id
    public function show($id)
    {
        $guardian = Guardian::with(['status', 'role'])
            ->find($id);

        if (!$guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($guardian);
    }

    // PUT update
    public function update(Request $request, $id)
    {
        $guardian = Guardian::with(['status', 'role'])
            ->find($id);

        if (!$guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $guardian->update($request->all());

        return response()->json($guardian);
    }

    // DELETE
    public function destroy($id)
    {
        $guardian = Guardian::with(['status', 'role'])
            ->find($id);

        if (!$guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $guardian->delete($id);

        return response()->json(['message' => 'Deleted']);
    }
}
