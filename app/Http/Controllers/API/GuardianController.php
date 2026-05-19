<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index(Request $request)
    {
        $query = Guardian::with('status:id,name');

        // SEARCH
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('phone', 'like', '%'.$request->search.'%');
            });
        }

        // SORTING
        if (
            $request->sort_by &&
            $request->sort_order
        ) {

            $query->orderBy(
                $request->sort_by,
                $request->sort_order
            );
        } else {

            $query->latest();
        }

        // PAGINATION
        return $query->paginate($request->per_page ?? 10);
    }

    // post / create
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'id_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
        ]);

        $validated['status_id'] = 1;

        $guardian = Guardian::create($validated);

        return response()->json(
            $guardian->load(['status:id,name', 'role:id,name']),
            201
        );
    }

    // GET by id
    public function show($id)
    {
        $guardian = Guardian::with(['status', 'children:id,name'])
            ->find($id);

        if (! $guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($guardian);
    }

    // PUT update
    public function update(Request $request, $id)
    {
        $guardian = Guardian::with(['status', 'role'])
            ->find($id);

        if (! $guardian) {
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

        if (! $guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $guardian->delete($id);

        return response()->json(['message' => 'Deleted']);
    }
}
