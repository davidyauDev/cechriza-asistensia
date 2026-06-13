<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolicitudCompletaRequest;
use App\Http\Requests\UpdateSolicitudDetallesRequest;
use App\Services\SolicitudCompletaServiceInterface;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

class SolicitudCompletaController extends Controller
{
    public function __construct(
        private readonly SolicitudCompletaServiceInterface $service
    ) {
    }

    public function store(StoreSolicitudCompletaRequest $request): JsonResponse
    {
        try {
            $result = $this->service->registrar($request->validated(), $request->allFiles());

            return response()->json([
                'success' => true,
                'message' => 'Solicitud registrada correctamente.',
                'ticket' => $result['ticket'],
                'tickets' => $result['tickets'] ?? [$result['ticket']],
                'uploaded_files' => $result['uploaded_files'] ?? [],
            ], 201);
        } catch (DomainException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error: No se pudo registrar la solicitud.',
            ], 500);
        }
    }

    public function updateDetalles(UpdateSolicitudDetallesRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->service->actualizarDetalles($id, $request->validated(), $request->allFiles());

            return response()->json([
                'success' => true,
                'message' => 'Detalles de la solicitud actualizados correctamente.',
                'id_solicitud' => $result['id_solicitud'],
                'ticket' => $result['ticket'],
                'detalles_actualizados' => $result['detalles_actualizados'],
                'detalles_creados' => $result['detalles_creados'],
                'detalles_eliminados' => $result['detalles_eliminados'],
                'uploaded_files' => $result['uploaded_files'],
                'detalles' => $result['detalles'],
            ], 200);
        } catch (DomainException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error: No se pudieron actualizar los detalles de la solicitud.',
            ], 500);
        }
    }
}
