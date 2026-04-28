<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudGasto\ComprobanteGasto;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'archivo' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $storedPath = null;

        try {
            /** @var UploadedFile $archivo */
            $archivo = $request->file('archivo');
            $storedPath = $this->storeComprobanteFile((int) $validated['solicitud_gasto_id'], $archivo);
            $publicUrl = $this->buildPublicUrl($storedPath);

            $payload = DB::connection('mysql_external')->transaction(function () use ($validated, $publicUrl): array {
                $comprobante = new ComprobanteGasto();
                $comprobante->setConnection('mysql_external');
                $comprobante->fill([
                    'solicitud_gasto_id' => (int) $validated['solicitud_gasto_id'],
                    'tipo' => $validated['tipo'],
                    'numero' => $validated['numero'],
                    'monto' => (float) $validated['monto'],
                    'archivo_url' => $publicUrl,
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
            if ($storedPath !== null && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }

            report($e);

            if (config('app.debug')) {
                return $this->errorResponse('No se pudo registrar el comprobante de gasto: '.$e->getMessage(), 500);
            }

            return $this->errorResponse('No se pudo registrar el comprobante de gasto.', 500);
        }
    }

    protected function storeComprobanteFile(int $solicitudGastoId, UploadedFile $file): string
    {
        $directory = 'uploads/solicitudes-gasto/'.$solicitudGastoId.'/comprobantes';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $filename = sprintf(
            'comprobante_%d_%s_%s.%s',
            $solicitudGastoId,
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
