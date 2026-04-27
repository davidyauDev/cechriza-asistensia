<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudGasto;
use App\Models\SolicitudGastoDetalle;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SolicitudGastoRegistroController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1', 'exists:mysql_external.ost_staff,staff_id'],
            'id_area' => ['required', 'integer', 'min:1', 'exists:mysql_external.area,id_area'],
            'motivo' => ['required', 'string', 'max:255'],
            'monto_estimado' => ['required', 'numeric', 'min:0'],
            'monto_real' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['nullable', 'string', 'max:30'],
            'fecha_solicitud' => ['nullable', 'date'],
            'fecha_aprobacion' => ['nullable', 'date'],
            'fecha_reembolso' => ['nullable', 'date'],
            'solicitud_gasto_detalles' => ['required', 'array', 'min:1'],
            'solicitud_gasto_detalles.*.id_producto' => ['required', 'integer', 'min:1', 'exists:mysql_external.productos,id_producto'],
            'solicitud_gasto_detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.precio_estimado' => ['required', 'numeric', 'min:0'],
            'solicitud_gasto_detalles.*.precio_real' => ['nullable', 'numeric', 'min:0'],
            'solicitud_gasto_detalles.*.descripcion_adicional' => ['nullable', 'string', 'max:1000'],
            'solicitud_gasto_detalles.*.ruta_imagen' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $result = DB::connection('mysql_external')->transaction(function () use ($validated): array {
                $solicitud = new SolicitudGasto();
                $solicitud->setConnection('mysql_external');
                $solicitud->fill([
                    'staff_id' => (int) $validated['staff_id'],
                    'id_area' => (int) $validated['id_area'],
                    'motivo' => $validated['motivo'],
                    'monto_estimado' => (float) $validated['monto_estimado'],
                    'monto_real' => isset($validated['monto_real']) ? (float) $validated['monto_real'] : 0.00,
                    'estado' => $validated['estado'] ?? 'pendiente',
                    'fecha_solicitud' => $validated['fecha_solicitud'] ?? now(),
                    'fecha_aprobacion' => $validated['fecha_aprobacion'] ?? null,
                    'fecha_reembolso' => $validated['fecha_reembolso'] ?? null,
                ]);
                $solicitud->save();

                $detallesPayload = [];

                foreach ($validated['solicitud_gasto_detalles'] as $detalle) {
                    $detalleModel = new SolicitudGastoDetalle();
                    $detalleModel->setConnection('mysql_external');
                    $detalleModel->fill([
                        'solicitud_gasto_id' => (int) $solicitud->id,
                        'id_producto' => (int) $detalle['id_producto'],
                        'cantidad' => (int) $detalle['cantidad'],
                        'precio_estimado' => (float) $detalle['precio_estimado'],
                        'precio_real' => isset($detalle['precio_real']) ? (float) $detalle['precio_real'] : 0.00,
                        'descripcion_adicional' => $detalle['descripcion_adicional'] ?? null,
                        'ruta_imagen' => $detalle['ruta_imagen'] ?? null,
                    ]);
                    $detalleModel->save();

                    $detallesPayload[] = [
                        'id' => (int) $detalleModel->id,
                        'solicitud_gasto_id' => (int) $detalleModel->solicitud_gasto_id,
                        'id_producto' => (int) $detalleModel->id_producto,
                        'cantidad' => (int) $detalleModel->cantidad,
                        'precio_estimado' => (float) $detalleModel->precio_estimado,
                        'precio_real' => (float) $detalleModel->precio_real,
                        'descripcion_adicional' => $detalleModel->descripcion_adicional,
                        'ruta_imagen' => $detalleModel->ruta_imagen,
                    ];
                }

                return [
                    'solicitud_gasto' => [
                        'id' => (int) $solicitud->id,
                        'staff_id' => (int) $solicitud->staff_id,
                        'id_area' => (int) $solicitud->id_area,
                        'motivo' => $solicitud->motivo,
                        'monto_estimado' => (float) $solicitud->monto_estimado,
                        'monto_real' => (float) $solicitud->monto_real,
                        'estado' => $solicitud->estado,
                        'fecha_solicitud' => $solicitud->fecha_solicitud,
                        'fecha_aprobacion' => $solicitud->fecha_aprobacion,
                        'fecha_reembolso' => $solicitud->fecha_reembolso,
                    ],
                    'solicitud_gasto_detalles' => $detallesPayload,
                ];
            });

            return $this->successResponse($result, 'Solicitud de gasto registrada correctamente', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo registrar la solicitud de gasto.', 500);
        }
    }
}

