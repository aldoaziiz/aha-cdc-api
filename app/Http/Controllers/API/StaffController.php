<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Staff::with([
            'status:id,name',
            'staffRole:id,name',
        ]);

        // 🔥 FILTER ROLE
        if ($request->staff_role_id) {
            $query->where(
                'staff_role_id',
                $request->staff_role_id
            );
        }

        // SEARCH
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('phone', 'like', '%'.$request->search.'%');
            });
        }

        // SORTING
        if ($request->sort_by && $request->sort_order) {
            $query->orderBy($request->sort_by, $request->sort_order);
        } else {
            $query->latest();
        }

        return $query->paginate($request->per_page ?? 10);
    }

    // store
    public function store(Request $request)
    {
        $validated = $request->validate([

            'name' => 'required|string|max:255',

            'email' => 'required|email|max:255',

            'phone' => 'nullable|string|max:255',

            'address' => 'nullable|string',

            'staff_role_id' => 'nullable|exists:staff_roles,id',

        ]);

        // DEFAULT ACTIVE

        $validated['status_id'] = 1;

        $staff = Staff::create($validated);

        return response()->json([

            'message' => 'Staff created successfully',

            'data' => $staff->load([
                'staffRole:id,name',
                'status:id,name',
            ]),

        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $staff = Staff::query()->with([
            'staffRole:id,name',
        ])->find($id);

        if (! $staff) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($staff);
    }

    // update
    public function update(
        Request $request,
        $id
    ) {
        $staff = Staff::query()
            ->find($id);

        if (! $staff) {

            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $validated = $request->validate([

            'name' => 'sometimes|string|max:255',

            'email' => 'sometimes|email|max:255',

            'phone' => 'nullable|string|max:255',

            'address' => 'nullable|string',

            'staff_role_id' => 'nullable|exists:staff_roles,id',

            'status_id' => 'nullable|exists:statuses,id',

        ]);

        $staff->update($validated);

        return response()->json([

            'message' => 'Staff updated successfully',

            'data' => $staff->load([
                'staffRole:id,name',
                'status:id,name',
            ]),

        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $staff = Staff::with(['status', 'role'])
            ->find($id);

        if (! $staff) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $staff->delete($id);

        return response()->json(['message' => 'Deleted']);
    }
}
