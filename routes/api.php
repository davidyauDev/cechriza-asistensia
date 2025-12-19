<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BioTimeController;
use App\Http\Controllers\Api\BirthdayGreetingsHistoryController;
use App\Http\Controllers\Api\EmployeeConceptController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\IncidenciaController;
use App\Http\Controllers\Api\TechnicianController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReporteAsistenciaController;


Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/register', [UserController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'store']);
        Route::get('/all', [UserController::class, 'listAll']);
        Route::get('/check-in-out', [UserController::class, 'listByCheckInAndOut']);
        Route::get('/not-checked-out', [UserController::class, 'listNotCheckedOut']);
        Route::get('/not-checked-in-out-today', [UserController::class, 'listNotCheckedInOutByCurrentDate']);
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::patch('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/toggle-active', [UserController::class, 'toggleActiveStatus']);
        Route::post('/{id}/restore', [UserController::class, 'restore']);
    });

    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::get('/{attendance}', [AttendanceController::class, 'show']);
        Route::put('/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy']);
    });

    Route::post('/users/attendances/for-user', [AttendanceController::class, 'forUser']);
    Route::get('/users/{user}/attendance-stats', [AttendanceController::class, 'userStats']);

    Route::prefix('eventos')->group(function () {
        Route::get('/', [EventoController::class, 'index']);
        Route::post('/', [EventoController::class, 'store']);
        Route::get('/hoy', [EventoController::class, 'eventosHoy']);
        Route::get('/fecha/{fecha}', [EventoController::class, 'porFecha']);
        Route::get('/mes/{anio}/{mes}', [EventoController::class, 'eventosDelMes']);
        Route::get('/dia/{fecha}', [EventoController::class, 'eventosDelDia']);
        Route::get('/{id}', [EventoController::class, 'show']);
        Route::put('/{id}', [EventoController::class, 'update']);
        Route::delete('/{id}', [EventoController::class, 'destroy']);
    });

    Route::get('/technicians/rutas-dia', [TechnicianController::class, 'getRutasTecnicosDia']);

    Route::post('/reporte-asistencia/detalle', [ReporteAsistenciaController::class, 'detalleAsist']);
    Route::post('/reporte-asistencia/marcacion', [ReporteAsistenciaController::class, 'detalleMarcacion']);

    Route::post('/reporte-asistencia/resumen', [ReporteAsistenciaController::class, 'resumenAsistencia']);

    Route::get('/biotime/departamentos', [BioTimeController::class, 'departamentos']);
    Route::get('/biotime/empresas', [BioTimeController::class, 'empresas']);
    Route::post('/biotime/empleados-por-departamento', [BioTimeController::class, 'empleadosPorDepartamento']);
    
    //storeConcept
    Route::post('/employee-concepts', [EmployeeConceptController::class, 'storeConcept']);
    Route::post('daily-records/monthly-summary', [EmployeeConceptController::class, 'getMonthlySummary']);

    Route::get('/birthday-greetings-history', [BirthdayGreetingsHistoryController::class, 'index']);
    Route::post('/birthday-greetings-history/retry-failed', [BirthdayGreetingsHistoryController::class, 'retryFailedGreetings']);

    Route::post('/incidencias', [IncidenciaController::class, 'store']);
    Route::get('/incidencias', [IncidenciaController::class, 'index']);

});
