<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolicitudCompletaRequest;
use App\Services\SolicitudCompletaServiceInterface;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

class SolicitudCompletaController extends Controller
{
    public function __construct(
        private readonly SolicitudCompletaServiceInterface $service
    ) {
        //
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
}
