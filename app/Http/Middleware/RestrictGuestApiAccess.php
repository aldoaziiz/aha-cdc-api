<?php

namespace App\Http\Middleware;

use App\Http\Controllers\API\ActivityController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Reports\TherapistReportController;
use Closure;
use Illuminate\Http\Request;

class RestrictGuestApiAccess
{
    public function handle(
        Request $request,
        Closure $next
    ) {
        $user = $request->user();

        // Non-guest tetap berjalan seperti biasa
        if (
            ! $user ||
            $user->role !== 'guest'
        ) {
            return $next($request);
        }

        // ======================
        // GUEST ALLOWED ACTIONS
        // ======================

        $allowedActions = [

            // Auth
            AuthController::class.'@me',
            AuthController::class.'@logout',
            AuthController::class.'@changePassword',

            // Activity read only
            ActivityController::class.'@index',
            ActivityController::class.'@show',

            // Therapy Report read only
            TherapistReportController::class.'@index',

        ];

        $action =
            $request
                ->route()
                ?->getActionName();

        if (
            ! in_array(
                $action,
                $allowedActions,
                true
            )
        ) {
            abort(
                403,
                'Forbidden'
            );
        }

        return $next($request);
    }
}
