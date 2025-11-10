<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

interface TechnicianRepositoryInterface
{
    /**
     * Obtener rutas de técnicos por día según emp_code
     * 
     * @param string $empCode Código del empleado
     * @return Collection
     */
    public function getRutasTecnicosDia(string $empCode): Collection;
}
