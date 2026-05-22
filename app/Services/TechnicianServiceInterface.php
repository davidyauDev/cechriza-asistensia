<?php

namespace App\Services;

interface TechnicianServiceInterface
{
    /**
     * Obtener rutas de técnicos por día según emp_code
     *
     * @param string $empCode Código del empleado
     * @param string $fecha Fecha a consultar en formato Y-m-d
     */
    public function getRutasTecnicosDia(string $empCode, string $fecha): array;
}
