<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudGasto\ComprobanteGasto;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ComprobanteGastoRegistroController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'solicitud_gasto_id' => ['required', 'integer', 'min:1', 'exists:mysql_external.solicitudes_gasto,id'],
            'tipo' => ['required', 'string', 'max:50'],
            'numero' => ['required', 'string', 'max:100'],
            'monto' => ['required', 'numeric', 'min:0'],
            'archivo_url' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $payload = DB::connection('mysql_external')->transaction(function () use ($validated): array {
                $comprobante = new ComprobanteGasto();
                $comprobante->setConnection('mysql_external');
                $comprobante->fill([
                    'solicitud_gasto_id' => (int) $validated['solicitud_gasto_id'],
                    'tipo' => $validated['tipo'],
                    'numero' => $validated['numero'],
                    'monto' => (float) $validated['monto'],
                    'archivo_url' => $validated['archivo_url'] ?? null,
                ]);
                $comprobante->save();

                return [
                    'id' => (int) $comprobante->id,
                    'solicitud_gasto_id' => (int) $comprobante->solicitud_gasto_id,
                    'tipo' => $comprobante->tipo,
                    'numero' => $comprobante->numero,
                    'monto' => (float) $comprobante->monto,
                    'archivo_url' => $comprobante->archivo_url,
                ];
            });

            return $this->successResponse($payload, 'Comprobante de gasto registrado correctamente', 201);
        } catch (Throwable $e) {
            report($e);

            if (config('app.debug')) {
                return $this->errorResponse('No se pudo registrar el comprobante de gasto: '.$e->getMessage(), 500);
            }

            return $this->errorResponse('No se pudo registrar el comprobante de gasto.', 500);
        }
    }
}
