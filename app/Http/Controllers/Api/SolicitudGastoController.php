<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SolicitudGastoController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'solicitud_gasto_id' => 'nullable|integer|min:1',
            'staff_id' => 'nullable|integer|min:1',
            'id_area' => 'nullable|integer|min:1',
            'tipo' => 'nullable|string|max:50',
            'numero' => 'nullable|string|max:100',
            'estado_id' => 'nullable|integer|min:1',
        ]);

        try {
            [$sql, $bindings] = $this->buildIndexQuery($validated);

            $rows = $this->getConnection()->select($sql, $bindings);
            $solicitudIds = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->all();

            $detallesPorSolicitud = $this->getDetallesPorSolicitudIds(
                $this->getConnection(),
                $solicitudIds
            );
            $seguimientosPorSolicitud = $this->getSeguimientosPorSolicitudIds(
                $this->getConnection(),
                $solicitudIds
            );

            $payload = collect($rows)
                ->map(fn (object $row): array => $this->buildIndexPayload(
                    $row,
                    $detallesPorSolicitud[(int) $row->id] ?? [],
                    $seguimientosPorSolicitud[(int) $row->id] ?? []
                ))
                ->values()
                ->all();

            return $this->successResponse(
                $payload,
                'Comprobantes de gasto consultados correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron consultar los comprobantes de gasto.', 500);
        }
    }

    public function historial(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, $id);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de gasto no encontrada.', 404);
            }

            $rows = $connection->select(
                $this->buildHistorialQuery(),
                [$id]
            );

            $payload = collect($rows)
                ->map(fn (object $row): array => $this->buildHistorialPayload($row))
                ->values()
                ->all();

            return $this->successResponse([
                'solicitud_gasto' => $this->buildSolicitudPayload($solicitud),
                'data' => $payload,
            ], 'Historial de solicitud de gasto consultado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el historial de la solicitud de gasto.', 500);
        }
    }

    protected function buildIndexQuery(array $filters): array
    {
        $clauses = [];
        $bindings = [];

        if (isset($filters['solicitud_gasto_id'])) {
            $clauses[] = 'sg.id = ?';
            $bindings[] = (int) $filters['solicitud_gasto_id'];
        }

        if (isset($filters['staff_id'])) {
            $clauses[] = 'sg.staff_id = ?';
            $bindings[] = (int) $filters['staff_id'];
        }

        if (isset($filters['id_area'])) {
            $clauses[] = 'sg.id_area = ?';
            $bindings[] = (int) $filters['id_area'];
        }

        if (isset($filters['tipo'])) {
            $clauses[] = 'cg.tipo = ?';
            $bindings[] = $filters['tipo'];
        }

        if (isset($filters['numero'])) {
            $clauses[] = 'cg.numero LIKE ?';
            $bindings[] = '%'.$filters['numero'].'%';
        }

        if (isset($filters['estado_id'])) {
            $clauses[] = 'sg.estado_id = ?';
            $bindings[] = (int) $filters['estado_id'];
        }

        $sql = <<<'SQL'
            SELECT
                sg.id,
                sg.staff_id,
                sg.id_area,
                sg.estado_id,
                sg.motivo,
                sg.monto_estimado,
                sg.monto_real,
                sg.estado,
                sg.fecha_solicitud,
                sg.fecha_aprobacion,
                sg.fecha_reembolso,
                os.username,
                os.firstname,
                os.lastname,
                a.descripcion_area AS area,
                sge.id AS estado_rel_id,
                sge.codigo AS estado_rel_codigo,
                sge.nombre AS estado_rel_nombre,
                sge.tipo AS estado_rel_tipo,
                sge.activo AS estado_rel_activo,
                sge.orden AS estado_rel_orden,
                sge.es_aprobacion AS estado_rel_es_aprobacion,
                sge.es_rechazo AS estado_rel_es_rechazo,
                cg.id AS comprobante_id,
                cg.tipo AS comprobante_tipo,
                cg.numero AS comprobante_numero,
                cg.monto AS comprobante_monto,
                cg.archivo_url AS comprobante_archivo_url
            FROM solicitudes_gasto sg
            LEFT JOIN ost_staff os ON os.staff_id = sg.staff_id
            LEFT JOIN area a ON a.id_area = sg.id_area
            LEFT JOIN solicitud_gasto_estados sge ON sge.id = sg.estado_id
            LEFT JOIN (
                SELECT cg1.*
                FROM comprobantes_gasto cg1
                INNER JOIN (
                    SELECT solicitud_gasto_id, MAX(id) AS id
                    FROM comprobantes_gasto
                    GROUP BY solicitud_gasto_id
                ) latest ON latest.id = cg1.id
            ) cg ON cg.solicitud_gasto_id = sg.id
SQL;

        if ($clauses !== []) {
            $sql .= "\n            WHERE ".implode("\n              AND ", $clauses);
        }

        $sql .= "\n            ORDER BY sg.id DESC";

        return [$sql, $bindings];
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     * @param  array<int, array<string, mixed>>  $seguimientos
     */
    protected function buildIndexPayload(object $row, array $detalles = [], array $seguimientos = []): array
    {
        return [
            'id' => (int) $row->id,
            'solicitud_gasto_id' => (int) $row->id,
            'monto_estimado' => $row->monto_estimado !== null ? (float) $row->monto_estimado : null,
            'monto_real' => $row->monto_real !== null ? (float) $row->monto_real : null,
            'solicitud_gasto' => [
                'id' => (int) $row->id,
                'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null,
                'id_area' => $row->id_area !== null ? (int) $row->id_area : null,
                'estado_id' => $row->estado_id !== null ? (int) $row->estado_id : null,
                'solicitante' => $this->formatStaffFullName($row),
                'username' => $row->username ?? null,
                'area' => $row->area ?? null,
                'motivo' => $row->motivo ?? null,
                'estado' => $row->estado ?? null,
                'estado_detalle' => [
                    'id' => $row->estado_rel_id !== null ? (int) $row->estado_rel_id : null,
                    'codigo' => $row->estado_rel_codigo ?? null,
                    'nombre' => $row->estado_rel_nombre ?? null,
                    'tipo' => $row->estado_rel_tipo ?? null,
                    'activo' => $row->estado_rel_activo !== null ? (int) $row->estado_rel_activo : null,
                    'orden' => $row->estado_rel_orden !== null ? (int) $row->estado_rel_orden : null,
                    'es_aprobacion' => $row->estado_rel_es_aprobacion !== null ? (int) $row->estado_rel_es_aprobacion : null,
                    'es_rechazo' => $row->estado_rel_es_rechazo !== null ? (int) $row->estado_rel_es_rechazo : null,
                ],
                'fecha_solicitud' => $row->fecha_solicitud ?? null,
                'fecha_aprobacion' => $row->fecha_aprobacion ?? null,
                'fecha_reembolso' => $row->fecha_reembolso ?? null,
            ],
            'comprobante' => [
                'id' => $row->comprobante_id !== null ? (int) $row->comprobante_id : null,
                'tipo' => $row->comprobante_tipo ?? null,
                'numero' => $row->comprobante_numero ?? null,
                'monto' => $row->comprobante_monto !== null ? (float) $row->comprobante_monto : null,
                'archivo_url' => $row->comprobante_archivo_url ?? null,
            ],
            'solicitud_gasto_detalles' => $detalles,
            'seguimientos_solicitud_gasto' => $seguimientos,
        ];
    }

    /**
     * @param  array<int, int>  $solicitudGastoIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function getDetallesPorSolicitudIds($connection, array $solicitudGastoIds): array
    {
        $solicitudGastoIds = array_values(array_unique(array_filter(
            array_map('intval', $solicitudGastoIds),
            fn (int $id): bool => $id > 0
        )));

        if ($solicitudGastoIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($solicitudGastoIds), '?'));
        $rows = $connection->select(
            <<<SQL
                SELECT
                    d.id,
                    d.solicitud_gasto_id,
                    d.id_producto,
                    d.cantidad,
                    d.precio_estimado,
                    d.precio_real,
                    d.descripcion_adicional,
                    d.ruta_imagen,
                    p.descripcion AS producto
                FROM solicitud_gasto_detalles d
                LEFT JOIN productos p ON p.id_producto = d.id_producto
                WHERE d.solicitud_gasto_id IN ({$placeholders})
                ORDER BY d.solicitud_gasto_id DESC, d.id ASC
SQL,
            $solicitudGastoIds
        );

        return collect($rows)
            ->groupBy(fn (object $row): int => (int) $row->solicitud_gasto_id)
            ->map(fn ($items): array => collect($items)
                ->map(fn (object $row): array => $this->buildDetallePayload($row))
                ->values()
                ->all())
            ->all();
    }

    protected function buildDetallePayload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'solicitud_gasto_id' => (int) $row->solicitud_gasto_id,
            'id_producto' => $row->id_producto !== null ? (int) $row->id_producto : null,
            'cantidad' => $row->cantidad !== null ? (int) $row->cantidad : null,
            'precio_estimado' => $row->precio_estimado !== null ? (float) $row->precio_estimado : null,
            'precio_real' => $row->precio_real !== null ? (float) $row->precio_real : null,
            'descripcion_adicional' => $row->descripcion_adicional ?? null,
            'ruta_imagen' => $row->ruta_imagen ?? null,
            'url_imagen' => $this->buildPublicUrl($row->ruta_imagen ?? null),
            'producto' => $row->producto ?? null,
        ];
    }

    /**
     * @param  array<int, int>  $solicitudGastoIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function getSeguimientosPorSolicitudIds($connection, array $solicitudGastoIds): array
    {
        $solicitudGastoIds = array_values(array_unique(array_filter(
            array_map('intval', $solicitudGastoIds),
            fn (int $id): bool => $id > 0
        )));

        if ($solicitudGastoIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($solicitudGastoIds), '?'));
        $rows = $connection->select(
            <<<SQL
                SELECT
                    ssg.id,
                    ssg.solicitud_gasto_id,
                    ssg.estado_anterior,
                    ssg.estado_nuevo,
                    ssg.comentario,
                    ssg.staff_id,
                    ssg.fecha,
                    os.username,
                    os.firstname,
                    os.lastname,
                    a.descripcion_area AS area
                FROM seguimientos_solicitud_gasto ssg
                LEFT JOIN ost_staff os ON os.staff_id = ssg.staff_id
                LEFT JOIN area a ON a.id_area = os.id_area
                WHERE ssg.solicitud_gasto_id IN ({$placeholders})
                ORDER BY ssg.solicitud_gasto_id DESC, ssg.fecha DESC, ssg.id DESC
SQL,
            $solicitudGastoIds
        );

        return collect($rows)
            ->groupBy(fn (object $row): int => (int) $row->solicitud_gasto_id)
            ->map(fn ($items): array => collect($items)
                ->map(fn (object $row): array => $this->buildHistorialPayload($row))
                ->values()
                ->all())
            ->all();
    }

    protected function buildHistorialQuery(): string
    {
        return <<<'SQL'
            SELECT
                ssg.id,
                ssg.solicitud_gasto_id,
                ssg.estado_anterior,
                ssg.estado_nuevo,
                ssg.comentario,
                ssg.staff_id,
                ssg.fecha,
                os.username,
                os.firstname,
                os.lastname,
                a.descripcion_area AS area
            FROM seguimientos_solicitud_gasto ssg
            LEFT JOIN ost_staff os ON os.staff_id = ssg.staff_id
            LEFT JOIN area a ON a.id_area = os.id_area
            WHERE ssg.solicitud_gasto_id = ?
            ORDER BY ssg.fecha DESC, ssg.id DESC
SQL;
    }

    protected function buildHistorialPayload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'solicitud_gasto_id' => (int) $row->solicitud_gasto_id,
            'estado_anterior' => $row->estado_anterior,
            'estado_nuevo' => $row->estado_nuevo,
            'comentario' => $row->comentario,
            'fecha' => $row->fecha ?? null,
            'usuario' => [
                'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null,
                'username' => $row->username ?? null,
                'firstname' => $row->firstname ?? null,
                'lastname' => $row->lastname ?? null,
                'full_name' => $this->formatStaffFullName($row),
                'area' => $row->area ?? null,
            ],
        ];
    }

    protected function buildSolicitudPayload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null,
            'id_area' => $row->id_area !== null ? (int) $row->id_area : null,
            'solicitante' => $this->formatStaffFullName($row),
            'username' => $row->username ?? null,
            'area' => $row->area ?? null,
            'motivo' => $row->motivo ?? null,
            'monto_estimado' => $row->monto_estimado !== null ? (float) $row->monto_estimado : null,
            'monto_real' => $row->monto_real !== null ? (float) $row->monto_real : null,
            'estado' => $row->estado ?? null,
            'fecha_solicitud' => $row->fecha_solicitud ?? null,
            'fecha_aprobacion' => $row->fecha_aprobacion ?? null,
            'fecha_reembolso' => $row->fecha_reembolso ?? null,
        ];
    }

    protected function findSolicitudById($connection, int $id): ?object
    {
        $rows = $connection->select(
            <<<'SQL'
                SELECT
                    sg.id,
                    sg.staff_id,
                    sg.id_area,
                    sg.motivo,
                    sg.monto_estimado,
                    sg.monto_real,
                    sg.estado,
                    sg.fecha_solicitud,
                    sg.fecha_aprobacion,
                    sg.fecha_reembolso,
                    os.username,
                    os.firstname,
                    os.lastname,
                    a.descripcion_area AS area
                FROM solicitudes_gasto sg
                LEFT JOIN ost_staff os ON os.staff_id = sg.staff_id
                LEFT JOIN area a ON a.id_area = sg.id_area
                WHERE sg.id = ?
                LIMIT 1
SQL,
            [$id]
        );

        return $rows[0] ?? null;
    }

    protected function formatStaffFullName(object $row): ?string
    {
        $firstname = trim((string) ($row->firstname ?? ''));
        $lastname = trim((string) ($row->lastname ?? ''));
        $fullName = trim($firstname.' '.$lastname);

        return $fullName !== '' ? $fullName : null;
    }

    protected function getConnection()
    {
        return DB::connection('mysql_external');
    }

    protected function buildPublicUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $appUrl = trim((string) config('app.url'), '/');

        if ($appUrl === '') {
            return null;
        }

        return $appUrl.'/storage/'.ltrim($path, '/');
    }
}
