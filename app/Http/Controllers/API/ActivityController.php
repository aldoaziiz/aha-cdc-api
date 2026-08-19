<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityPhoto;
use App\Models\TherapySession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActivityController extends Controller
{
    private function forbidReadOnly()
    {
        if (
            ! in_array(
                auth()->user()->role,
                ['admin', 'therapist'],
                true
            )
        ) {
            abort(
                403,
                'Forbidden'
            );
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Activity::with([
            'photos',
            'actionTypes',
            'therapySession.registration.child',
            'therapySession.registration.programs',
            'therapySession.therapist',
        ])
            ->join(
                'therapy_sessions',
                'activities.therapy_session_id',
                '=',
                'therapy_sessions.id'
            )
            ->orderByDesc(
                'therapy_sessions.therapy_date'
            )
            ->orderByDesc(
                'therapy_sessions.start_time'
            )
            ->select('activities.*');

        // ======================
        // GUARDIAN FILTER
        // ======================

        if ($user->role === 'guardian') {

            $guardian =
                $user->guardian;

            $childIds =
                $guardian
                    ->children()
                    ->pluck('children.id');

            $query->whereHas(
                'therapySession.registration',
                function ($q) use ($childIds) {

                    $q->whereIn(
                        'child_id',
                        $childIds
                    );

                }
            );

        }

        // ======================
        // THERAPIST FILTER
        // ======================

        if ($user->role === 'therapist') {

            $query->whereHas(
                'therapySession',
                function ($q) use ($user) {

                    $q->where(
                        'therapist_id',
                        $user->staff->id
                    );

                }
            );

        }

        // ======================
        // GUEST FILTER
        // ======================

        if ($user->role === 'guest') {

            $query->whereHas(
                'therapySession.registration',
                function ($q) {

                    $q->where(
                        'payer_id',
                        1
                    );

                }
            );
        }

        // ======================
        // SEARCH CHILD
        // ======================

        if ($request->search) {

            $query->whereHas(
                'therapySession.registration.child',
                function ($q) use ($request) {

                    $q->where(
                        'name',
                        'like',
                        '%'.$request->search.'%'
                    );
                }
            );
        }

        return response()->json(
            $query->paginate(
                $request->per_page ?? 10
            )
        );
    }

    public function store(Request $request)
    {
        $this->forbidReadOnly();

        // ======================
        // VALIDATION
        // ======================

        $request->validate([

            'therapy_session_id' => [
                'required',
                'exists:therapy_sessions,id',
                'unique:activities,therapy_session_id',
            ],

            'caption' => 'nullable|string',

            'action_type_ids' => [
                'nullable',
                'array',
            ],

            'action_type_ids.*' => [
                'integer',
                'distinct',
                'exists:activity_action_types,id',
            ],

            'document' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:10240',
            ],

            'video' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
                'max:102400',
            ],

            'photos.*' => 'nullable|image|max:5120',

        ]);

        // ======================
        // SESSION STATUS
        // ======================

        $therapySession = TherapySession::findOrFail(
            $request->therapy_session_id
        );

        // ======================
        // THERAPIST OWNERSHIP
        // ======================

        if (
            auth()->user()->role === 'therapist' &&
            (int) $therapySession->therapist_id !==
            (int) auth()->user()->staff->id
        ) {
            abort(
                403,
                'Forbidden'
            );
        }

        if ($therapySession->therapy_session_status_id === 3) {

            return response()->json([
                'message' => 'Cannot create activity for Alpha session.',
            ], 422);
        }

        // ======================
        // MINIMUM CONTENT
        // ======================

        if (
            ! $request->caption &&
            ! $request->hasFile('video') &&
            ! $request->hasFile('photos') &&
            ! $request->hasFile('document') &&
            empty($request->action_type_ids)
        ) {

            return response()->json([
                'message' => 'Activity content is required.',
            ], 422);
        }

        // ======================
        // UPLOAD VIDEO
        // ======================

        $videoPath = null;

        if ($request->hasFile('video')) {

            $videoPath = $request
                ->file('video')
                ->store(
                    'activities/videos'
                );
        }

        // ======================
        // UPLOAD DOCUMENT
        // ======================

        $documentPath = null;

        if ($request->hasFile('document')) {

            $documentPath = $request
                ->file('document')
                ->store(
                    'documents'
                );
        }

        // ======================
        // CREATE ACTIVITY
        // ======================

        $activity = Activity::create([

            'therapy_session_id' => $request->therapy_session_id,

            'caption' => $request->caption,

            'video' => $videoPath,

            'document' => $documentPath,

        ]);

        // ======================
        // ACTION TYPES
        // ======================

        $activity
            ->actionTypes()
            ->sync(
                $request->action_type_ids ?? []
            );

        $activity->therapySession()->update([
            'therapy_session_status_id' => 2,
        ]);

        // ======================
        // UPLOAD PHOTOS
        // ======================

        if ($request->hasFile('photos')) {

            foreach (
                $request->file('photos') as $photo
            ) {

                $photoPath = $photo->store(
                    'activities/photos'
                );

                ActivityPhoto::create([

                    'activity_id' => $activity->id,

                    'photo' => $photoPath,

                ]);
            }
        }

        // ======================
        // RESPONSE
        // ======================

        return response()->json([
            'message' => 'Activity created successfully',

            'data' => $activity->load([
                'photos',
                'actionTypes',
                'therapySession.registration.child',
                'therapySession.registration.programs',
                'therapySession.therapist',
            ]),
        ]);
    }

    public function destroy(Activity $activity)
    {
        $this->forbidReadOnly();

        // ======================
        // THERAPIST OWNERSHIP
        // ======================

        if (
            auth()->user()->role ===
            'therapist'
        ) {

            if (

                $activity
                    ->therapySession
                    ->therapist_id

                !==

                auth()->user()
                    ->staff
                    ->id

            ) {

                abort(
                    403,
                    'Forbidden'
                );

            }

        }

        // ======================
        // DELETE PHOTOS
        // ======================

        foreach ($activity->photos as $photo) {

            Storage::delete($photo->photo);
        }

        // ======================
        // DELETE VIDEO
        // ======================

        if ($activity->video) {

            Storage::delete($activity->video);
        }

        // ======================
        // DELETE DOCUMENT
        // ======================

        if ($activity->document) {

            Storage::delete(
                $activity->document
            );
        }

        // ======================
        // UPDATE SESSION STATUS
        // ======================

        $activity->therapySession()->update([
            'therapy_session_status_id' => 1,
        ]);

        // ======================
        // DELETE ACTIVITY
        // ======================

        $activity->delete();

        return response()->json([
            'message' => 'Activity deleted successfully',
        ]);
    }

    public function show(Activity $activity)
    {
        $user = auth()->user();

        $activity->load([
            'photos',
            'actionTypes',
            'therapySession.registration.child',
            'therapySession.registration.programs',
            'therapySession.therapist',
        ]);

        if (
            $user->role === 'guest' &&
            (int) $activity
                ->therapySession
                ->registration
                ->payer_id !== 1
        ) {
            abort(
                403,
                'Forbidden'
            );
        }

        return response()->json([
            'data' => $activity,
        ]);
    }

    public function update(Request $request, Activity $activity)
    {

        $this->forbidReadOnly();

        // ======================
        // VALIDATION
        // ======================

        $request->validate([

            'caption' => 'nullable|string',

            'action_type_ids' => [
                'nullable',
                'array',
            ],

            'action_type_ids.*' => [
                'integer',
                'distinct',
                'exists:activity_action_types,id',
            ],

            'document' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:10240',
            ],

            'video' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
                'max:102400',
            ],

            'photos.*' => 'nullable|image|max:5120',

        ]);

        // ======================
        // SESSION STATUS
        // ======================

        $therapySession = $activity->therapySession;

        if ($therapySession->therapy_session_status_id === 3) {

            return response()->json([
                'message' => 'Cannot update activity for Alpha session.',
            ], 422);
        }

        if (
            auth()->user()->role ===
            'therapist'
        ) {

            if (

                $activity
                    ->therapySession
                    ->therapist_id

                !==

                auth()->user()
                    ->staff
                    ->id

            ) {

                abort(403);

            }

        }

        // ======================
        // UPDATE VIDEO
        // ======================

        $videoPath = $activity->video;

        if ($request->hasFile('video')) {

            // DELETE OLD VIDEO

            if ($activity->video) {

                Storage::delete($activity->video);
            }

            // STORE NEW VIDEO

            $videoPath = $request
                ->file('video')
                ->store(
                    'activities/videos'
                );
        }

        // ======================
        // UPDATE DOCUMENT
        // ======================

        $documentPath = $activity->document;

        if ($request->hasFile('document')) {

            // DELETE OLD DOCUMENT

            if ($activity->document) {

                Storage::delete(
                    $activity->document
                );
            }

            // STORE NEW DOCUMENT

            $documentPath = $request
                ->file('document')
                ->store(
                    'documents'
                );
        }

        // ======================
        // UPDATE ACTIVITY
        // ======================

        $activity->update([

            'caption' => $request->caption,

            'video' => $videoPath,

            'document' => $documentPath,

        ]);

        // ======================
        // UPDATE ACTION TYPES
        // ======================

        $activity
            ->actionTypes()
            ->sync(
                $request->action_type_ids ?? []
            );

        // ======================
        // ADD NEW PHOTOS
        // ======================

        if ($request->hasFile('photos')) {

            foreach (
                $request->file('photos') as $photo
            ) {

                $photoPath = $photo->store(
                    'activities/photos'
                );

                ActivityPhoto::create([

                    'activity_id' => $activity->id,

                    'photo' => $photoPath,

                ]);
            }
        }

        // ======================
        // RESPONSE
        // ======================

        return response()->json([

            'message' => 'Activity updated successfully',

            'data' => $activity->load([

                'photos',

                'actionTypes',

                'therapySession.registration.child',

                'therapySession.registration.programs',

                'therapySession.therapist',

            ]),

        ]);
    }

    public function deleteVideo(Activity $activity)
    {

        $this->forbidReadOnly();

        // ======================
        // THERAPIST OWNERSHIP
        // ======================

        if (
            auth()->user()->role ===
            'therapist'
        ) {

            if (

                $activity
                    ->therapySession
                    ->therapist_id

                !==

                auth()->user()
                    ->staff
                    ->id

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

        if ($activity->video) {

            Storage::delete($activity->video);
        }

        // ======================
        // UPDATE DB
        // ======================

        $activity->update([
            'video' => null,
        ]);

        return response()->json([

            'message' => 'Video deleted successfully',

        ]);
    }
}
