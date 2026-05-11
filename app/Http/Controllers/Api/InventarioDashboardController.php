<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventarioDashboardController extends Controller
{
    use ApiResponseTrait;

    private const GLOBAL_AREA_IDS = [
        // 11
        ];

    public function consumo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'id_area' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $fechaHasta = $this->normalizarFechaCorte($validated['fecha_hasta'] ?? null);
            $fechaDesde = Carbon::createFromFormat('Y-m-d', $fechaHasta)
                ->subMonthsNoOverflow(3)
                ->toDateString();
            $idAreaFiltro = $this->resolverAreaFiltro($request, $validated['id_area'] ?? null);

            return $this->successResponse([
                'id_area_filtro' => $idAreaFiltro,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'rows' => $this->consultarConsumoInventario($idAreaFiltro, $fechaHasta),
            ], 'Consumo de inventario consultado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el consumo de inventario.', 500);
        }
    }

    public function consumoTecnico(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'id_area' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $hoy = Carbon::today()->toDateString();
            $fechaHasta = $this->normalizarFechaCorte($validated['fecha_hasta'] ?? null);
            $fechaDesde = $validated['fecha_desde'] ?? Carbon::createFromFormat('Y-m-d', $fechaHasta)
                ->subMonthsNoOverflow(3)
                ->toDateString();

            if ($fechaDesde > $hoy) {
                $fechaDesde = $hoy;
            }

            if ($fechaDesde > $fechaHasta) {
                $fechaDesde = Carbon::createFromFormat('Y-m-d', $fechaHasta)
                    ->subMonthsNoOverflow(3)
                    ->toDateString();
            }

            $idAreaFiltro = $this->resolverAreaFiltro($request, $validated['id_area'] ?? null);

            return $this->successResponse([
                'id_area_filtro' => $idAreaFiltro,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'rows' => $this->consultarConsumoPorTecnico($idAreaFiltro, $fechaDesde, $fechaHasta),
            ], 'Consumo por tecnico consultado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el consumo por tecnico.', 500);
        }
    }

    private function normalizarFechaCorte(?string $fecha): string
    {
        $hoy = Carbon::today()->toDateString();

        if (!$fecha) {
            return $hoy;
        }

        return $fecha > $hoy ? $hoy : $fecha;
    }

    private function resolverAreaFiltro(Request $request, mixed $idAreaSolicitada = null): int
    {
        if ($idAreaSolicitada !== null && (int) $idAreaSolicitada >= 0) {
            return (int) $idAreaSolicitada;
        }

        $perfilStaff = $this->resolverPerfilStaff($request);
        $idUsuario = $perfilStaff['staff_id'];
        $idArea = $perfilStaff['id_area'];

        if (in_array($idArea, self::GLOBAL_AREA_IDS, true) || $this->esValidadorReabastecimiento($idUsuario)) {
            return 0;
        }

        return $idArea > 0 ? $idArea : 0;
    }

    /**
     * El dashboard anterior usaba el staff_id/id_area de la BD externa.
     *
     * @return array{staff_id:int,id_area:int}
     */
    private function resolverPerfilStaff(Request $request): array
    {
        $user = $request->user();
        $staffId = (int) (data_get($user, 'staff_id') ?: 0);
        $idArea = (int) (
            data_get($user, 'department_id')
            ?: data_get($user, 'id_area')
            ?: data_get($user, 'area_id')
            ?: 0
        );
        $empCode = trim((string) (data_get($user, 'emp_code') ?: ''));

        if ($empCode !== '') {
            try {
                $staff = DB::connection('mysql_external')
                    ->table('ost_staff')
                    ->select(['staff_id', 'dept_id'])
                    ->where('dni', $empCode)
                    ->first();

                if ($staff) {
                    $staffId = (int) ($staff->staff_id ?? $staffId);
                    $idArea = (int) ($staff->dept_id ?? $idArea);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($staffId <= 0) {
            $staffId = (int) ($user?->id ?? 0);
        }

        return [
            'staff_id' => $staffId,
            'id_area' => $idArea,
        ];
    }

    private function esValidadorReabastecimiento(int $idUsuario): bool
    {
        if ($idUsuario <= 0) {
            return false;
        }

        try {
            return DB::connection('mysql_external')
                ->table('validadores_compra')
                ->where('id_usuario', $idUsuario)
                ->exists();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Consulta equivalente a ControladorSolicitudes::ctrDashboardConsumoInventario.
     *
     * @return array<int, object>
     */
    private function consultarConsumoInventario(int $idArea, string $fechaHasta): array
    {
        $filtrarArea = $idArea > 0;
        $filtroStock = $filtrarArea ? 'WHERE i.id_area = ?' : '';
        $filtroConsumo = $filtrarArea ? 'AND i.id_area = ?' : '';

        $sql = <<<SQL
            SELECT
                p.id_producto,
                p.codigo_producto,
                p.descripcion AS descripcion_producto,
                COALESCE(NULLIF(c.nombre_categoria, ''), 'SIN CATEGORIA') AS categoria,
                COALESCE(NULLIF(ts.descripcion, ''), '-') AS tipo,
                COALESCE(p.es_frecuente, 0) AS es_frecuente,
                COALESCE(stock.stock_actual, 0) AS stock_actual,
                COALESCE(consumo.consumo_15_dias, 0) AS consumo_15_dias,
                COALESCE(consumo.consumo_1_mes, 0) AS consumo_1_mes,
                COALESCE(consumo.consumo_2_meses, 0) AS consumo_2_meses,
                COALESCE(consumo.consumo_3_meses, 0) AS consumo_3_meses
            FROM productos p
            
            LEFT JOIN categorias_inventario c ON c.id_categoria = p.id_categoria
            LEFT JOIN tipos_stock ts ON ts.id_tipo_stock = p.id_tipo_stock
            LEFT JOIN (
                SELECT
                    i.id_producto,
                    SUM(i.stock_actual) AS stock_actual
                FROM inventario i
                {$filtroStock}
                GROUP BY i.id_producto
            ) stock ON stock.id_producto = p.id_producto
            LEFT JOIN (
                SELECT
                    mov.id_producto,
                    SUM(CASE WHEN mov.fecha_consumo BETWEEN DATE_SUB(?, INTERVAL 15 DAY) AND ? THEN mov.cantidad_consumida ELSE 0 END) AS consumo_15_dias,
                    SUM(CASE WHEN mov.fecha_consumo BETWEEN DATE_SUB(?, INTERVAL 1 MONTH) AND ? THEN mov.cantidad_consumida ELSE 0 END) AS consumo_1_mes,
                    SUM(CASE WHEN mov.fecha_consumo BETWEEN DATE_SUB(?, INTERVAL 2 MONTH) AND ? THEN mov.cantidad_consumida ELSE 0 END) AS consumo_2_meses,
                    SUM(CASE WHEN mov.fecha_consumo BETWEEN DATE_SUB(?, INTERVAL 3 MONTH) AND ? THEN mov.cantidad_consumida ELSE 0 END) AS consumo_3_meses
                FROM (
                    SELECT
                        i.id_producto,
                        DATE(d.fecha_atencion) AS fecha_consumo,
                        COALESCE(NULLIF(d.cantidad_aprobada, 0), d.cantidad_solicitada, 0) AS cantidad_consumida
                    FROM solicitud_detalles d
                    INNER JOIN solicitudes s ON s.id_solicitud = d.id_solicitud
                    INNER JOIN inventario i ON i.id_inventario = d.id_inventario
                    WHERE d.id_estado_detalle IN (2, 9)
                      AND d.fecha_atencion IS NOT NULL
                      {$filtroConsumo}
                      AND DATE(d.fecha_atencion) BETWEEN DATE_SUB(?, INTERVAL 3 MONTH) AND ?
                ) mov
                GROUP BY mov.id_producto
            ) consumo ON consumo.id_producto = p.id_producto
            where p.tipo_responsable = 'SSOMA'
            ORDER BY p.descripcion ASC
        SQL;

        $bindings = [];

        if ($filtrarArea) {
            $bindings[] = $idArea;
        }

        $bindings = array_merge($bindings, [
            $fechaHasta,
            $fechaHasta,
            $fechaHasta,
            $fechaHasta,
            $fechaHasta,
            $fechaHasta,
            $fechaHasta,
            $fechaHasta,
        ]);

        if ($filtrarArea) {
            $bindings[] = $idArea;
        }

        $bindings[] = $fechaHasta;
        $bindings[] = $fechaHasta;

        return DB::connection('mysql_external')->select($sql, $bindings);
    }




    /**
     * Consulta equivalente a ControladorSolicitudes::ctrDashboardConsumoPorTecnico.
     *
     * @return array<int, object>
     */
    private function consultarConsumoPorTecnico(int $idArea, string $fechaDesde, string $fechaHasta): array
    {
        $filtrarArea = $idArea > 0;
        $filtroArea = $filtrarArea ? 'AND i.id_area = ?' : '';

        $sql = <<<SQL
            SELECT
                s.id_solicitud,
                s.id_usuario_solicitante AS id_tecnico,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))), ''),
                    COALESCE(u.username, CONCAT('Tecnico #', s.id_usuario_solicitante))
                ) AS tecnico,
                DATE(d.fecha_atencion) AS fecha_consumo,
                d.id_detalle_solicitud,
                p.id_producto,
                p.codigo_producto,
                p.descripcion AS descripcion_producto,
                COALESCE(NULLIF(c.nombre_categoria, ''), 'SIN CATEGORIA') AS categoria,
                COALESCE(NULLIF(d.area_id, 0), i.id_area) AS id_area,
                COALESCE(a.descripcion_area, CONCAT('Area ', COALESCE(NULLIF(d.area_id, 0), i.id_area))) AS area,
                COALESCE(NULLIF(d.cantidad_aprobada, 0), d.cantidad_solicitada, 0) AS cantidad_consumida
            FROM solicitud_detalles d
            INNER JOIN solicitudes s ON s.id_solicitud = d.id_solicitud
            INNER JOIN inventario i ON i.id_inventario = d.id_inventario
            INNER JOIN productos p ON p.id_producto = i.id_producto
            LEFT JOIN categorias_inventario c ON c.id_categoria = p.id_categoria
            LEFT JOIN area a ON a.id_area = COALESCE(NULLIF(d.area_id, 0), i.id_area)
            LEFT JOIN ost_staff u ON u.staff_id = s.id_usuario_solicitante
            WHERE d.id_estado_detalle IN (2, 9)
              AND d.fecha_atencion IS NOT NULL
            --   {$filtroArea}
              AND DATE(d.fecha_atencion) >= ?
              AND DATE(d.fecha_atencion) <= ?
              AND p.tipo_responsable = 'SSOMA'
            ORDER BY fecha_consumo DESC, s.id_solicitud DESC, d.id_detalle_solicitud DESC
        SQL;

        $bindings = [];

        if ($filtrarArea) {
            $bindings[] = $idArea;
        }

        $bindings[] = $fechaDesde;
        $bindings[] = $fechaHasta;

        return DB::connection('mysql_external')->select($sql, $bindings);
    }
}
