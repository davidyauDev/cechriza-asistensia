<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\ReabastecimientoController;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ReabastecimientoDetalleControllerTest extends TestCase
{
    public function test_store_detalle_creates_row_for_existing_solicitud(): void
    {
        $lookup = Mockery::mock();
        $insertBuilder = Mockery::mock();
        $connection = Mockery::mock();

        $lookup->shouldReceive('select')->once()->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('sr.id_solicitud_reb', 3)->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('sr.id_area_solicitante', 11)->andReturnSelf();
        $lookup->shouldReceive('first')->once()->andReturn((object) [
            'id_solicitud_reb' => 3,
            'id_area_solicitante' => 11,
        ]);

        $insertBuilder->shouldReceive('insertGetId')->once()->with([
            'id_solicitud_reb' => 3,
            'id_producto' => 117,
            'cantidad_solicitada' => 2,
        ])->andReturn(15);

        $connection->shouldReceive('table')
            ->with('solicitudes_reabastecimiento as sr')
            ->once()
            ->andReturn($lookup);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_detalles')
            ->once()
            ->andReturn($insertBuilder);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $controller = new class($connection) extends ReabastecimientoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/reabastecimiento/detalles', 'POST', [
            'id_solicitud_reb' => 3,
            'id_producto' => 117,
            'cantidad_solicitada' => 2,
        ]);

        $response = $controller->storeDetalle($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(15, $payload['data']['id_detalle_reb']);
        $this->assertSame(3, $payload['data']['id_solicitud_reb']);
        $this->assertSame(117, $payload['data']['id_producto']);
        $this->assertSame(2, $payload['data']['cantidad_solicitada']);
    }

    public function test_update_detalle_updates_quantity_and_product(): void
    {
        $lookup = Mockery::mock();
        $updateBuilder = Mockery::mock();
        $connection = Mockery::mock();

        $lookup->shouldReceive('join')->once()->andReturnSelf();
        $lookup->shouldReceive('select')->once()->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('rd.id_detalle_reb', 12)->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('sr.id_area_solicitante', 11)->andReturnSelf();
        $lookup->shouldReceive('first')->once()->andReturn((object) [
            'id_detalle_reb' => 12,
            'id_solicitud_reb' => 3,
            'id_producto' => 115,
            'cantidad_solicitada' => 3,
        ]);

        $updateBuilder->shouldReceive('where')->once()->with('id_detalle_reb', 12)->andReturnSelf();
        $updateBuilder->shouldReceive('update')->once()->with([
            'id_producto' => 117,
            'cantidad_solicitada' => 2,
        ])->andReturn(1);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_detalles as rd')
            ->once()
            ->andReturn($lookup);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_detalles')
            ->once()
            ->andReturn($updateBuilder);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $controller = new class($connection) extends ReabastecimientoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $request = Request::create('/api/reabastecimiento/detalles/12', 'PUT', [
            'id_producto' => 117,
            'cantidad_solicitada' => 2,
        ]);

        $response = $controller->updateDetalle($request, 12);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(12, $payload['data']['id_detalle_reb']);
        $this->assertSame(117, $payload['data']['id_producto']);
        $this->assertSame(2, $payload['data']['cantidad_solicitada']);
    }

    public function test_destroy_detalle_deletes_row(): void
    {
        $lookup = Mockery::mock();
        $deleteBuilder = Mockery::mock();
        $connection = Mockery::mock();

        $lookup->shouldReceive('join')->once()->andReturnSelf();
        $lookup->shouldReceive('select')->once()->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('rd.id_detalle_reb', 12)->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('sr.id_area_solicitante', 11)->andReturnSelf();
        $lookup->shouldReceive('first')->once()->andReturn((object) [
            'id_detalle_reb' => 12,
            'id_solicitud_reb' => 3,
            'id_producto' => 115,
            'cantidad_solicitada' => 3,
        ]);

        $deleteBuilder->shouldReceive('where')->once()->with('id_detalle_reb', 12)->andReturnSelf();
        $deleteBuilder->shouldReceive('delete')->once()->andReturn(1);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_detalles as rd')
            ->once()
            ->andReturn($lookup);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_detalles')
            ->once()
            ->andReturn($deleteBuilder);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $controller = new class($connection) extends ReabastecimientoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };

        $response = $controller->destroyDetalle(12);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(12, $payload['data']['id_detalle_reb']);
        $this->assertSame(3, $payload['data']['id_solicitud_reb']);
    }
}
