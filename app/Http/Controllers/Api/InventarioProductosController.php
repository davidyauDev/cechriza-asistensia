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
                'id_cargo' => ['nullable', 'integer', 'min:1'],
            ]);

            $tipoResponsable = $validated['tipo_responsable'] ?? null;
            $idCargo = isset($validated['id_cargo']) ? (int) $validated['id_cargo'] : null;

            $inventario = $this->getInventarioProductos($tipoResponsable, $idCargo);

            if ($tipoResponsable === 'LOGISTICA') {
                return response()->json([
                    'success' => true,
                    'data' => $inventario,
                    'ubicaciones_limpieza' => $this->getUbicacionesLimpieza(),
                    'message' => 'Inventario de productos consultado correctamente',
                ]);
            }

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
    protected function getInventarioProductos(?string $tipoResponsable = null, ?int $idCargo = null): array
    {
        $sql = <<<'SQL'
            SELECT
                i.id_inventario,
                p.id_producto,
                p.descripcion AS producto,
                c.id_categoria,
                c.nombre_categoria AS categoria,
                CASE
                    WHEN p.tipo_responsable = 'LOGISTICA' THEN 7
                    WHEN p.tipo_responsable = 'SSOMA' THEN 11
                    ELSE 12
                END AS id_area,
                p.requiere_foto_producto_anterior
            FROM inventario i
            INNER JOIN productos p ON i.id_producto = p.id_producto
            LEFT JOIN categorias_inventario c
                ON c.id_categoria = p.id_categoria
            WHERE p.eliminado = 0
        SQL;

        $bindings = [];

        if ($tipoResponsable !== null) {
            $sql .= "\n            AND p.tipo_responsable = ?";
            $bindings[] = $tipoResponsable;
        }

        if ($idCargo !== null) {
            $sql .= "\n            AND EXISTS (\n                SELECT 1\n                FROM producto_cargo pc\n                WHERE pc.id_producto = p.id_producto\n                  AND pc.id_cargo = ?\n            )";
            $bindings[] = $idCargo;
        }

        return DB::connection('mysql_external')->select($sql, $bindings);
    }

    /**
     * Obtiene las ubicaciones de limpieza para logística.
     *
     * @return array<int, object>
     */
    protected function getUbicacionesLimpieza(): array
    {
        return DB::connection('mysql_external')->select(<<<'SQL'
            SELECT *
            FROM ubicaciones_limpieza
            ORDER BY id ASC
        SQL);
    }
}
