<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeguimientoTecnicoController extends Controller
{
    public function index()
    {
        try {
            $results = DB::connection('mysql_external')->select('CALL sp_get_rutas_tecnicos_dia(null)');
            Log::info('Resultados SP sp_get_rutas_tecnicos_dia (sin parámetro):', ['results' => $results]);
            $query = "
                SELECT *
                FROM iclock_transaction it
                WHERE it.punch_time >= '2026-01-07 00:00:00-05'
                  AND it.punch_time <  '2026-01-08 00:00:00-05'
                  AND it.terminal_sn = 'App';
            ";
            $resultPgsql = DB::connection('pgsql_external')->select($query);

            // Agrupar iclock_transactions por emp_code
            $iclockByEmpCode = [];
            foreach ($resultPgsql as $row) {
                $iclockByEmpCode[$row->emp_code][] = $row;
            }

            // Agrupar por dni y asociar iclock_transactions
            $grouped = [];
            foreach ($results as $ruta) {
                $dni = $ruta->dni; // <-- corregido aquí
                $grouped[$dni]['rutas'][] = $ruta;
                if (isset($iclockByEmpCode[$dni])) {
                    $grouped[$dni]['iclock_transactions'] = $iclockByEmpCode[$dni];
                } else {
                    $grouped[$dni]['iclock_transactions'] = ['message' => 'No marcó'];
                }
            }

            return response()->json([
                'success' => true,
                'grouped' => $grouped
            ]);
        } catch (\Exception $e) {
            Log::error('Error al ejecutar SP sp_get_rutas_tecnicos_dia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el SP',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
