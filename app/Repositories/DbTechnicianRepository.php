<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DbTechnicianRepository implements TechnicianRepositoryInterface
{
    /**
     * Nombre de la conexión de base de datos externa
     */
    private const DB_CONNECTION = 'mysql_external';

    /**
     * Obtener rutas de técnicos por día según emp_code
     * 
     * Llama al SP: sp_get_rutas_tecnicos_dia
     * 
     * @param string $empCode Código del empleado
     * @return Collection
     */
    public function getRutasTecnicosDia(string $empCode): Collection
    {
        try {
            $results = DB::connection(self::DB_CONNECTION)
                ->select('CALL sp_get_rutas_tecnicos_dia(?)', [$empCode]);

            return collect($results);
        } catch (\Exception $e) {
            Log::error('Error al obtener rutas de técnicos por día: ' . $e->getMessage(), [
                'emp_code' => $empCode,
                'exception' => $e,
                'connection' => self::DB_CONNECTION
            ]);
            
            throw new \RuntimeException('Error al consultar rutas de técnicos desde la base de datos externa: ' . $e->getMessage());
        }
    }
}
