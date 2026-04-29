<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index()
    {
        $guardians = Guardian::with(['status', 'role'])->get();

        return $guardians->map(function ($g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'phone' => $g->phone,
                'address' => $g->address,
                'status_name' => $g->status->name ?? '',
                'status_code' => $g->status->code ?? null,
                'role_name' => $g->role->name ?? '',
                'children_names' => $g->children->pluck('name')->toArray(),
            ];
        });
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
