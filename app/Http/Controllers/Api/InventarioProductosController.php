<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventarioProductosController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        try {
            $inventario = $this->getInventarioProductos();

            return $this->successResponse($inventario, 'Inventario de productos consultado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el inventario de productos.', 500);
        }
    }

    /**
     * Consulta el inventario de productos en la base externa.
     *
     * @return array<int, object>
     */
    protected function getInventarioProductos(): array
    {
        return DB::connection('mysql_external')->select(<<<'SQL'
            SELECT
                i.id_inventario,
                p.id_producto,
                p.descripcion AS producto,
                a.descripcion_area,
                a.id_area
            FROM inventario i
            INNER JOIN productos p ON i.id_producto = p.id_producto
            INNER JOIN area a ON i.id_area = a.id_area
        SQL);
    }
}
