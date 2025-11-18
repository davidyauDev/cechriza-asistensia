<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\TechnicianController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/register', [UserController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'store']);
        Route::get('/all', [UserController::class, 'listAll'])->middleware('role:ADMIN');
        Route::get('/check-in-out', [UserController::class, 'listByCheckInAndOut']);
        Route::get('/not-checked-out', [UserController::class, 'listNotCheckedOut']);
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::patch('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::post('/{id}/restore', [UserController::class, 'restore']);   
    });

    // Route::post('/users', [UserController::class, 'store']);
    // Route::get('/users/all', [UserController::class, 'listAll']);
    // Route::get('/users', [UserController::class, 'index']);
    // Route::get('/users/{user}', [UserController::class, 'show']);
    // Route::put('/users/{user}', [UserController::class, 'update']);
    // Route::patch('/users/{user}', [UserController::class, 'update']);
    // Route::delete('/users/{user}', [UserController::class, 'destroy']);
    // Route::post('/users/{id}/restore', [UserController::class, 'restore']);

    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::get('/{attendance}', [AttendanceController::class, 'show']);
        Route::put('/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy']);

    });

    // Rutas específicas para asistencias por usuario
    Route::get('/users/{user}/attendances', [AttendanceController::class, 'forUser']);
    Route::get('/users/{user}/attendance-stats', [AttendanceController::class, 'userStats']);



    Route::prefix('eventos')->group(function () {
        Route::get('/', [EventoController::class, 'index']);
        Route::post('/', [EventoController::class, 'store']);
        Route::get('/hoy', [EventoController::class, 'eventosHoy']); // Eventos activos de hoy
        Route::get('/fecha/{fecha}', [EventoController::class, 'porFecha']);
        Route::get('/mes/{anio}/{mes}', [EventoController::class, 'eventosDelMes']);
        Route::get('/dia/{fecha}', [EventoController::class, 'eventosDelDia']);
        Route::get('/{id}', [EventoController::class, 'show']);
        Route::put('/{id}', [EventoController::class, 'update']);
        Route::delete('/{id}', [EventoController::class, 'destroy']);
    });

    // Rutas para técnicos (Base de datos externa)
    Route::get('/technicians/rutas-dia', [TechnicianController::class, 'getRutasTecnicosDia']);
});
