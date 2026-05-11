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


    private const BOTAS_IDS = array(130, 140, 195, 196, 197, 198, 199);

    public function index(): JsonResponse
    {
        $noBotas = request()->query('noBotas', false);

        if ($noBotas) {
            $noBotas = filter_var($noBotas, FILTER_VALIDATE_BOOLEAN);
        }

        try {
            $inventario = $this->getInventario($noBotas);

            return $this->successResponse($inventario, 'Inventario consultado correctamente');
        } catch (Throwable $e) {
            ds($e->getMessage());

            return $this->errorResponse('No se pudo consultar el inventario.', 500);
        }
    }

    /**
     * Consulta el inventario en la base externa.
     *
     * @return array<int, object>
     */
    protected function getInventario(bool $noBotas = false): array
    {

        $noBotasCondition = $noBotas ? ' AND p.id_producto NOT IN (' . implode(',', self::BOTAS_IDS) . ')' : '';


        return DB::connection('mysql_external')->select(<<<SQL
            

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
                END AS estado
              
                 FROM productos p
            INNER JOIN inventario i
                ON p.id_producto = i.id_producto
            LEFT JOIN tipos_stock ts
                ON ts.id_tipo_stock = p.id_tipo_stock
            LEFT JOIN categorias_inventario c
                ON c.id_categoria = p.id_categoria
            where p.tipo_responsable = 'SSOMA'
            {$noBotasCondition}

        SQL);
    }
}
