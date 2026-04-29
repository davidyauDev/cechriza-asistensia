<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SolicitudController extends Controller
{
    use ApiResponseTrait;

    private const PEDIDO_COMPRA_ESTADO_FILTRADO = 0;

    private const AREA_FILTRO = 'RR.HH.';

    public function index(Request $request): JsonResponse
    {
        try {
            $idUsuarioSolicitante = filter_var(
                $request->input('id_usuario_solicitante'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $idUsuarioSolicitante = $idUsuarioSolicitante !== false ? (int) $idUsuarioSolicitante : null;

            $rows = $this->getConnection()->select(
                $this->buildIndexSql($idUsuarioSolicitante),
                $idUsuarioSolicitante === null
                    ? [
                        self::PEDIDO_COMPRA_ESTADO_FILTRADO,
                        self::AREA_FILTRO,
                        'INTERNO',
                        'MIXTO',
                    ]
                    : [
                        $idUsuarioSolicitante,
                    ]
            );

            $solicitudIds = collect($rows)->pluck('id_solicitud')->map(fn ($id): int => (int) $id)->all();
            $detallesPorSolicitud = $this->getDetallesPorSolicitudIds(
                $this->getConnection(),
                $solicitudIds
            );

            $payload = collect($rows)
                ->map(fn (object $row): array => $this->buildIndexPayload(
                    $row,
                    $detallesPorSolicitud[(int) $row->id_solicitud] ?? []
                ))
                ->values()
                ->all();

            return $this->successResponse($payload, 'Solicitudes consultadas correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron consultar las solicitudes.', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $rows = $this->getConnection()->select(
                $this->buildShowSql(),
                [$id]
            );

            if ($rows === []) {
                return $this->errorResponse('Solicitud no encontrada.', 404);
            }

            return $this->successResponse(
                $this->buildShowPayload($rows),
                'Solicitud consultada correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el detalle de la solicitud.', 500);
        }
    }

    public function updateEstadoRrhh(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'estado_rrhh' => 'required|string|in:pendiente,derivar_logistica,recojo_oficina',
            'estado_rrhh_comentario' => 'nullable|string|max:1000',
        ]);

        try {
            $connection = $this->getConnection();
            $solicitud = $connection->select(
                'SELECT id_solicitud FROM solicitudes WHERE id_solicitud = ? LIMIT 1',
                [$id]
            );

            if ($solicitud === []) {
                return $this->errorResponse('Solicitud no encontrada.', 404);
            }

            $connection->update(
                'UPDATE solicitudes
                 SET estado_rrhh = ?,
                     estado_rrhh_comentario = ?
                 WHERE id_solicitud = ?',
                [
                    $validated['estado_rrhh'],
                    $validated['estado_rrhh_comentario'] ?? null,
                    $id,
                ]
            );

            return $this->successResponse([
                'id_solicitud' => $id,
                'estado_rrhh' => $validated['estado_rrhh'],
                'estado_rrhh_comentario' => $validated['estado_rrhh_comentario'] ?? null,
            ], 'Estado RRHH actualizado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo actualizar el estado RRHH de la solicitud.', 500);
        }
    }

    public function uploadActaRrhh(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'acta_rrhh' => 'required|file|max:10240',
            'acta_rrhh_comentario' => 'nullable|string|max:1000',
        ]);

        try {
            $connection = $this->getConnection();
            $rows = $connection->select(
                'SELECT id_solicitud FROM solicitudes WHERE id_solicitud = ? LIMIT 1',
                [$id]
            );

            if ($rows === []) {
                return $this->errorResponse('Solicitud no encontrada.', 404);
            }

            $file = $request->file('acta_rrhh');
            $storedPath = $this->storeActaRrhhFile($id, $file);
            $publicUrl = $this->buildPublicUrl($storedPath);

            $connection->update(
                'UPDATE solicitudes
                 SET acta_rrhh_url = ?,
                     acta_rrhh_comentario = ?
                 WHERE id_solicitud = ?',
                [
                    $publicUrl,
                    $validated['acta_rrhh_comentario'] ?? null,
                    $id,
                ]
            );

            return $this->successResponse([
                'id_solicitud' => $id,
                'acta_rrhh_url' => $publicUrl,
                'acta_rrhh_comentario' => $validated['acta_rrhh_comentario'] ?? null,
            ], 'Acta RRHH subida correctamente', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo subir el acta RRHH.', 500);
        }
    }

    protected function buildIndexSql(?int $idUsuarioSolicitante = null): string
    {
        if ($idUsuarioSolicitante === null) {
            $clauses = [
                's.pedido_compra_estado = ?',
                <<<'SQL'
EXISTS (
    SELECT 1
    FROM solicitud_detalles d
    LEFT JOIN inventario i ON i.id_inventario = d.id_inventario
    LEFT JOIN area a ON a.id_area = COALESCE(NULLIF(d.area_id, 0), i.id_area)
    WHERE d.id_solicitud = s.id_solicitud
      AND a.descripcion_area = ?
)
SQL,
                's.tipo_solicitud IN (?, ?)',
            ];
        } else {
            $clauses = [
                's.id_usuario_solicitante = ?',
            ];
        }

        return <<<'SQL'
            SELECT
                s.id_solicitud,
                s.id_usuario_solicitante,
                s.justificacion,
                s.tipo_solicitud,
                s.ubicacion,
                s.estado_rrhh,
                s.estado_rrhh_comentario,
                s.id_estado_general,
                s.fecha_registro,
                e.descripcion AS estado,
                u.firstname,
                u.lastname
            FROM solicitudes s
            INNER JOIN estados_inventario e ON s.id_estado_general = e.id_estado
            INNER JOIN ost_staff u ON s.id_usuario_solicitante = u.staff_id
            WHERE 
SQL
            .implode("\n              AND ", $clauses)
            ."\n            ORDER BY s.fecha_registro DESC";
    }

    protected function buildShowSql(): string
    {
        return <<<'SQL'
            SELECT
                d.id_detalle_solicitud,
                d.id_solicitud,
                d.id_inventario,
                d.area_id,
                a.descripcion_area AS area,
                i.id_area AS id_area_inventario,
                i.stock_actual,
                p.descripcion AS producto,
                d.cantidad_solicitada AS solicitado,
                d.cantidad_aprobada AS aprobado,
                d.cantidad_atendida,
                d.id_estado_detalle,
                e.descripcion AS estado,
                d.url_imagen,
                d.observacion_atencion,
                d.motivo_rechazo AS motivo,
                d.id_usuario_atendio,
                d.fecha_atencion,
                s.id_usuario_solicitante,
                s.fecha_registro,
                s.fecha_necesaria,
                s.fecha_cierre,
                s.prioridad,
                s.ubicacion,
                s.tipo_entrega_preferida,
                s.justificacion,
                u.firstname,
                u.lastname,
                u.email
            FROM solicitudes s
            INNER JOIN ost_staff u ON u.staff_id = s.id_usuario_solicitante
            INNER JOIN solicitud_detalles d ON d.id_solicitud = s.id_solicitud
            INNER JOIN inventario i ON i.id_inventario = d.id_inventario
            INNER JOIN productos p ON p.id_producto = i.id_producto
            LEFT JOIN area a ON a.id_area = d.area_id
            INNER JOIN estados_inventario e ON e.id_estado = d.id_estado_detalle
            WHERE s.id_solicitud = ?
            ORDER BY COALESCE(NULLIF(d.area_id, 0), i.id_area) ASC, p.descripcion ASC
            SQL;
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<string, mixed>
     */
    protected function buildShowPayload(array $rows): array
    {
        $firstRow = $rows[0];

        return [
            'solicitud' => $this->buildSolicitudPayload($firstRow, $rows),
            'detalles' => collect($rows)
                ->map(fn (object $row): array => $this->buildDetallePayload($row))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, object>  $rows
     */
    protected function buildSolicitudPayload(object $row, array $rows): array
    {
        return [
            'id_solicitud' => (int) $row->id_solicitud,
            'id_usuario_solicitante' => (int) $row->id_usuario_solicitante,
            'solicitante' => $this->formatStaffFullName($row),
            'staff' => [
                'firstname' => $row->firstname ?? null,
                'lastname' => $row->lastname ?? null,
                'email' => $row->email ?? null,
            ],
            'justificacion' => $row->justificacion ?? null,
            'id_estado_general' => isset($row->id_estado_general) ? (int) $row->id_estado_general : null,
            'estado' => $row->estado ?? null,
            'fecha_registro' => $row->fecha_registro ?? null,
            'fecha_necesaria' => $row->fecha_necesaria ?? null,
            'fecha_cierre' => $row->fecha_cierre ?? null,
            'prioridad' => $row->prioridad ?? null,
            'ubicacion' => $row->ubicacion ?? null,
            'tipo_entrega_preferida' => $row->tipo_entrega_preferida ?? null,
            'detalles_count' => count($rows),
        ];
    }

    protected function buildIndexPayload(object $row, array $detalles = []): array
    {
        return [
            'id_solicitud' => (int) $row->id_solicitud,
            'id_usuario_solicitante' => (int) $row->id_usuario_solicitante,
            'justificacion' => $row->justificacion ?? null,
            'tipo_solicitud' => $row->tipo_solicitud ?? null,
            'estado_rrhh' => $row->estado_rrhh ?? null,
            'estado_rrhh_comentario' => $row->estado_rrhh_comentario ?? null,
            'id_estado_general' => isset($row->id_estado_general) ? (int) $row->id_estado_general : null,
            'fecha_registro' => $row->fecha_registro ?? null,
            'ubicacion' => $row->ubicacion ?? null,
            'estado' => [
                'id_estado' => isset($row->id_estado_general) ? (int) $row->id_estado_general : null,
                'descripcion' => $row->estado ?? null,
            ],
            'firstname' => $row->firstname ?? null,
            'lastname' => $row->lastname ?? null,
            'solicitante' => $this->formatStaffFullName($row),
            'staff' => [
                'firstname' => $row->firstname ?? null,
                'lastname' => $row->lastname ?? null,
            ],
            'detalles' => $detalles,
        ];
    }

    /**
     * @param  array<int, int>  $solicitudIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function getDetallesPorSolicitudIds($connection, array $solicitudIds): array
    {
        $solicitudIds = array_values(array_unique(array_filter(
            array_map('intval', $solicitudIds),
            fn (int $id): bool => $id > 0
        )));

        if ($solicitudIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($solicitudIds), '?'));
        $rows = $connection->select(
            <<<SQL
                SELECT
                    d.id_detalle_solicitud,
                    d.id_solicitud,
                    d.id_inventario,
                    d.area_id,
                    a.descripcion_area AS area,
                    i.id_area AS id_area_inventario,
                    i.stock_actual,
                    p.descripcion AS producto,
                    d.cantidad_solicitada AS solicitado,
                    d.cantidad_aprobada AS aprobado,
                    d.cantidad_atendida,
                    d.id_estado_detalle,
                    e.descripcion AS estado,
                    d.url_imagen,
                    d.observacion_atencion,
                    d.motivo_rechazo AS motivo,
                    d.id_usuario_atendio,
                    d.fecha_atencion,
                    s.id_usuario_solicitante,
                    s.fecha_registro,
                    s.fecha_necesaria,
                    s.fecha_cierre,
                    s.prioridad,
                    s.ubicacion,
                    s.tipo_entrega_preferida,
                    s.justificacion,
                    u.firstname,
                    u.lastname,
                    u.email
                FROM solicitud_detalles d
                INNER JOIN solicitudes s ON s.id_solicitud = d.id_solicitud
                INNER JOIN ost_staff u ON u.staff_id = s.id_usuario_solicitante
                INNER JOIN inventario i ON i.id_inventario = d.id_inventario
                INNER JOIN productos p ON p.id_producto = i.id_producto
                LEFT JOIN area a ON a.id_area = d.area_id
                INNER JOIN estados_inventario e ON e.id_estado = d.id_estado_detalle
                WHERE d.id_solicitud IN ({$placeholders})
                ORDER BY d.id_solicitud DESC, COALESCE(NULLIF(d.area_id, 0), i.id_area) ASC, p.descripcion ASC
SQL,
            $solicitudIds
        );

        return collect($rows)
            ->groupBy(fn (object $row): int => (int) $row->id_solicitud)
            ->map(fn ($items): array => collect($items)
                ->map(fn (object $row): array => $this->buildDetallePayload($row))
                ->values()
                ->all())
            ->all();
    }

    protected function buildDetallePayload(object $row): array
    {
        return [
            'id_detalle_solicitud' => (int) $row->id_detalle_solicitud,
            'id_solicitud' => (int) $row->id_solicitud,
            'id_inventario' => (int) $row->id_inventario,
            'area_id' => $row->area_id !== null ? (int) $row->area_id : null,
            'area' => $row->area ?? null,
            'id_area_inventario' => $row->id_area_inventario !== null ? (int) $row->id_area_inventario : null,
            'stock_actual' => $row->stock_actual !== null ? (int) $row->stock_actual : null,
            'producto' => $row->producto ?? null,
            'solicitado' => $row->solicitado !== null ? (int) $row->solicitado : null,
            'aprobado' => $row->aprobado !== null ? (int) $row->aprobado : null,
            'cantidad_atendida' => $row->cantidad_atendida !== null ? (int) $row->cantidad_atendida : null,
            'id_estado_detalle' => $row->id_estado_detalle !== null ? (int) $row->id_estado_detalle : null,
            'estado' => $row->estado ?? null,
            'url_imagen' => $row->url_imagen ?? null,
            'observacion_atencion' => $row->observacion_atencion ?? null,
            'motivo' => $row->motivo ?? null,
            'id_usuario_atendio' => $row->id_usuario_atendio !== null ? (int) $row->id_usuario_atendio : null,
            'fecha_atencion' => $row->fecha_atencion ?? null,
            'id_usuario_solicitante' => $row->id_usuario_solicitante !== null ? (int) $row->id_usuario_solicitante : null,
            'fecha_registro' => $row->fecha_registro ?? null,
            'fecha_necesaria' => $row->fecha_necesaria ?? null,
            'fecha_cierre' => $row->fecha_cierre ?? null,
            'prioridad' => $row->prioridad ?? null,
            'ubicacion' => $row->ubicacion ?? null,
            'tipo_entrega_preferida' => $row->tipo_entrega_preferida ?? null,
            'justificacion' => $row->justificacion ?? null,
            'firstname' => $row->firstname ?? null,
            'lastname' => $row->lastname ?? null,
            'email' => $row->email ?? null,
            'solicitante' => $this->formatStaffFullName($row),
            'staff' => [
                'firstname' => $row->firstname ?? null,
                'lastname' => $row->lastname ?? null,
                'email' => $row->email ?? null,
            ],
        ];
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

    protected function storeActaRrhhFile(int $solicitudId, $file): string
    {
        $directory = 'uploads/solicitudes/'.$solicitudId.'/acta_rrhh';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $filename = sprintf(
            'acta_rrhh_%d_%s_%s.%s',
            $solicitudId,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );

        return $file->storeAs($directory, $filename, 'public');
    }

    protected function buildPublicUrl(string $path): ?string
    {
        $appUrl = trim((string) config('app.url'), '/');
        if ($appUrl === '') {
            return null;
        }

        return $appUrl.'/storage/'.ltrim($path, '/');
    }
}
