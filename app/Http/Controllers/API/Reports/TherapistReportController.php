<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TherapistReportController extends Controller
{
    public function index(Request $request)
    {
        // ======================
        // AUTHORIZATION
        // ======================

        $user = $request->user();

        if (! in_array($user?->role, ['admin', 'guest', 'therapist'], true)) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $isGuest = $user->role === 'guest';

        $isTherapist = $user->role === 'therapist';

        $currentTherapistId = $isTherapist
            ? $user->staff?->id
            : null;

        if (
            $isTherapist &&
            ! $currentTherapistId
        ) {
            return response()->json([
                'message' => 'Therapist staff data not found.',
            ], 403);
        }

        // ======================
        // VALIDATION
        // ======================

        $validated = $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',

            'therapist_id' => 'nullable|integer|exists:staff,id',
            'child_id' => 'nullable|integer|exists:children,id',

            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:10,25,50,100',

            'sort_by' => 'nullable|in:registration_number,child_name,therapist_name,therapy_date,status',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        // ======================
        // FILTER
        // ======================

        $month = $validated['month'] ?? now()->month;
        $year = $validated['year'] ?? now()->year;

        $therapistId = $isTherapist
            ? $currentTherapistId
            : ($validated['therapist_id'] ?? null);

        $childId = $validated['child_id'] ?? null;

        $perPage = $validated['per_page'] ?? 10;

        $sortBy = $validated['sort_by'] ?? 'therapy_date';
        $sortOrder = $validated['sort_order'] ?? 'asc';

        // ======================
        // BASE QUERY
        // ======================

        $baseQuery = DB::table('therapy_sessions as ts')
            ->join(
                'registrations as r',
                'r.id',
                '=',
                'ts.registration_id'
            )
            ->join(
                'children as c',
                'c.id',
                '=',
                'r.child_id'
            )
            ->join(
                'staff as s',
                's.id',
                '=',
                'ts.therapist_id'
            )
            ->join(
                'therapy_session_statuses as tss',
                'tss.id',
                '=',
                'ts.therapy_session_status_id'
            )
            ->leftJoin(
                'activities as a',
                'a.therapy_session_id',
                '=',
                'ts.id'
            )
            ->whereMonth(
                'ts.therapy_date',
                $month
            )
            ->whereYear(
                'ts.therapy_date',
                $year
            );

        // ======================
        // GUEST SCOPE
        // ======================

        if ($isGuest) {
            $baseQuery->where(
                'r.payer_id',
                1
            );
        }

        // ======================
        // THERAPIST FILTER
        // ======================

        if ($therapistId) {
            $baseQuery->where(
                'ts.therapist_id',
                $therapistId
            );
        }

        // ======================
        // CHILD FILTER
        // ======================

        if ($childId) {
            $baseQuery->where(
                'r.child_id',
                $childId
            );
        }

        // ======================
        // SUMMARY
        // ======================

        $summary = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_sessions,

                SUM(
                    CASE
                        WHEN ts.therapy_session_status_id = 2
                        THEN 1
                        ELSE 0
                    END
                ) as completed,

                SUM(
                    CASE
                        WHEN ts.therapy_session_status_id = 1
                        THEN 1
                        ELSE 0
                    END
                ) as scheduled,

                SUM(
                    CASE
                        WHEN ts.therapy_session_status_id = 3
                        THEN 1
                        ELSE 0
                    END
                ) as alpha
            ')
            ->first();

        // ======================
        // SORTING
        // ======================

        $sortColumns = [
            'registration_number' => 'r.registration_number',
            'child_name' => 'c.name',
            'therapist_name' => 's.name',
            'therapy_date' => 'ts.therapy_date',
            'status' => 'tss.name',
        ];

        $sortColumn = $sortColumns[$sortBy];

        // ======================
        // SESSION TABLE
        // ======================

        $sessionsQuery = (clone $baseQuery)
            ->select([
                'ts.id',
                'ts.registration_id',

                'r.registration_number',

                'c.name as child_name',

                's.name as therapist_name',

                'ts.therapy_date',
                'ts.start_time',
                'ts.end_time',

                'tss.name as status',

                'a.id as activity_id',
                'a.caption as activity_caption',
                'a.video as activity_video',
                'a.document as activity_document',
            ])
            ->orderBy(
                $sortColumn,
                $sortOrder
            );

        if ($sortBy === 'therapy_date') {
            $sessionsQuery->orderBy(
                'ts.start_time',
                'asc'
            );
        }

        // deterministic ordering
        $sessionsQuery->orderBy(
            'ts.id',
            'asc'
        );

        $sessions = $sessionsQuery
            ->paginate($perPage);

        // ======================
        // PROGRAMS
        // ======================

        $registrationIds = $sessions
            ->getCollection()
            ->pluck('registration_id')
            ->filter()
            ->unique()
            ->values();

        $programsByRegistration = collect();

        if ($registrationIds->isNotEmpty()) {
            $programsByRegistration = DB::table('registration_programs as rp')
                ->join(
                    'programs as p',
                    'p.id',
                    '=',
                    'rp.program_id'
                )
                ->leftJoin(
                    'program_categories as pc',
                    'pc.id',
                    '=',
                    'p.program_category_id'
                )
                ->whereIn(
                    'rp.registration_id',
                    $registrationIds
                )
                ->orderBy(
                    'p.name',
                    'asc'
                )
                ->get([
                    'rp.registration_id',

                    'p.id as program_id',
                    'p.name as program_name',

                    'pc.id as program_category_id',
                    'pc.name as program_category_name',
                ])
                ->groupBy(
                    'registration_id'
                );
        }

        // ======================
        // ACTIVITY PHOTOS
        // ======================

        $activityIds = $sessions
            ->getCollection()
            ->pluck('activity_id')
            ->filter()
            ->unique()
            ->values();

        $photosByActivity = collect();

        if ($activityIds->isNotEmpty()) {
            $photosByActivity = DB::table('activity_photos')
                ->whereIn(
                    'activity_id',
                    $activityIds
                )
                ->orderBy(
                    'id',
                    'asc'
                )
                ->get([
                    'id',
                    'activity_id',
                    'photo',
                ])
                ->groupBy(
                    'activity_id'
                );
        }

        // ======================
        // ACTIVITY ACTION TYPES
        // ======================

        $actionTypesByActivity = collect();

        if ($activityIds->isNotEmpty()) {
            $actionTypesByActivity = DB::table(
                'activity_action_assignments as aaa'
            )
                ->join(
                    'activity_action_types as aat',
                    'aat.id',
                    '=',
                    'aaa.activity_action_type_id'
                )
                ->whereIn(
                    'aaa.activity_id',
                    $activityIds
                )
                ->orderBy(
                    'aat.id',
                    'asc'
                )
                ->get([
                    'aaa.activity_id',
                    'aat.id',
                    'aat.name',
                ])
                ->groupBy(
                    'activity_id'
                );
        }

        // ======================
        // BUILD SESSION DETAILS
        // ======================

        $sessions->getCollection()->transform(
            function ($session) use (
                $programsByRegistration,
                $photosByActivity,
                $actionTypesByActivity
            ) {
                $registrationPrograms = $programsByRegistration
                    ->get(
                        $session->registration_id,
                        collect()
                    );

                // ======================
                // PROGRAM CATEGORIES
                // ======================

                $session->program_categories = $registrationPrograms
                    ->filter(function ($program) {
                        return $program->program_category_id;
                    })
                    ->map(function ($program) {
                        return [
                            'id' => $program->program_category_id,
                            'name' => $program->program_category_name,
                        ];
                    })
                    ->unique('id')
                    ->values();

                // ======================
                // PROGRAMS
                // ======================

                $session->programs = $registrationPrograms
                    ->map(function ($program) {
                        return [
                            'id' => $program->program_id,
                            'name' => $program->program_name,
                        ];
                    })
                    ->values();

                // ======================
                // ACTIVITY
                // ======================

                if ($session->activity_id) {
                    $photos = $photosByActivity
                        ->get(
                            $session->activity_id,
                            collect()
                        )
                        ->map(function ($photo) {
                            return [
                                'id' => $photo->id,
                                'photo' => $photo->photo,
                            ];
                        })
                        ->values();

                    $actionTypes = $actionTypesByActivity
                        ->get(
                            $session->activity_id,
                            collect()
                        )
                        ->map(function ($actionType) {
                            return [
                                'id' => $actionType->id,
                                'name' => $actionType->name,
                            ];
                        })
                        ->values();

                    $session->activity = [
                        'id' => $session->activity_id,
                        'caption' => $session->activity_caption,
                        'video' => $session->activity_video,
                        'document' => $session->activity_document,
                        'action_types' => $actionTypes,
                        'photos' => $photos,
                    ];
                } else {
                    $session->activity = null;
                }

                // field internal tidak perlu dikirim
                unset(
                    $session->activity_id,
                    $session->activity_caption,
                    $session->activity_video,
                    $session->activity_document
                );

                return $session;
            }
        );

        // ======================
        // THERAPISTS
        // ======================

        $therapistsQuery = DB::table('staff as s')
            ->where(
                's.staff_role_id',
                2
            );

        if ($isGuest) {
            $therapistsQuery
                ->join(
                    'therapy_sessions as ts',
                    'ts.therapist_id',
                    '=',
                    's.id'
                )
                ->join(
                    'registrations as r',
                    'r.id',
                    '=',
                    'ts.registration_id'
                )
                ->where(
                    'r.payer_id',
                    1
                );
        }

        if ($isTherapist) {
            $therapistsQuery->where(
                's.id',
                $currentTherapistId
            );
        }

        $therapists = $therapistsQuery
            ->select([
                's.id',
                's.name',
                's.status_id',
            ])
            ->distinct()
            ->orderBy(
                's.name',
                'asc'
            )
            ->get();

        // ======================
        // CHILDREN
        // ======================

        $childrenQuery = DB::table('children as c');

        if ($isGuest) {
            $childrenQuery
                ->join(
                    'registrations as r',
                    'r.child_id',
                    '=',
                    'c.id'
                )
                ->where(
                    'r.payer_id',
                    1
                );
        }

        if ($isTherapist) {
            $childrenQuery
                ->join(
                    'registrations as r',
                    'r.child_id',
                    '=',
                    'c.id'
                )
                ->join(
                    'therapy_sessions as ts',
                    'ts.registration_id',
                    '=',
                    'r.id'
                )
                ->where(
                    'ts.therapist_id',
                    $currentTherapistId
                );
        }

        $children = $childrenQuery
            ->select([
                'c.id',
                'c.name',
                'c.status_id',
            ])
            ->distinct()
            ->orderBy(
                'c.name',
                'asc'
            )
            ->get();

        // ======================
        // RESPONSE
        // ======================

        return response()->json([
            'filters' => [
                'month' => $month,
                'year' => $year,
                'therapist_id' => $therapistId,
                'child_id' => $childId,
            ],

            'therapists' => $therapists,
            'children' => $children,

            'summary' => [
                'total_sessions' => (int) ($summary->total_sessions ?? 0),
                'completed' => (int) ($summary->completed ?? 0),
                'scheduled' => (int) ($summary->scheduled ?? 0),
                'alpha' => (int) ($summary->alpha ?? 0),
            ],

            'sessions' => $sessions,
        ]);
    }
}
