<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\ReabastecimientoController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReabastecimientoListingControllerTest extends TestCase
{
    public function test_index_returns_tabs_and_pagination_payload(): void
    {
        $controller = new class extends ReabastecimientoController
        {
            protected function buildIndexPayload(array $filters): array
            {
                return [
                    'data' => [
                        [
                            'id_solicitud_reb' => 10,
                            'codigo' => 'CECH_REA_000010',
                            'staff' => [
                                'staff_id' => 163,
                                'dept_id' => 11,
                                'role_id' => 1,
                                'username' => 'raul.castro',
                                'firstname' => 'Hermes Raul',
                                'lastname' => 'Castro Campos',
                                'full_name' => 'Hermes Raul Castro Campos',
                            ],
                            'estado_inventario' => [
                                'id_estado' => 7,
                                'descripcion' => 'Pendiente de Aprobacion',
                            ],
                            'solicitante' => 'Emma Soledad Julian Iturbe',
                            'area' => 'RR.HH.',
                            'estado' => [
                                'key' => 'pendiente_aprobacion_compras',
                                'label' => 'Pendiente Aprobacion Compras',
                                'color' => 'green',
                                'tab' => 'pendientes',
                            ],
                            'justificacion' => 'solicitud de prueba',
                            'fecha_creacion' => '2025-11-24 11:17:51',
                            'detalles_count' => 2,
                        ],
                    ],
                    'meta' => [
                        'tabs' => [
                            'pendientes' => ['label' => 'PENDIENTES', 'count' => 10],
                            'procesando' => ['label' => 'PROCESANDO', 'count' => 12],
                            'cerrados' => ['label' => 'CERRADOS', 'count' => 8],
                        ],
                        'active_tab' => $filters['tab'] ?? 'pendientes',
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => 10,
                            'total' => 30,
                            'last_page' => 3,
                        ],
                    ],
                ];
            }
        };

        $request = Request::create('/api/reabastecimiento/solicitudes', 'GET', [
            'tab' => 'pendientes',
            'search' => 'prueba',
            'page' => 1,
            'per_page' => 10,
        ]);

        $response = $controller->index($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(10, $payload['data']['data'][0]['id_solicitud_reb']);
        $this->assertSame('Hermes Raul Castro Campos', $payload['data']['data'][0]['staff']['full_name']);
        $this->assertSame('Pendiente de Aprobacion', $payload['data']['data'][0]['estado_inventario']['descripcion']);
        $this->assertSame('PENDIENTES', $payload['data']['meta']['tabs']['pendientes']['label']);
        $this->assertSame(12, $payload['data']['meta']['tabs']['procesando']['count']);
    }

    public function test_show_returns_request_detail_payload(): void
    {
        $controller = new class extends ReabastecimientoController
        {
            protected function buildShowPayload(int $id): ?array
            {
                return [
                    'solicitud' => [
                            'id_solicitud_reb' => $id,
                            'codigo' => 'CECH_REA_000010',
                            'staff' => [
                                'staff_id' => 163,
                                'dept_id' => 11,
                                'role_id' => 1,
                                'username' => 'raul.castro',
                                'firstname' => 'Hermes Raul',
                                'lastname' => 'Castro Campos',
                                'full_name' => 'Hermes Raul Castro Campos',
                            ],
                            'estado_inventario' => [
                                'id_estado' => 7,
                                'descripcion' => 'Pendiente de Aprobacion',
                            ],
                            'solicitante' => 'Emma Soledad Julian Iturbe',
                            'area' => 'RR.HH.',
                            'estado' => [
                            'key' => 'pendiente_aprobacion_compras',
                            'label' => 'Pendiente Aprobacion Compras',
                            'color' => 'green',
                            'tab' => 'pendientes',
                        ],
                        'fecha_creacion' => '2025-11-24 11:17:51',
                        'justificacion' => 'solicitud de prueba',
                        'detalles_count' => 2,
                    ],
                    'detalles' => [
                        [
                            'id_detalle_reb' => 1,
                            'id_solicitud_reb' => $id,
                            'id_producto' => 125,
                            'codigo' => 'PRD-001',
                            'descripcion' => 'Laptop',
                            'categoria' => 'Equipos',
                            'tipo' => 'Activo',
                            'stock' => 3,
                            'cantidad_solicitada' => 1,
                        ],
                    ],
                ];
            }
        };

        $response = $controller->show(10);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(10, $payload['data']['solicitud']['id_solicitud_reb']);
        $this->assertSame('Hermes Raul Castro Campos', $payload['data']['solicitud']['staff']['full_name']);
        $this->assertSame('Pendiente de Aprobacion', $payload['data']['solicitud']['estado_inventario']['descripcion']);
        $this->assertSame('Laptop', $payload['data']['detalles'][0]['descripcion']);
    }
}
