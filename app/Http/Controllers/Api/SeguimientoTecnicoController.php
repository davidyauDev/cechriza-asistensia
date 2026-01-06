<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeguimientoTecnicoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $results = DB::connection('mysql_external')->select('CALL sp_get_rutas_tecnicos_dia(null)');
            Log::info('Resultados SP sp_get_rutas_tecnicos_dia (sin parámetro):', ['results' => $results]);
            return response()->json([
                'success' => true,
                'data' => $results
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
