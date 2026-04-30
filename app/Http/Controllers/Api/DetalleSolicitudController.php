<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DetalleSolicitudController extends Controller
{
    use ApiResponseTrait;

    private const ESTADO_DETALLE_APROBADO = 2;

    private const ESTADO_DETALLE_RECHAZADO = 3;

    private const ESTADO_GENERAL_AVANZAR = 20;

    private const ESTADO_GENERAL_BLOQUEADO = 9;

    public function aprobar(Request $request, int $detalleId): JsonResponse
    {
        $validated = $request->validate([
            'cantidad_aprobada' => 'required|integer|min:1',
            'motivo' => 'nullable|string|max:1000',
            'id_usuario_atendio' => 'nullable|integer',
        ]);

        try {
            $connection = $this->getConnection();
            $detalle = $this->findDetalleById($connection, $detalleId);

            if (! $detalle) {
                return $this->errorResponse('Detalle de solicitud no encontrado.', 404);
            }

            $usuarioId = $validated['id_usuario_atendio'] ?? $request->user()?->id;
            if (! $usuarioId) {
                return $this->errorResponse('No se pudo resolver el usuario que atiende.', 422);
            }

            $cantidadAprobada = (int) $validated['cantidad_aprobada'];
            $motivo = $validated['motivo'] ?? null;

            $result = $connection->transaction(function () use (
                $connection,
                $detalle,
                $cantidadAprobada,
                $motivo,
                $usuarioId
            ) {
                $stockActualizado = $connection->update(
                    'UPDATE inventario
                     SET stock_actual = stock_actual - ?
                     WHERE id_inventario = ?
                       AND stock_actual >= ?',
                    [
                        $cantidadAprobada,
                        (int) $detalle->id_inventario,
                        $cantidadAprobada,
                    ]
                );

                if ($stockActualizado === 0) {
                    throw new RuntimeException('Stock insuficiente para aprobar el detalle.');
                }

                $connection->update(
                    'UPDATE solicitud_detalles
                     SET cantidad_aprobada = ?,
                         motivo_rechazo = ?,
                         id_usuario_atendio = ?,
                         fecha_atencion = NOW(),
                         id_estado_detalle = ?
                     WHERE id_detalle_solicitud = ?',
                    [
                        $cantidadAprobada,
                        $motivo,
                        (int) $usuarioId,
                        self::ESTADO_DETALLE_APROBADO,
                        (int) $detalle->id_detalle_solicitud,
                    ]
                );

                $tienePendientes = $this->hasPendingDetalles(
                    $connection,
                    (int) $detalle->id_solicitud
                );

                 $connection->update(
                     'UPDATE solicitudes
                      SET id_estado_general = ?
                      WHERE id_solicitud = ?
                        AND id_estado_general <> ?
                        AND id_estado_general <> ?',
                     [
                         self::ESTADO_GENERAL_AVANZAR,
                         (int) $detalle->id_solicitud,
                         self::ESTADO_GENERAL_BLOQUEADO,
                         self::ESTADO_GENERAL_AVANZAR,
                     ]
                 );

                return [
                    'id_detalle_solicitud' => (int) $detalle->id_detalle_solicitud,
                    'id_solicitud' => (int) $detalle->id_solicitud,
                    'cantidad_aprobada' => $cantidadAprobada,
                    'id_estado_detalle' => self::ESTADO_DETALLE_APROBADO,
                    'tiene_detalles_pendientes' => $tienePendientes,
                ];
            });

            return $this->successResponse($result, 'Detalle de solicitud aprobado correctamente');
        } catch (RuntimeException $e) {
            report($e);

            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo aprobar el detalle de solicitud.', 500);
        }
    }

    public function rechazar(Request $request, int $detalleId): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => 'required|string|max:1000',
            'id_usuario_atendio' => 'nullable|integer',
        ]);

        try {
            $connection = $this->getConnection();
            $detalle = $this->findDetalleById($connection, $detalleId);

            if (! $detalle) {
                return $this->errorResponse('Detalle de solicitud no encontrado.', 404);
            }

            $usuarioId = $validated['id_usuario_atendio'] ?? $request->user()?->id;
            if (! $usuarioId) {
                return $this->errorResponse('No se pudo resolver el usuario que atiende.', 422);
            }

            $motivo = $validated['motivo'];

            $result = $connection->transaction(function () use (
                $connection,
                $detalle,
                $motivo,
                $usuarioId
            ) {
                $connection->update(
                    'UPDATE solicitud_detalles
                     SET cantidad_aprobada = 0,
                         motivo_rechazo = ?,
                         id_usuario_atendio = ?,
                         fecha_atencion = NOW(),
                         id_estado_detalle = ?
                     WHERE id_detalle_solicitud = ?',
                    [
                        $motivo,
                        (int) $usuarioId,
                        self::ESTADO_DETALLE_RECHAZADO,
                        (int) $detalle->id_detalle_solicitud,
                    ]
                );

                $tienePendientes = $this->hasPendingDetalles(
                    $connection,
                    (int) $detalle->id_solicitud
                );

                 $connection->update(
                     'UPDATE solicitudes
                      SET id_estado_general = ?
                      WHERE id_solicitud = ?
                        AND id_estado_general <> ?
                        AND id_estado_general <> ?',
                     [
                         self::ESTADO_GENERAL_AVANZAR,
                         (int) $detalle->id_solicitud,
                         self::ESTADO_GENERAL_BLOQUEADO,
                         self::ESTADO_GENERAL_AVANZAR,
                     ]
                 );

                return [
                    'id_detalle_solicitud' => (int) $detalle->id_detalle_solicitud,
                    'id_solicitud' => (int) $detalle->id_solicitud,
                    'cantidad_aprobada' => 0,
                    'id_estado_detalle' => self::ESTADO_DETALLE_RECHAZADO,
                    'tiene_detalles_pendientes' => $tienePendientes,
                ];
            });

            return $this->successResponse($result, 'Detalle de solicitud rechazado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo rechazar el detalle de solicitud.', 500);
        }
    }
    public function derivarLogistica(int $detalleId): JsonResponse
    {
        try {
            $connection = $this->getConnection();
            $detalle = $this->findDetalleById($connection, $detalleId);

            if (! $detalle) {
                return $this->errorResponse('Detalle de solicitud no encontrado.', 404);
            }

            $connection->update(
                'UPDATE solicitud_detalles
                 SET derivado_a_logistica = 1
                 WHERE id_detalle_solicitud = ?',
                [$detalleId]
            );

            return $this->successResponse([
                'id_detalle_solicitud' => (int) $detalle->id_detalle_solicitud,
                'id_solicitud' => (int) $detalle->id_solicitud,
                'derivado_a_logistica' => true,
            ], 'Detalle derivado a logistica correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo derivar el detalle a logistica.', 500);
        }
    }
    protected function findDetalleById($connection, int $detalleId): ?object
    {
        $rows = $connection->select(
            'SELECT
                id_detalle_solicitud,
                id_solicitud,
                id_inventario,
                cantidad_solicitada,
                id_estado_detalle
             FROM solicitud_detalles
             WHERE id_detalle_solicitud = ?
             LIMIT 1',
            [$detalleId]
        );

        return $rows[0] ?? null;
    }

    protected function hasPendingDetalles($connection, int $solicitudId): bool
    {
        $rows = $connection->select(
            'SELECT 1
             FROM solicitud_detalles
             WHERE id_solicitud = ?
               AND id_estado_detalle = 11
             LIMIT 1',
            [$solicitudId]
        );

        return $rows !== [];
    }

    protected function getConnection()
    {
        return DB::connection('mysql_external');
    }
}

