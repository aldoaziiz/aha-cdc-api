<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Registration;
use App\Models\User;
use App\Services\Auth\CreateGuardianUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicRegistrationController extends Controller
{
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {

            // ======================
            // VALIDATION
            // ======================

            $request->validate([

                'child.name' => 'required|string|max:255',
                'child.id_number' => 'required|string|max:255',
                'child.birth_date' => 'required|date',

                'guardian.name' => 'required|string|max:255',
                'guardian.email' => 'required|email',
                'guardian.phone' => 'required|string|max:255',
                'guardian.guardian_role_id' => 'required|integer',

                'registration.program_id' => 'required',
                'registration.payer_id' => 'required',

            ]);

            // ======================
            // CHECK EMAIL
            // ======================

            $email = $request->guardian['email'];

            $emailExists = User::where(
                'email',
                $email
            )->exists();

            if ($emailExists) {

                return response()->json([

                    'message' => 'Email already registered',

                ], 422);

            }

            // ======================
            // CREATE CHILD
            // ======================

            $child = Child::create(
                $request->child
            );

            // ======================
            // CREATE GUARDIAN
            // ======================

            $guardian = Guardian::create([

                'id_number' => $request->guardian['id_number']
                    ?? null,

                'name' => $request->guardian['name'],

                'email' => $request->guardian['email'],

                'phone' => $request->guardian['phone'],

                'address' => $request->guardian['address']
                    ?? null,

            ]);

            // ======================
            // CREATE USER ACCOUNT
            // ======================

            $userService =
                new CreateGuardianUserService;

            $user =
                $userService->execute(
                    $guardian->email,
                    $guardian->name,
                    $guardian->phone
                );

            $guardian->update([

                'user_id' => $user->id,

            ]);

            // ======================
            // ATTACH GUARDIAN
            // ======================

            $child->guardians()->attach(
                $guardian->id,
                [
                    'guardian_role_id' => $request->guardian['guardian_role_id'],
                ]
            );

            // ======================
            // GENERATE REG NUMBER
            // ======================

            $today = now()->format('Ymd');

            $count =
                Registration::whereDate(
                    'created_at',
                    today()
                )->count() + 1;

            $registrationNumber =
                'REG-'.
                $today.
                '-'.
                str_pad(
                    $count,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            // ======================
            // CREATE REGISTRATION
            // ======================

            $registration =
                Registration::create([

                    'registration_number' => $registrationNumber,

                    'child_id' => $child->id,

                    'complaint' => $request->registration['complaint']
                        ?? null,

                    'program_id' => $request->registration['program_id']
                        ?? null,

                    'payer_id' => $request->registration['payer_id']
                        ?? null,

                    'clinic_id' => $request->registration['clinic_id']
                        ?? null,

                ]);

            return response()->json([

                'message' => 'Registration created successfully',

                'data' => $registration,

            ], 201);
        });
    }
}
