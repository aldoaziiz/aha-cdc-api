<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Auth\CreateGuardianUserService;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    private function forbidNonAdmin()
    {
        if (
            auth()->user()->role !==
            'admin'
        ) {

            abort(
                403,
                'Forbidden'
            );

        }
    }

    public function index(Request $request)
    {
        $query = Guardian::with(
            'status:id,name'
        );

        // SEARCH

        if ($request->search) {

            $query->where(
                function ($q) use ($request) {

                    $q->where(
                        'name',
                        'like',
                        '%'.$request->search.'%'
                    )

                        ->orWhere(
                            'phone',
                            'like',
                            '%'.$request->search.'%'
                        );

                }
            );
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

        return $query->paginate(
            $request->per_page ?? 10
        );
    }

    // ======================
    // CREATE
    // ======================

    public function store(Request $request)
    {
        $this->forbidNonAdmin();

        // ======================
        // VALIDATION
        // ======================

        $validated =
            $request->validate([

                'name' => 'required|string|max:255',

                'email' => [

                    'required',

                    'email',

                ],

                'id_number' => 'nullable|string|max:255',

                'address' => 'nullable|string',

                'phone' => 'nullable|string|max:255',

            ]);

        // ======================
        // CHECK EMAIL
        // ======================

        $emailExists =
            User::where(

                'email',

                $validated['email']

            )->exists();

        if ($emailExists) {

            return response()->json([

                'message' => 'Email already registered',

            ], 422);

        }

        // ======================
        // DEFAULT STATUS
        // ======================

        $validated['status_id'] = 1;

        // ======================
        // CREATE GUARDIAN
        // ======================

        $guardian =
            Guardian::create(
                $validated
            );

        // ======================
        // CREATE USER
        // ======================

        $userService =
            new CreateGuardianUserService;

        $user =
            $userService->execute(

                $guardian->name,

                $guardian->email,

                $guardian->phone

            );

        // ======================
        // CONNECT USER
        // ======================

        $guardian->update([

            'user_id' => $user->id,

        ]);

        return response()->json(

            $guardian->load([

                'status:id,name',

                'role:id,name',

            ]),

            201

        );
    }

    // ======================
    // SHOW
    // ======================

    public function show($id)
    {
        $guardian =
            Guardian::with([

                'status',

                'children:id,name',

            ])->find($id);

        if (! $guardian) {

            return response()->json([

                'message' => 'Not found',

            ], 404);

        }

        return response()->json(
            $guardian
        );
    }

    // ======================
    // UPDATE
    // ======================

    public function update(
        Request $request,
        $id
    ) {
        $this->forbidNonAdmin();

        $guardian =
            Guardian::with([

                'status',

                'role',

            ])->find($id);

        if (! $guardian) {

            return response()->json([

                'message' => 'Not found',

            ], 404);

        }

        $guardian->update(
            $request->all()
        );

        return response()->json(
            $guardian
        );
    }

    // ======================
    // DELETE
    // ======================

    public function destroy($id)
    {
        $this->forbidNonAdmin();

        $guardian =
            Guardian::with([

                'status',

                'role',

            ])->find($id);

        if (! $guardian) {

            return response()->json([

                'message' => 'Not found',

            ], 404);

        }

        $guardian->delete($id);

        return response()->json([

            'message' => 'Deleted',

        ]);
    }
}
