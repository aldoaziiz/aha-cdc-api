<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActivityPhotoController extends Controller
{
    public function destroy(
        ActivityPhoto $activityPhoto
    ) {
        $user = auth()->user();

        // ======================
        // ROLE PERMISSION
        // ======================

        if (
            ! in_array(
                $user->role,
                ['admin', 'therapist'],
                true
            )
        ) {
            abort(
                403,
                'Forbidden'
            );
        }

        // ======================
        // THERAPIST OWNERSHIP
        // ======================

        if ($user->role === 'therapist') {

            $therapistId = DB::table('activity_photos')
                ->join(
                    'activities',
                    'activity_photos.activity_id',
                    '=',
                    'activities.id'
                )
                ->join(
                    'therapy_sessions',
                    'activities.therapy_session_id',
                    '=',
                    'therapy_sessions.id'
                )
                ->where(
                    'activity_photos.id',
                    $activityPhoto->id
                )
                ->value(
                    'therapy_sessions.therapist_id'
                );

            if (
                (int) $therapistId !==
                (int) $user->staff->id
            ) {
                abort(
                    403,
                    'Forbidden'
                );
            }
        }

        // ======================
        // DELETE FILE
        // ======================

        Storage::delete(
            $activityPhoto->photo
        );

        // ======================
        // DELETE DB
        // ======================

        $activityPhoto->delete();

        return response()->json([
            'message' => 'Photo deleted successfully',
        ]);
    }
}
