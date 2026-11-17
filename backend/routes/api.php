<?php

use App\Http\Controllers\Api\Admin\LeaveReviewController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');

    Route::apiResource('leaves', LeaveController::class)->parameters(['leaves' => 'leave']);
    Route::get('/leaves/{leave}/attachment', [LeaveController::class, 'attachment']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::patch('/leaves/{leave}', [LeaveReviewController::class, 'update']);
    });
});
