<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staff = \App\Models\Staff::with(['role', 'status'])->get();

        return $staff->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'title' => $s->title,
                'email' => $s->email,
                'phone' => $s->phone,
                'address' => $s->address,
                'status' => [
                    'id' => $s->status_id,
                    'name' => $s->status->name ?? '',
                ],
                'staff_role' => [
                    'id' => $s->staff_role_id,
                    'name' => $s->role->name ?? '',
                ],
            ];
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $staff = Staff::with(['status', 'role'])
            ->create($request->all());
        return response()->json($staff, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $staff = Staff::with(['status', 'role'])
            ->find($id);

        if (!$staff) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($staff);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $staff = Staff::with(['status', 'role'])
            ->find($id);

        if (!$staff) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $staff->update($request->all());

        return response()->json($staff);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $staff = Staff::with(['status', 'role'])
            ->find($id);

        if (!$staff) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $staff->delete($id);

        return response()->json(['message' => 'Deleted']);
    }
}
