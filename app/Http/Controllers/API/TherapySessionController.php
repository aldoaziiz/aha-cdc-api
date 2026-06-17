<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TherapySessionController extends Controller
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
        $user = auth()->user();
        if (
            $user->role ===
            'guardian'
        ) {

            abort(
                403,
                'Forbidden'
            );

        }

        $query = TherapySession::with([
            'therapist.staffRole',
            'registration.child',
            'registration.programs',
            'activity.photos',
        ]);

        // ======================
        // THERAPIST FILTER
        // ======================

        if (
            $user->role ===
            'therapist'
        ) {

            $query->where(
                'therapist_id',
                $user->staff->id
            );

        }

        // ======================
        // SEARCH CHILD
        // ======================

        if ($request->search) {

            $query->whereHas(
                'registration.child',
                function ($q) use ($request) {

                    $q->where(
                        'name',
                        'like',
                        '%'.$request->search.'%'
                    );
                }
            );
        }

        // ======================
        // FILTER DATE
        // ======================

        if ($request->therapy_date) {

            $query->whereDate(
                'therapy_date',
                $request->therapy_date
            );
        }

        // ======================
        // FILTER THERAPIST
        // ======================

        if ($request->therapist_id) {

            $query->where(
                'therapist_id',
                $request->therapist_id
            );
        }

        // ======================
        // FILTER REGISTRATION
        // ======================

        if ($request->registration_id) {

            $query->where(
                'registration_id',
                $request->registration_id
            );
        }

        // ======================
        // WITHOUT ACTIVITY
        // ======================

        if ($request->without_activity) {

            $query->whereDoesntHave('activity');
        }

        // ======================
        // SORTING
        // ======================

        $query->orderBy('therapy_date')
            ->orderBy('start_time');

        // ======================
        // PAGINATION
        // ======================

        if ($request->registration_id) {

            return response()->json([
                'data' => $query->get(),
            ]);
        }

        $data = $query->paginate(
            $request->per_page ?? 10
        );

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $this->forbidNonAdmin();

        $request->validate([
            'registration_id' => 'required|exists:registrations,id',
            'therapist_id' => 'required|exists:staff,id',
            'therapy_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',

            'notes' => 'nullable',
        ]);

        //
        if ($request->start_time >= $request->end_time) {

            return response()->json([
                'message' => 'End time must be greater than start time.',
            ], 422);
        }

        $therapistConflict = TherapySession::where('therapist_id', $request->therapist_id)
            ->where('therapy_date', $request->therapy_date)
            ->where(function ($q) use ($request) {

                $q->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        // validasi terapis sudah ada jadwal di waktu yang sama
        if ($therapistConflict) {
            return response()->json(['message' => 'Therapist is already booked for the selected time'], 422);
        }

        // buat sesi terapi
        $session = TherapySession::create([
            'registration_id' => $request->registration_id,
            'therapist_id' => $request->therapist_id,
            'therapy_date' => $request->therapy_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,

            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Therapy session created',
            'data' => $session,
        ]);
    }

    public function show(string $id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $this->forbidNonAdmin();

        $session = TherapySession::findOrFail($id);

        // LOCK
        if ($session->activity) {
            return response()->json([
                'message' => 'Completed sessions cannot be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'therapist_id' => 'required|exists:staff,id',
            'therapy_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'notes' => 'nullable|string',
        ]);

        // therapist conflict
        $conflict = TherapySession::where(
            'therapist_id',
            $validated['therapist_id']
        )
            ->where('id', '!=', $session->id)
            ->whereDate(
                'therapy_date',
                $validated['therapy_date']
            )
            ->where(function ($query) use ($validated) {
                $query
                    ->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'Therapist already has another session.',
            ], 422);
        }

        $session->update($validated);

        return response()->json([
            'message' => 'Session updated successfully.',
            'data' => $session->fresh(['therapist']),
        ]);
    }

    public function destroy($id)
    {
        $this->forbidNonAdmin();

        $session = TherapySession::findOrFail($id);

        if ($session->activity) {
            return response()->json([
                'message' => 'Completed sessions cannot be deleted.',
            ], 422);
        }
        $session->delete();

        return response()->json([
            'message' => 'Session deleted',
        ]);
    }

    public function generate(Request $request)
    {
        $this->forbidNonAdmin();

        $validated = $request->validate([
            'registration_id' => 'required|exists:registrations,id',

            'therapist_id' => 'required|exists:staff,id',

            'days' => 'required|array|min:1',

            'days.*' => 'integer|between:0,6',

            'start_date' => 'required|date',

            'start_time' => 'required',

            'end_time' => 'required|after:start_time',

            'notes' => 'nullable|string',
        ]);

        $registration = Registration::with(
            'programs'
        )->findOrFail(
            $validated['registration_id']
        );

        $totalSessions = $registration
            ->programs
            ->sum('session_count');

        if ($totalSessions <= 0) {

            return response()->json([
                'message' => 'Selected programs do not have sessions.',
            ], 422);
        }

        if (
            TherapySession::where(
                'registration_id',
                $registration->id
            )->exists()
        ) {

            return response()->json([
                'message' => 'Sessions have already been generated.',
            ], 422);
        }

        return DB::transaction(function () use (
            $validated,
            $totalSessions
        ) {

            $generatedDates = [];

            $currentDate = Carbon::parse(
                $validated['start_date']
            );

            // ======================
            // GENERATE DATES
            // ======================

            while (
                count($generatedDates)
                < $totalSessions
            ) {

                $dayOfWeek =
                    $currentDate->dayOfWeek;

                if (
                    in_array(
                        $dayOfWeek,
                        $validated['days']
                    )
                ) {

                    $generatedDates[] =
                        $currentDate->copy();
                }

                $currentDate->addDay();
            }

            // ======================
            // CHECK CONFLICTS
            // ======================

            $conflicts = [];

            foreach (
                $generatedDates as $date
            ) {

                $exists =
                    TherapySession::where(
                        'therapist_id',
                        $validated['therapist_id']
                    )
                        ->whereDate(
                            'therapy_date',
                            $date
                        )
                        ->where(function ($query) use ($validated) {

                            $query
                                ->where(
                                    'start_time',
                                    '<',
                                    $validated['end_time']
                                )
                                ->where(
                                    'end_time',
                                    '>',
                                    $validated['start_time']
                                );
                        })
                        ->exists();

                if ($exists) {

                    $conflicts[] = [
                        'date' => $date
                            ->format('Y-m-d'),
                    ];
                }
            }

            if (! empty($conflicts)) {

                return response()->json([
                    'message' => 'Therapist conflict detected.',

                    'conflicts' => $conflicts,
                ], 422);
            }

            // ======================
            // CREATE SESSIONS
            // ======================

            $sessions = [];

            foreach (
                $generatedDates as $date
            ) {

                $sessions[] =
                    TherapySession::create([

                        'registration_id' => $validated[
                                'registration_id'
                            ],

                        'therapist_id' => $validated[
                                'therapist_id'
                            ],

                        'therapy_date' => $date->format(
                            'Y-m-d'
                        ),

                        'start_time' => $validated[
                                'start_time'
                            ],

                        'end_time' => $validated[
                                'end_time'
                            ],

                        'notes' => $validated[
                                'notes'
                            ] ?? null,
                    ]);
            }

            return response()->json([
                'message' => count($sessions)
                    .' sessions generated successfully.',

                'target_sessions' => $totalSessions,

                'data' => $sessions,
            ]);
        });
    }
}
