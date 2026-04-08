<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\InventarioController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InventarioControllerTest extends TestCase
{
    public function test_inventario_route_is_registered_and_controller_returns_data(): void
    {
        $this->assertTrue(Route::has('inventario.index'));

        $controller = new class extends InventarioController
        {
            protected function getInventario(): array
            {
                return [
                    (object) [
                        'codigo' => 'PRD-001',
                        'descripcion' => 'Laptop',
                        'categoria' => 'Equipos',
                        'tipo' => 'Activo',
                        'stock' => 3,
                        'estado' => 'BAJO',
                        'area' => 'Sistemas',
                    ],
                ];
            }
        };

        $response = $controller->index();

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('PRD-001', $payload['data'][0]['codigo']);
        $this->assertSame('BAJO', $payload['data'][0]['estado']);
    }
}
