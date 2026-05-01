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
