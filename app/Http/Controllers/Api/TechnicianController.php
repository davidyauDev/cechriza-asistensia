<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TechnicianServiceInterface;
use App\Traits\ApiResponseTrait;
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
            'emp_code' => 'required|string'
        ]);

        return $this->successResponse(
            $this->service->getRutasTecnicosDia($request->emp_code),
            'Rutas de técnicos retrieved successfully'
        );


    }
}
