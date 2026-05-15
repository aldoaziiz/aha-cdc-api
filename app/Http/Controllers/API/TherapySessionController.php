<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TherapySession;

class TherapySessionController extends Controller
{
    public function index(Request $request)
    {
        $query = TherapySession::with([
            'therapist.staffRole',
            'room',
            'registration.child',
            'registration.program',
            'activity.photos'
        ]);

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
                        '%' . $request->search . '%'
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

        $data = $query->paginate(
            $request->per_page ?? 10
        );

        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'registration_id' => 'required|exists:registrations,id',
            'therapist_id' => 'required|exists:staff,id',
            'room_id' => 'nullable|exists:rooms,id',

            'therapy_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',

            'notes' => 'nullable'
        ]);

        // 
        if ($request->start_time >= $request->end_time) {

            return response()->json([
                'message' => 'End time must be greater than start time.'
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

        // validasi ruangan sudah ada jadwal di waktu yang sama
        if ($request->room_id) {

            $roomConflict = TherapySession::where('room_id', $request->room_id)
                ->where('therapy_date', $request->therapy_date)
                ->where(function ($q) use ($request) {

                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })
                ->exists();

            if ($roomConflict) {

                return response()->json([
                    'message' => 'Room already used at this time.'
                ], 422);
            }
        }

        // buat sesi terapi
        $session = TherapySession::create([
            'registration_id' => $request->registration_id,
            'therapist_id' => $request->therapist_id,
            'room_id' => $request->room_id,

            'therapy_date' => $request->therapy_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,

            'notes' => $request->notes
        ]);

        return response()->json([
            'message' => 'Therapy session created',
            'data' => $session
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $session = TherapySession::findOrFail($id);

        $session->delete();

        return response()->json([
            'message' => 'Session deleted'
        ]);
    }
}
