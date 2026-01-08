<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class SeguimientoTecnicoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date'
        ]);

        try {
            $fecha = $request->query('fecha');
            $dni = $request->query('dni'); // Opcional
            
            $results = DB::connection('mysql_external')->select(
                'CALL sp_get_rutas_tecnicos_dia_fecha(?, ?)',
                [$dni, $fecha]
            );

            Log::info('Resultados SP sp_get_rutas_tecnicos_dia_fecha', [
                'dni' => $dni,
                'fecha' => $fecha,
                'count' => count($results)
            ]);

            $query = "
                SELECT *
                FROM iclock_transaction it
                WHERE it.punch_time >= '{$fecha} 00:00:00-05'
                  AND it.punch_time <  '{$fecha} 23:59:59-05'
                  AND it.terminal_sn = 'App';
            ";
            $resultPgsql = DB::connection('pgsql_external')->select($query);

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
                'fecha' => $fecha,
                'dni' => $dni,
                'grouped' => $grouped
            ]);
        } catch (\Exception $e) {
            Log::error('Error al ejecutar SP sp_get_rutas_tecnicos_dia_fecha: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el SP',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
