<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\SolicitudGastoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class SolicitudGastoControllerTest extends TestCase
{
    public function test_index_route_is_registered_and_returns_solicitudes_gasto_payload(): void
    {
        $this->assertTrue(Route::has('solicitudes-gasto.comprobantes.index'));

        $connection = Mockery::mock();

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'FROM solicitudes_gasto sg')
                    && str_contains($sql, 'LEFT JOIN (')
                    && str_contains($sql, 'FROM comprobantes_gasto cg1')
                    && str_contains($sql, 'ORDER BY sg.id DESC')
                    && $bindings === [];
            })
            ->andReturn([
                (object) [
                    'id' => 12,
                    'staff_id' => 163,
                    'id_area' => 11,
                    'motivo' => 'Viaticos',
                    'monto_estimado' => '200.00',
                    'monto_real' => '150.50',
                    'estado' => 'aprobada',
                    'fecha_solicitud' => '2026-04-09 10:15:00',
                    'fecha_aprobacion' => '2026-04-10 10:15:00',
                    'fecha_reembolso' => null,
                    'username' => 'raul.castro',
                    'firstname' => 'Raul',
                    'lastname' => 'Castro',
                    'area' => 'RR.HH.',
                    'comprobante_id' => 7,
                    'comprobante_tipo' => 'BOLETA',
                    'comprobante_numero' => 'F001-123',
                    'comprobante_monto' => '150.50',
                    'comprobante_archivo_url' => 'https://example.test/comprobantes/7.pdf',
                ],
            ]);

        $controller = new class($connection) extends SolicitudGastoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/solicitudes-gasto/comprobantes', 'GET');

        $response = $controller->index($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(12, $payload['data'][0]['id']);
        $this->assertSame(12, $payload['data'][0]['solicitud_gasto_id']);
        $this->assertSame(163, $payload['data'][0]['solicitud_gasto']['staff_id']);
        $this->assertSame('Raul Castro', $payload['data'][0]['solicitud_gasto']['solicitante']);
        $this->assertSame(200, $payload['data'][0]['monto_estimado']);
        $this->assertSame(150.5, $payload['data'][0]['monto_real']);
        $this->assertSame('BOLETA', $payload['data'][0]['comprobante']['tipo']);
        $this->assertSame('F001-123', $payload['data'][0]['comprobante']['numero']);
    }

    public function test_historial_route_is_registered_and_returns_history_payload(): void
    {
        $this->assertTrue(Route::has('solicitudes-gasto.historial'));

        $connection = Mockery::mock();

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'FROM solicitudes_gasto sg')
                    && str_contains($sql, 'LEFT JOIN ost_staff os ON os.staff_id = sg.staff_id')
                    && str_contains($sql, 'WHERE sg.id = ?')
                    && $bindings === [12];
            })
            ->andReturn([
                (object) [
                    'id' => 12,
                    'staff_id' => 163,
                    'id_area' => 11,
                    'motivo' => 'Viaticos',
                    'monto_estimado' => '200.00',
                    'monto_real' => '150.50',
                    'estado' => 'aprobada',
                    'fecha_solicitud' => '2026-04-09 10:15:00',
                    'fecha_aprobacion' => '2026-04-10 10:15:00',
                    'fecha_reembolso' => null,
                    'username' => 'raul.castro',
                    'firstname' => 'Raul',
                    'lastname' => 'Castro',
                    'area' => 'RR.HH.',
                ],
            ]);

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'FROM seguimientos_solicitud_gasto ssg')
                    && str_contains($sql, 'WHERE ssg.solicitud_gasto_id = ?')
                    && str_contains($sql, 'ORDER BY ssg.fecha DESC, ssg.id DESC')
                    && $bindings === [12];
            })
            ->andReturn([
                (object) [
                    'id' => 3,
                    'solicitud_gasto_id' => 12,
                    'estado_anterior' => 'pendiente',
                    'estado_nuevo' => 'aprobada',
                    'comentario' => 'Se aprobo la solicitud.',
                    'staff_id' => 163,
                    'fecha' => '2026-04-10 11:30:00',
                    'username' => 'raul.castro',
                    'firstname' => 'Raul',
                    'lastname' => 'Castro',
                    'area' => 'RR.HH.',
                ],
            ]);

        $controller = new class($connection) extends SolicitudGastoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/solicitudes-gasto/12/historial', 'GET');

        $response = $controller->historial($request, 12);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(12, $payload['data']['solicitud_gasto']['id']);
        $this->assertSame('Raul Castro', $payload['data']['solicitud_gasto']['solicitante']);
        $this->assertCount(1, $payload['data']['data']);
        $this->assertSame('pendiente', $payload['data']['data'][0]['estado_anterior']);
        $this->assertSame('aprobada', $payload['data']['data'][0]['estado_nuevo']);
        $this->assertSame('Se aprobo la solicitud.', $payload['data']['data'][0]['comentario']);
    }
}
