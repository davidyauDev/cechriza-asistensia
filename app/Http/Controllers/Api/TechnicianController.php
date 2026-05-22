<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TechnicianServiceInterface;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{

    use ApiResponseTrait;

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
            'emp_code' => 'required|string',
            'fecha' => 'nullable|date',
        ]);

        $fecha = $request->query('fecha')
            ? Carbon::parse($request->query('fecha'))->format('Y-m-d')
            : Carbon::now('America/Lima')->format('Y-m-d');

        return $this->successResponse(
            $this->service->getRutasTecnicosDia($request->emp_code, $fecha),
            'Rutas de técnicos retrieved successfully'
        );
    }
}
