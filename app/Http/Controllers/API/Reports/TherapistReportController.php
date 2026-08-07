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

        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        // ======================
        // VALIDATION
        // ======================

        $validated = $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',

            'therapist_id' => 'nullable|integer|exists:staff,id',

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

        $therapistId = $validated['therapist_id'] ?? null;

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
            ->whereMonth('ts.therapy_date', $month)
            ->whereYear('ts.therapy_date', $year);

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
                'r.registration_number',
                'c.name as child_name',
                's.name as therapist_name',
                'ts.therapy_date',
                'ts.start_time',
                'ts.end_time',
                'tss.name as status',
            ])
            ->orderBy(
                $sortColumn,
                $sortOrder
            );

        // Kalau sort berdasarkan tanggal,
        // urutkan session pada hari yang sama berdasarkan jam.
        if ($sortBy === 'therapy_date') {
            $sessionsQuery->orderBy(
                'ts.start_time',
                'asc'
            );
        }

        // deterministic ordering untuk pagination
        $sessionsQuery->orderBy(
            'ts.id',
            'asc'
        );

        $sessions = $sessionsQuery
            ->paginate($perPage);

        // ======================
        // THERAPISTS
        // ======================

        $therapists = DB::table('staff')
            ->where(
                'staff_role_id',
                2
            )
            ->orderBy(
                'name',
                'asc'
            )
            ->get([
                'id',
                'name',
                'status_id',
            ]);

        // ======================
        // RESPONSE
        // ======================

        return response()->json([
            'filters' => [
                'month' => $month,
                'year' => $year,
                'therapist_id' => $therapistId,
            ],

            'therapists' => $therapists,

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
