<?php

namespace Tests\Unit;

use App\Services\EmployeeConceptServiceInterface;
use App\Services\TechnicianNightlyMissingMarksService;
use Tests\TestCase;

class TechnicianNightlyMissingMarksServiceTest extends TestCase
{
    public function test_process_no_route_missing_concepts_skips_users_with_existing_daily_record(): void
    {
        $employeeConceptService = $this->createMock(EmployeeConceptServiceInterface::class);

        $service = $this->getMockBuilder(TechnicianNightlyMissingMarksService::class)
            ->setConstructorArgs([$employeeConceptService])
            ->onlyMethods(['getUsersWithRouteWithoutMark', 'registerMissingConceptForDay'])
            ->getMock();

        $service->expects($this->once())
            ->method('getUsersWithRouteWithoutMark')
            ->with('2026-04-05', null)
            ->willReturn([
                'success' => true,
                'fecha' => '2026-04-05',
                'dni' => null,
                'total_usuarios' => 3,
                'total_con_ruta' => 1,
                'total_sin_ruta' => 2,
                'total_con_ruta_sin_marcacion' => 0,
                'usuarios_con_ruta' => [
                    [
                        'id' => 3,
                        'dni' => '33333333',
                        'nombre' => 'Ruta',
                        'apellido' => 'Usuario',
                        'nombre_completo' => 'Ruta Usuario',
                        'department_id' => 9,
                        'departamento' => 'Tecnicos',
                        'position_id' => 1,
                        'posicion' => 'Tecnico',
                        'email' => null,
                        'mobile' => null,
                        'status' => 0,
                        'marcaciones' => [
                            (object) ['emp_code' => '33333333'],
                        ],
                        'daily_record' => null,
                        'rutas' => [
                            (object) ['dni' => '33333333'],
                        ],
                    ],
                ],
                'usuarios_sin_ruta' => [
                    [
                        'id' => 1,
                        'dni' => '11111111',
                        'nombre' => 'Sin',
                        'apellido' => 'Ruta',
                        'nombre_completo' => 'Sin Ruta',
                        'department_id' => 9,
                        'departamento' => 'Tecnicos',
                        'position_id' => 1,
                        'posicion' => 'Tecnico',
                        'email' => null,
                        'mobile' => null,
                        'status' => 0,
                        'marcaciones' => [
                            'message' => 'No marcó',
                        ],
                        'daily_record' => null,
                        'rutas' => [],
                    ],
                    [
                        'id' => 2,
                        'dni' => '22222222',
                        'nombre' => 'Ya',
                        'apellido' => 'ConConcepto',
                        'nombre_completo' => 'Ya ConConcepto',
                        'department_id' => 9,
                        'departamento' => 'Tecnicos',
                        'position_id' => 1,
                        'posicion' => 'Tecnico',
                        'email' => null,
                        'mobile' => null,
                        'status' => 0,
                        'marcaciones' => [
                            'message' => 'No marcó',
                        ],
                        'daily_record' => [
                            'id' => 99,
                            'concept_id' => 5,
                        ],
                        'rutas' => [],
                    ],
                ],
                'usuarios_con_ruta_sin_marcacion' => [],
            ]);

        $service->expects($this->once())
            ->method('registerMissingConceptForDay')
            ->with(
                $this->callback(function (array $user): bool {
                    return $user['id'] === 1 && $user['dni'] === '11111111';
                }),
                '2026-04-05',
                1,
                'Registro automático generado por seguimiento técnico nocturno sin ruta.'
            )
            ->willReturn([
                'employee_concept_id' => 101,
                'concept_code' => 'NM',
                'concept_name' => 'No Marcacion',
                'was_updated' => false,
                'total_days_registered' => 1,
            ]);

        $result = $service->processNoRouteMissingConcepts('2026-04-05');

        $this->assertSame('2026-04-05', $result['preview']['fecha']);
        $this->assertSame(1, $result['processed_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame(1, $result['concept_id']);
        $this->assertCount(1, $result['processed_users']);
        $this->assertSame(101, $result['processed_users'][0]['employee_concept_id']);
    }
}
