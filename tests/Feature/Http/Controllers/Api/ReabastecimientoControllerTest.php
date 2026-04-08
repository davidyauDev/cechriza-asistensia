<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\ReabastecimientoController;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ReabastecimientoControllerTest extends TestCase
{
    public function test_store_registers_header_and_details_in_external_database(): void
    {
        $connection = Mockery::mock();
        $solicitudesTable = Mockery::mock();
        $detallesTable = Mockery::mock();

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $connection->shouldReceive('table')
            ->with('solicitudes_reabastecimiento')
            ->once()
            ->andReturn($solicitudesTable);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_detalles')
            ->once()
            ->andReturn($detallesTable);

        $solicitudesTable->shouldReceive('insertGetId')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['id_usuario_solicitante'] === 163
                    && $payload['id_area_solicitante'] === 11
                    && $payload['id_estado_general'] === 1
                    && $payload['justificacion'] === 'pedido de prueba';
            }))
            ->andReturn(9);

        $detallesTable->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function (array $rows) {
                return count($rows) === 2
                    && $rows[0]['id_solicitud_reb'] === 9
                    && $rows[0]['id_producto'] === 125
                    && $rows[0]['cantidad_solicitada'] === 1
                    && $rows[1]['id_producto'] === 127
                    && $rows[1]['cantidad_solicitada'] === 2;
            }))
            ->andReturnTrue();

        $controller = new class($connection) extends ReabastecimientoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/reabastecimiento/solicitudes', 'POST', [
            'id_usuario_solicitante' => 163,
            'id_area_solicitante' => 11,
            'id_estado_general' => 1,
            'justificacion' => 'pedido de prueba',
            'detalles' => [
                ['id_producto' => 125, 'cantidad_solicitada' => 1],
                ['id_producto' => 127, 'cantidad_solicitada' => 2],
            ],
        ]);

        $response = $controller->store($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(9, $payload['data']['id_solicitud_reb']);
        $this->assertSame(2, $payload['data']['detalles_registrados']);
    }
}
