<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\DetalleSolicitudController;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class DetalleSolicitudControllerTest extends TestCase
{
    public function test_aprobar_descuenta_stock_y_actualiza_el_detalle(): void
    {
        $connection = Mockery::mock();
        $detalle = (object) [
            'id_detalle_solicitud' => 10,
            'id_solicitud' => 154,
            'id_inventario' => 991,
            'cantidad_solicitada' => 3,
        ];

        $connection->shouldReceive('select')
            ->andReturn([$detalle], [(object) ['exists' => 1]]);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $connection->shouldReceive('update')->times(3)->andReturn(1, 1, 1);

        $controller = new class($connection) extends DetalleSolicitudController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/solicitudes/detalles/10/aprobar', 'POST', [
            'cantidad_aprobada' => 3,
            'motivo' => 'aprobado parcial',
            'id_usuario_atendio' => 163,
        ]);

        $response = $controller->aprobar($request, 10);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(10, $payload['data']['id_detalle_solicitud']);
        $this->assertSame(154, $payload['data']['id_solicitud']);
        $this->assertSame(3, $payload['data']['cantidad_aprobada']);
        $this->assertSame(2, $payload['data']['id_estado_detalle']);
        $this->assertTrue($payload['data']['tiene_detalles_pendientes']);
    }

    public function test_rechazar_actualiza_el_detalle_sin_descontar_stock(): void
    {
        $connection = Mockery::mock();
        $detalle = (object) [
            'id_detalle_solicitud' => 11,
            'id_solicitud' => 154,
            'id_inventario' => 991,
            'cantidad_solicitada' => 2,
        ];

        $connection->shouldReceive('select')
            ->andReturn([$detalle], []);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $connection->shouldReceive('update')->times(2)->andReturn(1, 1);

        $controller = new class($connection) extends DetalleSolicitudController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/solicitudes/detalles/11/rechazar', 'POST', [
            'motivo' => 'rechazado por calidad',
            'id_usuario_atendio' => 163,
        ]);

        $response = $controller->rechazar($request, 11);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(11, $payload['data']['id_detalle_solicitud']);
        $this->assertSame(154, $payload['data']['id_solicitud']);
        $this->assertSame(0, $payload['data']['cantidad_aprobada']);
        $this->assertSame(3, $payload['data']['id_estado_detalle']);
        $this->assertFalse($payload['data']['tiene_detalles_pendientes']);
    }
}
