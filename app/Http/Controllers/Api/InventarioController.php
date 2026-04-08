<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventarioController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        try {
            $inventario = $this->getInventario();

            return $this->successResponse($inventario, 'Inventario consultado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el inventario.', 500);
        }
    }

    /**
     * Consulta el inventario en la base externa.
     *
     * @return array<int, object>
     */
    protected function getInventario(): array
    {
        return DB::connection('mysql_external')->select(<<<'SQL'
            SELECT 
                p.id_producto  as id_producto,
                p.codigo_producto AS codigo,
                p.descripcion,
                c.nombre_categoria AS categoria,
                ts.descripcion AS tipo,
                i.stock_actual AS stock,
                CASE 
                    WHEN i.stock_actual = 0 THEN 'SIN STOCK'
                    WHEN i.stock_actual <= 5 THEN 'BAJO'
                    ELSE 'OK'
                END AS estado,
                a.descripcion_area AS area
            FROM inventario i
            INNER JOIN productos p 
                ON p.id_producto = i.id_producto
            LEFT JOIN tipos_stock ts 
                ON ts.id_tipo_stock = p.id_tipo_stock
            LEFT JOIN categorias_inventario c 
                ON c.id_categoria = p.id_categoria
            INNER JOIN area a 
                ON a.id_area = i.id_area
            WHERE i.id_area = 11
        SQL);
    }
}
