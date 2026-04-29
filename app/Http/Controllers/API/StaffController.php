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
                'role_name' => $s->role->name ?? '',
                'status_name' => $s->status->name ?? '',
                'status_code' => $s->status->code ?? null,
            ];
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $guardian = Staff::with(['status', 'role'])
            ->create($request->all());
        return response()->json($guardian, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $guardian = Staff::with(['status', 'role'])
            ->find($id);

        if (!$guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($guardian);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $guardian = Staff::with(['status', 'role'])
            ->find($id);

        if (!$guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $guardian->update($request->all());

        return response()->json($guardian);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $guardian = Staff::with(['status', 'role'])
            ->find($id);

        if (!$guardian) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $guardian->delete($id);

        return response()->json(['message' => 'Deleted']);
    }
}
