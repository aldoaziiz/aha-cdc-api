<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    // GET all
    public function index()
    {
        $children = Child::with([
            'program:id,name',
            'status:id,code,name',
            'guardian:id,name',
            'birthplace:id,name',
            'hometown:id,name',
        ])
            ->get()
            ->map(function ($child) {
                return [
                    'id' => $child->id,
                    'id_number' => $child->id_number,
                    'name' => $child->name,
                    'birth_date' => $child->birth_date,
                    'gender' => $child->gender,
                    'guardian_name' => $child->guardian->name ?? '',
                    'phone' => $child->phone,
                    'address' => $child->address,
                    'program_name' => $child->program->name ?? '',
                    'status_code' => $child->status->code ?? null,
                    'status_name' => $child->status->name ?? '',
                    'created_at' => $child->created_at,
                    'birthplace_name' => $child->birthplace->name ?? '',
                    'hometown_name' => $child->hometown->name ?? '',
                ];
            });

        return response()->json($children);
    }

    // POST create
    public function store(Request $request)
    {
        $child = Child::create($request->all());
        return response()->json($child, 201);
    }

    // GET by id
    public function show($id)
    {
        $child = Child::find($id, 'id');

        if (!$child) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($child);
    }

    // PUT update
    public function update(Request $request, $id)
    {
        $child = Child::find($id, 'id');

        if (!$child) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $child->update($request->all());

        return response()->json($child);
    }

    // DELETE
    public function destroy($id)
    {
        $child = Child::find($id, 'id');

        if (!$child) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $child->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
