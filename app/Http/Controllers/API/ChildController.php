<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\Request;
use App\Http\Resources\ChildResource;

class ChildController extends Controller
{
    // GET all
    public function index()
    {
        $children = Child::with([
            'status',
            'guardians',
            'school',
            'schoolEducation',
            'schoolClass',
            'birthplace',
            'hometown'
        ])->get();

        return ChildResource::collection($children);
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
