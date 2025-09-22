<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/register', [UserController::class, 'store']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);

    Route::post('/attendances', [AttendanceController::class, 'store']);
});
    