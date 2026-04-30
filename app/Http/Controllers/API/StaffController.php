<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use App\Http\Resources\StaffResource;

class StaffController extends Controller
{
    // GET all
    public function index()
    {
        $staffs = Staff::with([
            'staffRole',
            'status'
        ])->get();

        return StaffResource::collection($staffs);
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
        $staff = Staff::find($id, 'id');

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
        $staff = Staff::find($id, 'id');

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
