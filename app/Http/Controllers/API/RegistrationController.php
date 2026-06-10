<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Child;
use App\Models\Clinic;
use App\Models\Guardian;
use App\Models\GuardianRole;
use App\Models\Payer;
use App\Models\Program;
use App\Models\ProgramCategory;
use App\Models\Registration;
use App\Models\RegistrationProgram;
use App\Models\User;

use App\Services\Auth\CreateGuardianUserService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
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

    private function forbidTherapist()
    {
        if (
            auth()->user()->role ===
            'therapist'
        ) {

            abort(
                403,
                'Forbidden'
            );

        }
    }

    public function index(Request $request)
    {
        $query = Registration::orderBy('created_at', 'desc')->with([
            'child.guardians',
            'programs',
            'paymentStatus',
        ]);

        // SEARCH
        if ($request->search) {
            $query->whereHas('child', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
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
            // VALIDATION
            // ======================

            $rules = [];

            if (
                ! isset(
                    $request->guardian['id']
                )
            ) {

                $rules['guardian.email'] = [

                    'required',

                    'email',

                ];
            }

            $request->validate(
                $rules
            );

            // ======================
            // CHECK EMAIL
            // ======================

            if (
                ! isset(
                    $request->guardian['id']
                )
            ) {

                $email =
                    $request->guardian['email'];

                $emailExists =
                    User::where(
                        'email',
                        $email
                    )->exists();

                if ($emailExists) {

                    return response()->json([

                        'message' => 'Email already registered',

                    ], 422);

                }
            }

            // ======================
            // 1. CREATE CHILD
            // OR FIND IF ID PROVIDED
            // ======================

            if (
                isset(
                    $request->child['id']
                )
            ) {

                $child =
                    Child::findOrFail(
                        $request->child['id']
                    );

            } else {

                $child =
                    Child::create(
                        $request->child
                    );

            }

            // ======================
            // 2. CREATE GUARDIAN
            // OR FIND IF ID PROVIDED
            // ======================

            if (
                isset(
                    $request->guardian['id']
                )
            ) {

                $guardian =
                    Guardian::findOrFail(
                        $request->guardian['id']
                    );

            } else {

                $guardian =
                    Guardian::create([

                        'id_number' => $request->guardian['id_number']
                            ?? null,

                        'name' => $request->guardian['name'],

                        'email' => $request->guardian['email'],

                        'phone' => $request->guardian['phone'],

                        'occupation' => $request->guardian['occupation']
                            ?? null,

                        'social_media' => $request->guardian['social_media']
                            ?? null,

                        'address' => $request->guardian['address'],

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
            }

            // ======================
            // 3. ATTACH PIVOT
            // ======================

            $roleId =
                $request->guardian[
                    'guardian_role_id'
                ];

            try {

                $child->guardians()->attach(
                    $guardian->id,
                    [
                        'guardian_role_id' => $roleId,
                    ]
                );

            } catch (QueryException $e) {

                $child
                    ->guardians()
                    ->updateExistingPivot(
                        $guardian->id,
                        [
                            'guardian_role_id' => $roleId,
                        ]
                    );
            }

            // ======================
            // 4. GENERATE REG NUMBER
            // ======================

            $today =
                now()->format('Ymd');

            $count =
                Registration::whereDate(
                    'created_at',
                    now()
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
            // 5. CREATE REGISTRATION
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

            // ======================
            // 6. CREATE
            // REGISTRATION PROGRAMS
            // ======================

            if (
                ! empty(
                    $request->registration['program_ids']
                )
            ) {

                foreach (
                    $request->registration['program_ids'] as $programId
                ) {

                    $program =
                        Program::find($programId);

                    if (! $program) {
                        continue;
                    }

                    RegistrationProgram::create([

                        'registration_id' => $registration->id,

                        'program_id' => $program->id,

                        'price' => $program->price,

                    ]);
                }

            } elseif (
                ! empty(
                    $request->registration['program_id']
                )
            ) {

                $program =
                    Program::find(
                        $request->registration['program_id']
                    );

                if ($program) {

                    RegistrationProgram::create([

                        'registration_id' => $registration->id,

                        'program_id' => $program->id,

                        'price' => $program->price,

                    ]);
                }
            }

            return response()->json([

                'message' => 'Registration created successfully',

                'data' => $registration,

            ]);
        });
    }

    public function show($id)
    {
        $data = Registration::with([
            'child.guardians',
            'clinic',
            'programs',
            'paymentStatus',
            'payer',
        ])->findOrFail($id);

        return new RegistrationResource($data);
    }

    public function update(Request $request, $id)
    {
        $this->forbidNonAdmin();

        return DB::transaction(function () use ($request, $id) {

            $registration = Registration::findOrFail($id);

            // ======================
            // LOCK IF NOT UNPAID
            // ======================

            if ($registration->payment_status_id != 1) {

                return response()->json([
                    'message' => 'This registration can no longer be edited.',
                ], 422);

            }

            // ======================
            // VALIDATION
            // ======================

            $validated = $request->validate([
                'clinic_id' => 'required|exists:clinics,id',
                'program_category_id' => 'required|exists:program_categories,id',
                'program_ids' => 'required|array|min:1',
                'program_ids.*' => 'exists:programs,id',

                'payer_id' => 'nullable|exists:payers,id',

                'complaint' => 'nullable|string',
            ]);

            // ======================
            // UPDATE REGISTRATION
            // ======================

            $registration->update([
                'clinic_id' => $validated['clinic_id'],
                'program_id' => $validated['program_ids'][0],

                'payer_id' => $validated['payer_id'] ?? null,

                'complaint' => $validated['complaint'] ?? null,

            ]);

            // ======================
            // REPLACE PROGRAMS
            // ======================

            $registration
                ->registrationPrograms()
                ->delete();

            foreach ($validated['program_ids'] as $programId) {

                $program = Program::find($programId);

                RegistrationProgram::create([

                    'registration_id' => $registration->id,

                    'program_id' => $program->id,

                    'price' => $program->price,

                ]);
            }

            return response()->json([

                'message' => 'Registration updated successfully',

                'data' => $registration->load([
                    'programs',
                    'payer',
                    'paymentStatus',
                ]),
            ]);
        });
    }

    public function uploadReceipt(Request $request, $id)
    {
        $this->forbidTherapist();

        $request->validate([
            'file' => 'required|image|max:2048', // max 2MB (backup dari server)
        ]);

        $registration = Registration::findOrFail($id);

        // simpan file
        $path = $request->file('file')->store('receipts', 'public');

        // simpan ke DB
        $registration->update([
            'payment_receipt' => $path,
            'payment_status_id' => 2,
        ]);

        return response()->json([
            'message' => 'Upload success',
            'path' => $path,
        ]);
    }

    public function markPaid($id)
    {
        $this->forbidNonAdmin();

        $registration = Registration::findOrFail($id);

        $registration->update([
            'payment_status_id' => 3,
        ]);

        return response()->json([
            'message' => 'Marked as paid',
        ]);
    }

    public function generateInvoiceLink(Registration $registration)
    {
        if (
            ! $registration->invoice_token
        ) {

            $registration->update([

                'invoice_token' => Str::uuid(),

            ]);
        }

        return response()->json([

            'token' => $registration->invoice_token,

        ]);
    }

    public function uploadReceiptByToken(
        Request $request,
        $token
    ) {
        $registration =
            Registration::where(
                'invoice_token',
                $token
            )->firstOrFail();

        $request->validate([

            'receipt' => [
                'required',
                'image',
                'max:5120',
            ],

        ]);

        $receiptPath =
            $request
                ->file('receipt')
                ->store(
                    'receipts',
                    'public'
                );

        $registration->update([
            'payment_receipt' => $receiptPath,
            'payment_status_id' => 2,
        ]);

        return response()->json([

            'message' => 'Receipt uploaded successfully',

        ]);
    }

    public function invoiceByToken(
        $token
    ) {
        $registration =
            Registration::with([

                'child.guardians',
                'program',
                'payer',
                'paymentStatus',

            ])
                ->where(
                    'invoice_token',
                    $token
                )
                ->firstOrFail();

        if (
            $registration->child &&
            $registration->child->guardians
        ) {

            $registration
                ->child
                ->guardians
                ->transform(function ($guardian) {

                    $guardian->guardian_role =
                        GuardianRole::find(
                            $guardian
                                ->pivot
                                ->guardian_role_id
                        );

                    return $guardian;
                });
        }

        return response()->json([

            'data' => $registration,

        ]);
    }

    public function publicChildren()
    {
        $children = Child::with([

            'birthplace',

            'hometown',

            'school',

            'schoolClass',

            'schoolEducation',

        ])->get();

        return response()->json([

            'data' => $children,

        ]);
    }

    public function publicGuardians()
    {
        $guardians =
            Guardian::get();

        return response()->json([

            'data' => $guardians,

        ]);
    }

    public function editMasterData($id)
    {
        $registration = Registration::with([

            'child',

            'paymentStatus',

            'programs.category',

            'payer',

            'clinic',

        ])->findOrFail($id);

        $programs = Program::all();

        $payers = Payer::all();

        $clinics = Clinic::all();

        $programCategories = ProgramCategory::all();

        return response()->json([

            'registration' => [

                ...$registration->toArray(),

                'program_category' => optional(
                    $registration->programs->first()
                )->category,

            ],

            'programs' => $programs,

            'payers' => $payers,

            'clinics' => $clinics,

            'program_categories' => $programCategories,

        ]);
    }
}
