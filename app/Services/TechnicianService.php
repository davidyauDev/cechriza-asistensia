<?php

namespace App\Services;

use App\Repositories\TechnicianRepositoryInterface;

class TechnicianService implements TechnicianServiceInterface
{
    public function __construct(
        private TechnicianRepositoryInterface $repository
    ) {
    }

    /**
     * Obtener rutas de técnicos por día según emp_code
     *
     * @param string $empCode Código del empleado
     * @param string $fecha Fecha a consultar en formato Y-m-d
     */
    public function getRutasTecnicosDia(string $empCode, string $fecha): array
    {
        $rutas = $this->repository->getRutasTecnicosDia($empCode, $fecha);

        return [
            'rutas' => $rutas,
            'meta' => [
                'emp_code' => $empCode,
                'fecha' => $fecha,
                'total_rutas' => $rutas->count(),
                'fecha_consulta' => now()->toDateTimeString(),
            ],
        ];
    }
}
