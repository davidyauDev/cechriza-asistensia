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
            'fecha' => 'required|date',
            'dni' => 'nullable|string'
        ]);

        try {
            $fecha = $request->query('fecha');
            $dni = $request->query('dni'); 
            $departamentos = [9, 7, 2, 10, 5]; // Áreas técnicas
            
            $rutasResults = DB::connection('mysql_external')->select(
                'CALL sp_get_rutas_tecnicos_dia_fecha(?, ?)',
                [$dni, $fecha]
            );

            Log::info('Resultados SP sp_get_rutas_tecnicos_dia_fecha', [
                'dni' => $dni,
                'fecha' => $fecha,
                'count' => count($rutasResults)
            ]);

            // Obtener marcaciones
            $resultPgsql = DB::connection('pgsql_external')
                ->table('iclock_transaction')
                ->whereRaw('punch_time >= ? AND punch_time < ?', [
                    "{$fecha} 00:00:00-05",
                    "{$fecha} 23:59:59-05"
                ])
                ->where('terminal_sn', 'App')
                ->get();

            $iclockByEmpCode = [];
            foreach ($resultPgsql as $row) {
                $iclockByEmpCode[$row->emp_code][] = $row;
            }

            // Obtener daily_records de la fecha
            $dailyRecordsResult = DB::connection('pgsql_external')
                ->table('daily_records')
                ->select([
                    'id',
                    'date',
                    'employee_id',
                    'emp_code',
                    'concept_id',
                    'day_code',
                    'mobility_eligible',
                    'source',
                    'notes',
                    'processed',
                    'created_at',
                    'updated_at'
                ])
                ->where('date', $fecha)
                ->get();

            $dailyRecordsByEmpCode = [];
            foreach ($dailyRecordsResult as $dr) {
                $dailyRecordsByEmpCode[$dr->emp_code] = [
                    'id' => $dr->id,
                    'date' => $dr->date,
                    'employee_id' => $dr->employee_id,
                    'emp_code' => $dr->emp_code,
                    'concept_id' => $dr->concept_id,
                    'day_code' => $dr->day_code,
                    'mobility_eligible' => $dr->mobility_eligible,
                    'source' => $dr->source,
                    'notes' => $dr->notes,
                    'processed' => $dr->processed,
                    'created_at' => $dr->created_at,
                    'updated_at' => $dr->updated_at
                ];
            }

            $todosUsuarios = DB::connection('pgsql_external')
                ->table('personnel_employee as pe')
                ->join('personnel_department as pd', 'pe.department_id', '=', 'pd.id')
                ->leftJoin('personnel_position as pp', 'pe.position_id', '=', 'pp.id')
                ->select([
                    'pe.id',
                    'pe.emp_code as dni',
                    'pe.first_name as nombre',
                    'pe.last_name as apellido',
                    DB::raw("CONCAT(pe.first_name, ' ', pe.last_name) as nombre_completo"),
                    'pe.department_id',
                    'pd.dept_name as departamento',
                    'pe.position_id',
                    'pp.position_name as posicion',
                    'pe.email',
                    'pe.mobile',
                    'pe.status'
                ])
                ->where('pe.status', 0)
                ->whereIn('pe.department_id', $departamentos)
                ->when($dni, function ($query) use ($dni) {
                    return $query->where('pe.emp_code', $dni);
                })
                ->orderBy('pe.last_name')
                ->orderBy('pe.first_name')
                ->get();

            Log::info('Todos los usuarios del área obtenidos', [
                'count' => count($todosUsuarios)
            ]);

            // 4. Crear índice de usuarios con ruta
            $usuariosConRutaMap = [];
            foreach ($rutasResults as $ruta) {
                $dniRuta = $ruta->dni;
                if (!isset($usuariosConRutaMap[$dniRuta])) {
                    $usuariosConRutaMap[$dniRuta] = [];
                }
                $usuariosConRutaMap[$dniRuta][] = $ruta;
            }

            // 5. Clasificar usuarios con ruta y sin ruta, agregando marcaciones
            $usuariosConRuta = [];
            $usuariosSinRuta = [];

            foreach ($todosUsuarios as $usuario) {
                $dniUsuario = $usuario->dni;
                
                $userData = [
                    'id' => $usuario->id,
                    'dni' => $dniUsuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'nombre_completo' => $usuario->nombre_completo,
                    'department_id' => $usuario->department_id,
                    'departamento' => $usuario->departamento,
                    'position_id' => $usuario->position_id,
                    'posicion' => $usuario->posicion,
                    'email' => $usuario->email,
                    'mobile' => $usuario->mobile,
                    'status' => $usuario->status
                ];

                // Agregar marcaciones
                if (isset($iclockByEmpCode[$dniUsuario])) {
                    $userData['marcaciones'] = $iclockByEmpCode[$dniUsuario];
                } else {
                    $userData['marcaciones'] = ['message' => 'No marcó'];
                }

                // Agregar daily_record
                if (isset($dailyRecordsByEmpCode[$dniUsuario])) {
                    $userData['daily_record'] = $dailyRecordsByEmpCode[$dniUsuario];
                } else {
                    $userData['daily_record'] = null;
                }

                // Clasificar según tenga o no ruta
                if (isset($usuariosConRutaMap[$dniUsuario])) {
                    // Usuario CON ruta
                    $userData['rutas'] = $usuariosConRutaMap[$dniUsuario];
                    $usuariosConRuta[] = $userData;
                } else {
                    // Usuario SIN ruta
                    $userData['rutas'] = [];
                    $usuariosSinRuta[] = $userData;
                }
            }

            return response()->json([
                'success' => true,
                'fecha' => $fecha,
                'dni' => $dni,
                'total_usuarios' => count($todosUsuarios),
                'total_con_ruta' => count($usuariosConRuta),
                'total_sin_ruta' => count($usuariosSinRuta),
                'usuarios_con_ruta' => $usuariosConRuta,
                'usuarios_sin_ruta' => $usuariosSinRuta
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
