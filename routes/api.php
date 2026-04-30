<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API jalan bro'
    ]);
});

Route::apiResource('children', API\ChildController::class);
// Route::post('/children/bulk', [ChildController::class, 'bulkStore']);
Route::apiResource('guardians', API\GuardianController::class);
Route::apiResource('staff', API\StaffController::class);
Route::apiResource('schools', API\SchoolController::class);
