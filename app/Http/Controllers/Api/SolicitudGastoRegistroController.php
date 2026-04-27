<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudGasto\SolicitudGasto;
use App\Models\SolicitudGasto\SolicitudGastoDetalle;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SolicitudGastoRegistroController extends Controller
{
    use ApiResponseTrait;

    private const DEFAULT_MONTO = 130.00;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1', 'exists:mysql_external.ost_staff,staff_id'],
            'id_area' => ['required', 'integer', 'min:1', 'exists:mysql_external.area,id_area'],
            'motivo' => ['required', 'string', 'max:255'],
            'monto_estimado' => ['nullable', 'numeric', 'min:0'],
            'monto_real' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['nullable', 'string', 'max:30'],
            'fecha_solicitud' => ['nullable', 'date'],
            'fecha_aprobacion' => ['nullable', 'date'],
            'fecha_reembolso' => ['nullable', 'date'],
            'solicitud_gasto_detalles' => ['required', 'array', 'min:1'],
            'solicitud_gasto_detalles.*.id_producto' => ['nullable', 'integer', 'min:1', 'exists:mysql_external.productos,id_producto', 'required_without:solicitud_gasto_detalles.*.id_inventario'],
            'solicitud_gasto_detalles.*.id_inventario' => ['nullable', 'integer', 'min:1', 'exists:mysql_external.inventario,id_inventario', 'required_without:solicitud_gasto_detalles.*.id_producto'],
            'solicitud_gasto_detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.precio_estimado' => ['nullable', 'numeric', 'min:0'],
            'solicitud_gasto_detalles.*.precio_real' => ['nullable', 'numeric', 'min:0'],
            'solicitud_gasto_detalles.*.descripcion_adicional' => ['nullable', 'string', 'max:1000'],
            'solicitud_gasto_detalles.*.ruta_imagen' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $result = DB::connection('mysql_external')->transaction(function () use ($validated): array {
                $inventarioMap = $this->loadInventarioProductMap($validated['solicitud_gasto_detalles']);

                $solicitud = new SolicitudGasto();
                $solicitud->setConnection('mysql_external');
                $solicitud->fill([
                    'staff_id' => (int) $validated['staff_id'],
                    'id_area' => (int) $validated['id_area'],
                    'motivo' => $validated['motivo'],
                    'monto_estimado' => $this->amountOrDefault($validated['monto_estimado'] ?? null),
                    'monto_real' => $this->amountOrDefault($validated['monto_real'] ?? null),
                    'estado' => $validated['estado'] ?? 'pendiente',
                    'fecha_solicitud' => $validated['fecha_solicitud'] ?? now(),
                    'fecha_aprobacion' => $validated['fecha_aprobacion'] ?? null,
                    'fecha_reembolso' => $validated['fecha_reembolso'] ?? null,
                ]);
                $solicitud->save();

                $detallesPayload = [];

                foreach ($validated['solicitud_gasto_detalles'] as $detalle) {
                    $resolvedProductId = $this->resolveDetalleProductId($detalle, $inventarioMap);

                    $detalleModel = new SolicitudGastoDetalle();
                    $detalleModel->setConnection('mysql_external');
                    $detalleModel->fill([
                        'solicitud_gasto_id' => (int) $solicitud->id,
                        'id_producto' => $resolvedProductId,
                        'cantidad' => (int) $detalle['cantidad'],
                        'precio_estimado' => $this->amountOrDefault($detalle['precio_estimado'] ?? null),
                        'precio_real' => $this->amountOrDefault($detalle['precio_real'] ?? null),
                        'descripcion_adicional' => $detalle['descripcion_adicional'] ?? null,
                        'ruta_imagen' => $detalle['ruta_imagen'] ?? null,
                    ]);
                    $detalleModel->save();

                    $detallesPayload[] = [
                        'id' => (int) $detalleModel->id,
                        'solicitud_gasto_id' => (int) $detalleModel->solicitud_gasto_id,
                        'id_producto' => (int) $detalleModel->id_producto,
                        'id_inventario' => isset($detalle['id_inventario']) ? (int) $detalle['id_inventario'] : null,
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

            if (config('app.debug')) {
                return $this->errorResponse('No se pudo registrar la solicitud de gasto: '.$e->getMessage(), 500);
            }

            return $this->errorResponse('No se pudo registrar la solicitud de gasto.', 500);
        }
    }

    protected function amountOrDefault(mixed $value): float
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_MONTO;
        }

        return (float) $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, int>
     */
    protected function loadInventarioProductMap(array $detalles): array
    {
        $inventarioIds = collect($detalles)
            ->pluck('id_inventario')
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($inventarioIds === []) {
            return [];
        }

        return DB::connection('mysql_external')
            ->table('inventario')
            ->whereIn('id_inventario', $inventarioIds)
            ->pluck('id_producto', 'id_inventario')
            ->map(fn ($idProducto): int => (int) $idProducto)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @param  array<int, int>  $inventarioMap
     */
    protected function resolveDetalleProductId(array $detalle, array $inventarioMap): int
    {
        if (isset($detalle['id_producto']) && (int) $detalle['id_producto'] > 0) {
            return (int) $detalle['id_producto'];
        }

        $inventarioId = (int) ($detalle['id_inventario'] ?? 0);
        $productId = $inventarioMap[$inventarioId] ?? 0;

        if ($productId <= 0) {
            abort(422, "No se pudo resolver id_producto para id_inventario {$inventarioId}.");
        }

        return $productId;
    }
}
