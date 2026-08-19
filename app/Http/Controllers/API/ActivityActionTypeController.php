<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityActionType;

class ActivityActionTypeController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (
            ! in_array(
                $user?->role,
                ['admin', 'therapist'],
                true
            )
        ) {
            abort(
                403,
                'Forbidden'
            );
        }

        $actionTypes = ActivityActionType::query()
            ->where(
                'status_id',
                1
            )
            ->orderBy(
                'id',
                'asc'
            )
            ->get([
                'id',
                'name',
            ]);

        return response()->json([
            'data' => $actionTypes,
        ]);
    }
}
