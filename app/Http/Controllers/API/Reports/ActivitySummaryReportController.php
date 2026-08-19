<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivitySummaryReportController extends Controller
{
    public function index(Request $request)
    {
        // ======================
        // AUTHORIZATION
        // ======================

        $user = $request->user();

        // Temporary permission.
        // Final permission review tetap di Phase 11.
        if ($user?->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        // ======================
        // VALIDATION
        // ======================

        $validated = $request->validate([
            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'start_month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],

            'end_month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
        ]);

        // ======================
        // FILTER
        // ======================

        $year =
            $validated['year']
            ?? now()->year;

        $startMonth =
            $validated['start_month']
            ?? 1;

        $endMonth =
            $validated['end_month']
            ?? 12;

        if ($startMonth > $endMonth) {
            return response()->json([
                'message' => 'Start month cannot be greater than end month.',
            ], 422);
        }

        $startDate = Carbon::create(
            $year,
            $startMonth,
            1
        )
            ->startOfMonth()
            ->toDateString();

        $endDate = Carbon::create(
            $year,
            $endMonth,
            1
        )
            ->endOfMonth()
            ->toDateString();

        // ======================
        // ACTION TYPES
        // ======================

        /*
         * Intentionally return ALL action types,
         * including inactive ones.
         *
         * Activity Create/Edit only returns active types,
         * but historical reports must not lose an old
         * action column if that action is later deactivated.
         */
        $actionTypes = DB::table(
            'activity_action_types'
        )
            ->orderBy(
                'id',
                'asc'
            )
            ->get([
                'id',
                'name',
            ]);

        // ======================
        // ACTIVITY SUMMARY
        // ======================

        $summaryRows = DB::table(
            'activities as a'
        )
            ->join(
                'therapy_sessions as ts',
                'ts.id',
                '=',
                'a.therapy_session_id'
            )
            ->join(
                'registrations as r',
                'r.id',
                '=',
                'ts.registration_id'
            )
            ->join(
                'staff as s',
                's.id',
                '=',
                'ts.therapist_id'
            )
            ->whereBetween(
                'ts.therapy_date',
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->select([
                's.id as therapist_id',
                's.name as therapist_name',
            ])
            ->selectRaw(
                'MONTH(ts.therapy_date) as month_number'
            )
            ->selectRaw(
                'COUNT(DISTINCT r.child_id) as patients'
            )
            ->selectRaw(
                'COUNT(DISTINCT a.id) as sessions'
            )
            ->groupBy(
                DB::raw(
                    'MONTH(ts.therapy_date)'
                ),
                's.id',
                's.name'
            )
            ->orderByRaw(
                'MONTH(ts.therapy_date) ASC'
            )
            ->orderBy(
                's.name',
                'asc'
            )
            ->get();

        // ======================
        // ACTION COUNTS
        // ======================

        $actionCounts = DB::table(
            'activity_action_assignments as aaa'
        )
            ->join(
                'activities as a',
                'a.id',
                '=',
                'aaa.activity_id'
            )
            ->join(
                'therapy_sessions as ts',
                'ts.id',
                '=',
                'a.therapy_session_id'
            )
            ->join(
                'staff as s',
                's.id',
                '=',
                'ts.therapist_id'
            )
            ->whereBetween(
                'ts.therapy_date',
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->select([
                's.id as therapist_id',
                'aaa.activity_action_type_id',
            ])
            ->selectRaw(
                'MONTH(ts.therapy_date) as month_number'
            )
            ->selectRaw(
                'COUNT(DISTINCT aaa.activity_id) as total'
            )
            ->groupBy(
                DB::raw(
                    'MONTH(ts.therapy_date)'
                ),
                's.id',
                'aaa.activity_action_type_id'
            )
            ->get();

        // ======================
        // GROUP ACTION COUNTS
        // ======================

        $actionCountsByRow =
            $actionCounts->groupBy(
                function ($item) {
                    return
                        $item->month_number
                        .'-'
                        .$item->therapist_id;
                }
            );

        // ======================
        // BUILD ROWS
        // ======================

        $rows = $summaryRows
            ->map(
                function ($row) use (
                    $actionTypes,
                    $actionCountsByRow
                ) {
                    $key =
                        $row->month_number
                        .'-'
                        .$row->therapist_id;

                    $rowActionCounts =
                        $actionCountsByRow
                            ->get(
                                $key,
                                collect()
                            );

                    $actions = [];

                    foreach (
                        $actionTypes as $actionType
                    ) {
                        $count =
                            $rowActionCounts
                                ->firstWhere(
                                    'activity_action_type_id',
                                    $actionType->id
                                );

                        $actions[
                            $actionType->id
                        ] = (int) (
                            $count->total
                            ?? 0
                        );
                    }

                    return [
                        'month_number' => (int) $row->month_number,

                        'month_name' => Carbon::create(
                            2000,
                            $row->month_number,
                            1
                        )->format('F'),

                        'therapist_id' => (int) $row->therapist_id,

                        'therapist_name' => $row->therapist_name,

                        'patients' => (int) $row->patients,

                        'sessions' => (int) $row->sessions,

                        'actions' => $actions,
                    ];
                }
            )
            ->values();

        // ======================
        // MONTHS
        // ======================

        $months = collect(
            range(
                $startMonth,
                $endMonth
            )
        )
            ->map(
                function ($month) {
                    return [
                        'number' => $month,

                        'name' => Carbon::create(
                            2000,
                            $month,
                            1
                        )->format('F'),
                    ];
                }
            )
            ->values();

        // ======================
        // RESPONSE
        // ======================

        return response()->json([
            'filters' => [
                'year' => $year,
                'start_month' => $startMonth,
                'end_month' => $endMonth,
            ],

            'months' => $months,

            'action_types' => $actionTypes,

            'data' => $rows,
        ]);
    }
}
