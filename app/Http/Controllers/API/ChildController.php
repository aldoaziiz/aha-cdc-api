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
        $children = \App\Models\Child::with([
            'guardian',
            'program',
            'status',
            'birthplace',
            'hometown',
            'schoolClass'
        ])->get();

        return $children->map(function ($c) {
            return [
                'id' => $c->id,
                'id_number' => $c->id_number,
                'name' => $c->name,
                'birth_date' => $c->birth_date,
                'gender' => $c->gender,
                'phone' => $c->phone,
                'address' => $c->address,
                'created_at' => $c->created_at,

                'guardian' => [
                    'guardian_id' => $c->guardian_id,
                    'name' => $c->guardian->name ?? '',
                    'phone' => $c->guardian->phone ?? '',
                ],

                'program' => [
                    'program_id' => $c->program_id,
                    'name' => $c->program->name ?? '',
                ],

                'status' => [
                    'status_id' => $c->status_id,
                    'code' => $c->status->code ?? null,
                    'name' => $c->status->name ?? '',
                ],
            ];
        });
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
