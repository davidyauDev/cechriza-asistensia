<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\InventarioProductosController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InventarioProductosControllerTest extends TestCase
{
    public function test_inventario_productos_route_is_registered_and_controller_returns_data(): void
    {
        $this->assertTrue(Route::has('inventario.productos.index'));

        $controller = new class extends InventarioProductosController
        {
            protected function getInventarioProductos(): array
            {
                return [
                    (object) [
                        'id_inventario' => 1,
                        'id_producto' => 10,
                        'producto' => 'Martillo',
                        'descripcion_area' => 'Bodega',
                        'id_area' => 2,
                    ],
                ];
            }
        };

        $response = $controller->index();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(1, $payload['data'][0]['id_inventario']);
        $this->assertSame('Martillo', $payload['data'][0]['producto']);
    }
}
