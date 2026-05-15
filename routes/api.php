<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API jalan bro'
    ]);
});

Route::apiResource('children', API\ChildController::class);
Route::apiResource('guardians', API\GuardianController::class);
Route::apiResource('staff', API\StaffController::class);
Route::apiResource('schools', API\SchoolController::class);
Route::apiResource('school-classes', API\SchoolClassController::class);
Route::apiResource('school-educations', API\SchoolEducationController::class);
Route::apiResource('clinics', API\ClinicController::class);
Route::apiResource('payers', API\PayerController::class);
Route::apiResource('programs', API\ProgramController::class);
Route::apiResource('guardian-roles', API\GuardianRoleController::class);
Route::apiResource('cities', API\CityController::class);
Route::apiResource('registrations', API\RegistrationController::class);
Route::apiResource('therapy-sessions', API\TherapySessionController::class);
Route::apiResource('rooms', API\RoomController::class);
Route::apiResource('activities', API\ActivityController::class);

Route::get('/registrations/{id}', [API\RegistrationController::class, 'show']);

Route::post('/registrations/{id}/upload-receipt', [API\RegistrationController::class, 'uploadReceipt']);
Route::put('/registrations/{id}/mark-paid', [API\RegistrationController::class, 'markPaid']);

Route::delete(
    '/activity-photos/{activityPhoto}',
    [API\ActivityPhotoController::class, 'destroy']
);
Route::delete(
    '/activities/{activity}/video',
    [API\ActivityController::class, 'deleteVideo']
);
