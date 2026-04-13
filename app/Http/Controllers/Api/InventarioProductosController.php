<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventarioProductosController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id_area' => ['nullable', 'integer', 'min:1'],
            ]);

            $idArea = isset($validated['id_area']) ? (int) $validated['id_area'] : null;

            $inventario = $this->getInventarioProductos($idArea);

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
    protected function getInventarioProductos(?int $idArea = null): array
    {
        $sql = <<<'SQL'
            SELECT
                i.id_inventario,
                p.id_producto,
                p.descripcion AS producto,
                a.descripcion_area,
                a.id_area,
                p.requiere_foto_producto_anterior
            FROM inventario i
            INNER JOIN productos p ON i.id_producto = p.id_producto
            INNER JOIN area a ON i.id_area = a.id_area
        SQL;

        $bindings = [];

        if ($idArea !== null) {
            $sql .= "\n            WHERE i.id_area = ?";
            $bindings[] = $idArea;
        }

        return DB::connection('mysql_external')->select($sql, $bindings);
    }
}
