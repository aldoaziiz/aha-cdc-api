<?php

use App\Http\Controllers\API;
use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

// ======================
// TEST
// ======================

Route::get('/test', function () {

    return response()->json([
        'message' => 'API jalan bro',
    ]);

});

// ======================
// PUBLIC
// ======================

// AUTH

Route::post(
    '/login',
    [AuthController::class, 'login']
);

// PUBLIC REGISTRATION

Route::post(
    '/registrations',
    [API\RegistrationController::class, 'store']
);

Route::get(
    '/public-registration/children',
    [API\RegistrationController::class,
        'publicChildren']
);

Route::get(
    '/public-registration/guardians',
    [API\RegistrationController::class,
        'publicGuardians']
);

// PUBLIC LOOKUP

Route::get(
    '/guardian-roles',
    [API\GuardianRoleController::class,
        'index']
);

Route::get(
    '/programs',
    [API\ProgramController::class,
        'index']
);

Route::get(
    '/payers',
    [API\PayerController::class,
        'index']
);

Route::get(
    '/cities',
    [API\CityController::class,
        'index']
);

Route::get(
    '/schools',
    [API\SchoolController::class,
        'index']
);

Route::get(
    '/school-classes',
    [API\SchoolClassController::class,
        'index']
);

Route::get(
    '/school-educations',
    [API\SchoolEducationController::class,
        'index']
);

Route::get(
    '/rooms',
    [API\RoomController::class,
        'index']
);

// PUBLIC UPLOAD

Route::post(

    '/invoice-upload/{token}',

    [API\RegistrationController::class,
        'uploadReceiptByToken']

);

Route::get(

    '/invoice-upload/{token}',

    [API\RegistrationController::class,
        'invoiceByToken']

);

// ======================
// PROTECTED
// ======================

Route::middleware('auth:sanctum')
    ->group(function () {

        // ======================
        // AUTH
        // ======================

        Route::get(
            '/me',
            [AuthController::class, 'me']
        );

        Route::post(
            '/logout',
            [AuthController::class, 'logout']
        );

        Route::post(

            '/registrations/{registration}/generate-invoice-link',

            [API\RegistrationController::class,
                'generateInvoiceLink']

        );

        // ======================
        // MASTER DATA
        // ======================

        Route::apiResource(
            'children',
            API\ChildController::class
        );

        Route::apiResource(
            'guardians',
            API\GuardianController::class
        );

        Route::apiResource(
            'staff',
            API\StaffController::class
        );

        Route::apiResource(
            'staff-roles',
            API\StaffRoleController::class
        );

        Route::apiResource(
            'schools',
            API\SchoolController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'school-classes',
            API\SchoolClassController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'school-educations',
            API\SchoolEducationController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'clinics',
            API\ClinicController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'payers',
            API\PayerController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'programs',
            API\ProgramController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'guardian-roles',
            API\GuardianRoleController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'cities',
            API\CityController::class
        )->except([
            'index',
            'show',
        ]);

        Route::apiResource(
            'rooms',
            API\RoomController::class
        )->except([
            'index',
            'show',
        ]);

        // ======================
        // TRANSACTIONS
        // ======================

        Route::apiResource(
            'registrations',
            API\RegistrationController::class
        )->except([
            'store',
        ]);

        Route::apiResource(
            'therapy-sessions',
            API\TherapySessionController::class
        );

        Route::apiResource(
            'activities',
            API\ActivityController::class
        );

        // ======================
        // REGISTRATION
        // ======================

        Route::get(
            '/registrations/{id}',
            [API\RegistrationController::class, 'show']
        );

        Route::post(
            '/registrations/{id}/upload-receipt',
            [API\RegistrationController::class, 'uploadReceipt']
        );

        Route::put(
            '/registrations/{id}/mark-paid',
            [API\RegistrationController::class, 'markPaid']
        );

        // ======================
        // ACTIVITY
        // ======================

        Route::delete(
            '/activity-photos/{activityPhoto}',
            [API\ActivityPhotoController::class, 'destroy']
        );

        Route::delete(
            '/activities/{activity}/video',
            [API\ActivityController::class, 'deleteVideo']
        );

        // ======================
        // CHILD GUARDIANS
        // ======================

        Route::post(
            '/children/{child}/guardians',
            [API\ChildGuardianController::class, 'store']
        );

        Route::delete(
            '/children/{child}/guardians/{guardian}',
            [API\ChildGuardianController::class, 'destroy']
        );

    });
