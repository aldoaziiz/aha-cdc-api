<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Registration;
use App\Models\GuardianRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RegistrationResource;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $query = Registration::with([
            'child.guardians',
            'program',
            'paymentStatus'
        ]);

        // SEARCH
        if ($request->search) {
            $query->whereHas('child', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        // PAGINATION
        $data = $query->paginate($request->per_page ?? 10);

        // 🔥 TRANSFORM GUARDIAN ROLE
        $data->getCollection()->transform(function ($registration) {

            if ($registration->child && $registration->child->guardians) {

                $registration->child->guardians->transform(function ($guardian) {

                    $guardian->guardian_role = GuardianRole::find(
                        $guardian->pivot->guardian_role_id
                    );

                    return $guardian;
                });
            }

            return $registration;
        });

        return response()->json($data);
    }

    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {

            // ======================
            // 1. CREATE CHILD OR FIND IF ID PROVIDED
            // ======================
            if (isset($request->child['id'])) {
                $child = Child::findOrFail($request->child['id']);
            } else {
                $child = Child::create($request->child);
            }

            // ======================
            // 2. CREATE GUARDIAN OR FIND IF ID PROVIDED
            // ======================
            if (isset($request->guardian['id'])) {
                $guardian = Guardian::findOrFail($request->guardian['id']);
            } else {
                $guardian = Guardian::create([
                    'id_number' => $request->guardian['id_number'] ?? null,
                    'name' => $request->guardian['name'],
                    'phone' => $request->guardian['phone'],
                    'address' => $request->guardian['address'],
                ]);
            }

            // ======================
            // 3. ATTACH PIVOT
            // ======================
            $roleId = $request->guardian['guardian_role_id'];

            try {
                $child->guardians()->attach($guardian->id, [
                    'guardian_role_id' => $roleId
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                $child->guardians()->updateExistingPivot(
                    $guardian->id,
                    ['guardian_role_id' => $roleId]
                );
            }

            // ======================
            // 4. GENERATE REG NUMBER
            // ======================
            $today = now()->format('Ymd');

            $count = Registration::whereDate('created_at', now())->count() + 1;

            $registrationNumber = 'REG-' . $today . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            // ======================
            // 5. CREATE REGISTRATION
            // ======================
            $registration = Registration::create([
                'registration_number' => $registrationNumber,
                'child_id' => $child->id,
                'complaint' => $request->registration['complaint'] ?? null,
                'program_id' => $request->registration['program_id'] ?? null,
                'payer_id' => $request->registration['payer_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Registration created successfully',
                'data' => $registration
            ]);
        });
    }

    public function show($id)
    {
        $data = Registration::with([
            'child.guardians',
            'program',
            'paymentStatus'
        ])->findOrFail($id);

        return new RegistrationResource($data);
    }

    public function uploadReceipt(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|image|max:2048', // max 2MB (backup dari server)
        ]);

        $registration = Registration::findOrFail($id);

        // simpan file
        $path = $request->file('file')->store('receipts', 'public');

        // simpan ke DB
        $registration->update([
            'payment_receipt' => $path,
            'payment_status_id' => 2
        ]);

        return response()->json([
            'message' => 'Upload success',
            'path' => $path
        ]);
    }

    public function markPaid($id)
    {
        $registration = Registration::findOrFail($id);

        $registration->update([
            'payment_status_id' => 3
        ]);

        return response()->json([
            'message' => 'Marked as paid'
        ]);
    }
}
