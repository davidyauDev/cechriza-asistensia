<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BioTimeController;
use App\Http\Controllers\Api\BirthdayGreetingsHistoryController;
use App\Http\Controllers\Api\EmployeeConceptController;
use App\Http\Controllers\Api\EmployeeMobilityController;
use App\Http\Controllers\Api\EmployeeMobilityMonthlyCommentController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\IncidenciaController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PersonnelEmployeeController;
use App\Http\Controllers\Api\ReabastecimientoController;
use App\Http\Controllers\Api\ReporteAsistenciaController;
use App\Http\Controllers\Api\SeguimientoTecnicoController;
use App\Http\Controllers\Api\TardanzaController;
use App\Http\Controllers\Api\TechnicianController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    // Ruta para seguimiento técnico
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
    Route::post('/reporte-asistencia/detalle-general', [ReporteAsistenciaController::class, 'detalleAsistGeneral']);
    Route::post('/reporte-asistencia/technicians', [ReporteAsistenciaController::class, 'technicians']);
    Route::post('/reporte-asistencia/today', [ReporteAsistenciaController::class, 'today']);

    Route::post('/reporte-asistencia/resumen', [ReporteAsistenciaController::class, 'resumenAsistencia']);

    Route::get('/biotime/departamentos', [BioTimeController::class, 'departamentos']);
    Route::get('/biotime/empresas', [BioTimeController::class, 'empresas']);
    Route::post('/biotime/empleados-por-departamento', [BioTimeController::class, 'empleadosPorDepartamento']);
    Route::prefix('biotime/personnel-employees')->group(function () {
        Route::get('/', [PersonnelEmployeeController::class, 'index']);
        Route::get('/birthdays-by-month', [PersonnelEmployeeController::class, 'birthdaysByMonth']);
        Route::get('/{id}', [PersonnelEmployeeController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [PersonnelEmployeeController::class, 'update'])->whereNumber('id');
        Route::patch('/{id}', [PersonnelEmployeeController::class, 'update'])->whereNumber('id');
    });

    Route::post('/employee-concepts', [EmployeeConceptController::class, 'storeConcept']);
    Route::post('/mobility/monthly-report', [EmployeeConceptController::class, 'monthlyMobilityReport']);
    Route::get('/employee-mobility', [EmployeeMobilityController::class, 'index']);
    Route::post('/employee-mobility/set', [EmployeeMobilityController::class, 'set']);
    Route::post('/employee-mobility', [EmployeeMobilityController::class, 'store']);
    Route::put('/employee-mobility/{id}', [EmployeeMobilityController::class, 'update'])->whereNumber('id');
    Route::patch('/employee-mobility/{id}', [EmployeeMobilityController::class, 'update'])->whereNumber('id');

    Route::prefix('employee-mobility/monthly-comments')->group(function () {
        Route::get('/', [EmployeeMobilityMonthlyCommentController::class, 'show']);
        Route::post('/', [EmployeeMobilityMonthlyCommentController::class, 'store']);
        Route::put('/', [EmployeeMobilityMonthlyCommentController::class, 'update']);
        Route::patch('/', [EmployeeMobilityMonthlyCommentController::class, 'update']);
    });

    Route::get('/birthday-greetings-history', [BirthdayGreetingsHistoryController::class, 'index']);
    Route::post('/birthday-greetings-history/retry-failed', [BirthdayGreetingsHistoryController::class, 'retryFailedGreetings']);

    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/reabastecimiento/solicitudes', [ReabastecimientoController::class, 'index'])->name('reabastecimiento.index');
    Route::get('/reabastecimiento/solicitudes/{id}', [ReabastecimientoController::class, 'show'])->whereNumber('id')->name('reabastecimiento.show');
    Route::post('/reabastecimiento/solicitudes', [ReabastecimientoController::class, 'store'])->name('reabastecimiento.store');
    Route::get('/reabastecimiento/solicitudes/{id}/archivos', [ReabastecimientoController::class, 'indexArchivos'])->whereNumber('id')->name('reabastecimiento.archivos.index');
    Route::post('/reabastecimiento/solicitudes/{id}/archivos', [ReabastecimientoController::class, 'storeArchivo'])->whereNumber('id')->name('reabastecimiento.archivos.store');
    Route::post('/reabastecimiento/detalles', [ReabastecimientoController::class, 'storeDetalle'])->name('reabastecimiento.detalles.store');
    Route::match(['put', 'patch'], '/reabastecimiento/detalles/{id}', [ReabastecimientoController::class, 'updateDetalle'])->whereNumber('id')->name('reabastecimiento.detalles.update');
    Route::delete('/reabastecimiento/detalles/{id}', [ReabastecimientoController::class, 'destroyDetalle'])->whereNumber('id')->name('reabastecimiento.detalles.destroy');
    Route::delete('/reabastecimiento/archivos/{id}', [ReabastecimientoController::class, 'destroyArchivo'])->whereNumber('id')->name('reabastecimiento.archivos.destroy');

    Route::post('/incidencias', [IncidenciaController::class, 'index']);
    Route::post('/incidencias/store', [IncidenciaController::class, 'store']);
    Route::put('/incidencias/{id}', [IncidenciaController::class, 'update']);
    Route::delete('/incidencias/{id}', [IncidenciaController::class, 'destroy']);

    Route::post('/tardanzas/enviar-correo', [TardanzaController::class, 'enviarCorreoTardanza']);
    Route::get('/seguimiento-tecnico', [SeguimientoTecnicoController::class, 'index']);
    Route::get('/seguimiento-tecnico/notificaciones-dia-anterior', [SeguimientoTecnicoController::class, 'notificacionesDiaAnterior']);

});
