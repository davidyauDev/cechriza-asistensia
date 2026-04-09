<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\SolicitudController;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class SolicitudControllerTest extends TestCase
{
    public function test_index_returns_filtered_solicitudes_payload(): void
    {
        $connection = Mockery::mock();

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'FROM solicitudes s')
                    && str_contains($sql, 'WHERE s.id_estado_general = ?')
                    && str_contains($sql, 's.pedido_compra_estado = ?')
                    && str_contains($sql, 'a.descripcion_area = ?')
                    && str_contains($sql, 'ORDER BY s.fecha_registro DESC')
                    && $bindings === [11, 0, 'RR.HH.'];
            })
            ->andReturn([
                (object) [
                    'id_solicitud' => 154,
                    'id_usuario_solicitante' => 163,
                    'justificacion' => 'Pedido de prueba',
                    'id_estado_general' => 11,
                    'fecha_registro' => '2026-04-09 10:15:00',
                    'estado' => 'Pendiente de Atencion',
                    'firstname' => 'Alexander',
                    'lastname' => 'Flores',
                ],
            ]);

        $controller = new class($connection) extends SolicitudController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/solicitudes', 'GET');

        $response = $controller->index($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(154, $payload['data'][0]['id_solicitud']);
        $this->assertSame('Alexander Flores', $payload['data'][0]['solicitante']);
        $this->assertSame('Pendiente de Atencion', $payload['data'][0]['estado']['descripcion']);
        $this->assertSame('Pedido de prueba', $payload['data'][0]['justificacion']);
    }

    public function test_show_returns_solicitud_detail_payload(): void
    {
        $connection = Mockery::mock();

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'FROM solicitudes s')
                    && str_contains($sql, 'INNER JOIN solicitud_detalles d ON d.id_solicitud = s.id_solicitud')
                    && str_contains($sql, 'WHERE s.id_solicitud = ?')
                    && str_contains($sql, 'ORDER BY COALESCE(NULLIF(d.area_id, 0), i.id_area) ASC, p.descripcion ASC')
                    && $bindings === [154];
            })
            ->andReturn([
                (object) [
                    'id_detalle_solicitud' => 10,
                    'id_solicitud' => 154,
                    'id_inventario' => 991,
                    'area_id' => 11,
                    'area' => 'RR.HH.',
                    'id_area_inventario' => 11,
                    'stock_actual' => 4,
                    'producto' => 'Laptop',
                    'solicitado' => 3,
                    'aprobado' => 0,
                    'cantidad_atendida' => 0,
                    'id_estado_detalle' => 11,
                    'estado' => 'Pendiente de Atencion',
                    'observacion_atencion' => null,
                    'motivo' => null,
                    'id_usuario_atendio' => null,
                    'fecha_atencion' => null,
                    'id_usuario_solicitante' => 163,
                    'fecha_registro' => '2026-04-09 10:15:00',
                    'fecha_necesaria' => '2026-04-12 10:15:00',
                    'fecha_cierre' => null,
                    'prioridad' => 'Alta',
                    'tipo_entrega_preferida' => 'Interna',
                    'justificacion' => 'Pedido de prueba',
                    'firstname' => 'Alexander',
                    'lastname' => 'Flores',
                    'email' => 'alexander@example.com',
                ],
            ]);

        $controller = new class($connection) extends SolicitudController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $response = $controller->show(154);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(154, $payload['data']['solicitud']['id_solicitud']);
        $this->assertSame('Alexander Flores', $payload['data']['solicitud']['solicitante']);
        $this->assertSame('Laptop', $payload['data']['detalles'][0]['producto']);
        $this->assertSame('RR.HH.', $payload['data']['detalles'][0]['area']);
    }
}
