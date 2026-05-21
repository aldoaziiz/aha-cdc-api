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
        );

        Route::apiResource(
            'school-classes',
            API\SchoolClassController::class
        );

        Route::apiResource(
            'school-educations',
            API\SchoolEducationController::class
        );

        Route::apiResource(
            'clinics',
            API\ClinicController::class
        );

        Route::apiResource(
            'payers',
            API\PayerController::class
        );

        Route::apiResource(
            'programs',
            API\ProgramController::class
        );

        Route::apiResource(
            'guardian-roles',
            API\GuardianRoleController::class
        );

        Route::apiResource(
            'cities',
            API\CityController::class
        );

        Route::apiResource(
            'rooms',
            API\RoomController::class
        );

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
