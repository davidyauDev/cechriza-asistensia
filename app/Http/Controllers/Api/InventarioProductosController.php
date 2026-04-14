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
                'tipo_responsable' => ['nullable', 'string', 'in:SSGG,SSOMA,LOGISTICA'],
            ]);

            $tipoResponsable = $validated['tipo_responsable'] ?? null;

            $inventario = $this->getInventarioProductos($tipoResponsable);

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
    protected function getInventarioProductos(?string $tipoResponsable = null): array
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
            WHERE p.eliminado = 0
        SQL;

        $bindings = [];

        if ($tipoResponsable !== null) {
            $sql .= "\n            AND p.tipo_responsable = ?";
            $bindings[] = $tipoResponsable;
        }

        return DB::connection('mysql_external')->select($sql, $bindings);
    }
}
