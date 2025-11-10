<?php

namespace App\Services;

use App\Repositories\TechnicianRepositoryInterface;
use Illuminate\Support\Collection;

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
     * @return Collection
     */
    public function getRutasTecnicosDia(string $empCode): Collection
    {
        return $this->repository->getRutasTecnicosDia($empCode);
    }
}
