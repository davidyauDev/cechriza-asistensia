<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use App\Services\TechnicianNightlyMissingMarksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguimientoTecnicoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_notificaciones_dia_anterior_devuelve_lista_seleccionada(): void
    {
        $user = User::factory()->create();

        $mock = $this->createMock(TechnicianNightlyMissingMarksService::class);
        $mock->expects($this->once())
            ->method('getPreviousDayNotifications')
            ->with(null)
            ->willReturn([
                'success' => true,
                'fecha_referencia' => '2026-04-26',
                'total_notificaciones' => 1,
                'notifications' => [
                    [
                        'id' => 10,
                        'employee_id' => 10,
                        'dni' => '12345678',
                        'nombre' => 'Juan',
                        'apellido' => 'Perez',
                        'nombre_completo' => 'Juan Perez',
                        'fecha_referencia' => '2026-04-26',
                        'title' => 'Técnico sin marcación',
                        'message' => 'Juan Perez tenía ruta y no marcó el 2026-04-26.',
                        'selected' => true,
                        'type' => 'technician_missing_mark',
                        'rutas_count' => 2,
                        'rutas' => [],
                        'daily_record' => null,
                        'source' => 'seguimiento_tecnico',
                    ],
                ],
                'selected_users' => [],
                'raw' => [],
            ]);

        $this->app->instance(TechnicianNightlyMissingMarksService::class, $mock);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/seguimiento-tecnico/notificaciones-dia-anterior')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_notificaciones', 1)
            ->assertJsonPath('notifications.0.selected', true);
    }
}
