<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TechnicianServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    public function __construct(
        private TechnicianServiceInterface $service
    ) {
    }

    /**
     * Obtener rutas de técnicos por día según emp_code
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getRutasTecnicosDia(Request $request): JsonResponse
    {
        $request->validate([
            'emp_code' => 'required|string'
        ]);

        try {
            $rutas = $this->service->getRutasTecnicosDia($request->emp_code);

            return response()->json([
                'success' => true,
                'data' => $rutas,
                'meta' => [
                    'emp_code' => $request->emp_code,
                    'total_rutas' => $rutas->count(),
                    'fecha_consulta' => now()->toDateTimeString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener rutas de técnicos: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
