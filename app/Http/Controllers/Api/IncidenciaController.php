<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\IncidenciasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class IncidenciaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'mes'  => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2020|max:2030',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'descargar' => 'nullable|boolean',
        ]);
        $usarRango = $request->filled('fecha_desde') && $request->filled('fecha_hasta');
        $departments = [1, 6, 3,11 , 12 , 14, 15 , 16, 13, 8];
        $brutosQuery = DB::connection('pgsql_external')
            ->table('att_payloadbase')
            ->selectRaw("
                emp_id,
                COALESCE(SUM(
                    CASE
                        WHEN clock_in > check_in
                        THEN EXTRACT(EPOCH FROM (clock_in - check_in)) / 60
                        ELSE 0
                    END
                ), 0) AS minutos_bruto
            ");

        if ($usarRango) {
            $brutosQuery->whereDate('clock_in', '>=', $request->fecha_desde)
                ->whereDate('clock_in', '<=', $request->fecha_hasta);
        } else {
            $brutosQuery->whereMonth('clock_in', $request->mes)
                ->whereYear('clock_in', $request->anio);
        }
        $brutos = $brutosQuery->groupBy('emp_id')->pluck('minutos_bruto', 'emp_id');
        $rowsQuery = DB::connection('pgsql_external')
            ->table('personnel_employee as e')
            ->leftJoin('personnel_department as pd', 'e.department_id', '=', 'pd.id')
            ->leftJoin('personnel_company as pc', 'pd.company_id', '=', 'pc.id')
            ->leftJoin('incidencias as i', function ($join) use ($request, $usarRango) {
                $join->on('i.usuario_id', '=', 'e.id');
                if ($usarRango) {
                    $join->whereDate('i.fecha', '>=', $request->fecha_desde)
                        ->whereDate('i.fecha', '<=', $request->fecha_hasta);
                } else {
                    $join->whereMonth('i.fecha', $request->mes)
                        ->whereYear('i.fecha', $request->anio);
                }
            })
            ->whereIn('e.department_id', $departments)
            ->where('e.status', 0)
            ->select([
                'e.id',
                'e.emp_code as dni',
                'e.email',
                'e.last_name as apellidos',
                'e.first_name as nombre',
                'pd.dept_name as departamento',
                'pc.company_name as empresa',
                'i.id as incidencia_id',
                'i.fecha',
                'i.minutos',
                'i.tipo',
                'i.motivo',
            ])
            ->orderBy('e.id')
            ->orderBy('i.fecha');

        $rows = $rowsQuery->get();


        $mapaTipos = [
            'DESCANSO_MEDICO'      => 'DM',
            'MINUTOS_JUSTIFICADOS' => 'MF',
            'FALTA'               => 'F',
            'TRABAJO_EN_CAMPO'    => 'TC',
        ];


        $data = $rows->groupBy('id')->map(function ($items) use ($brutos, $mapaTipos, $usarRango, $request) {
            $user = $items->first();
            $dias = [];
            $minutosIncidencias = 0;

            // Si se usa rango, limitar los días a ese rango
            $fechaDesde = $usarRango ? Carbon::parse($request->fecha_desde)->startOfDay() : null;
            $fechaHasta = $usarRango ? Carbon::parse($request->fecha_hasta)->endOfDay() : null;

            foreach ($items as $row) {
                if (!$row->fecha) {
                    continue;
                }
                $fechaRow = Carbon::parse($row->fecha);
                if ($usarRango && ($fechaRow->lt($fechaDesde) || $fechaRow->gt($fechaHasta))) {
                    continue;
                }
                $key = $fechaRow->locale('es')->translatedFormat('j-M');
                $key = preg_replace_callback(
                    '/-(\p{L}+)/u',
                    fn($m) => '-' . ucfirst($m[1]),
                    str_replace('.', '', $key)
                );
                if (!is_null($row->minutos)) {
                    $minutosIncidencias += $row->minutos;
                    $dias[$key] = [
                        'id'     => $row->incidencia_id,
                        'valor'  => sprintf('%02d:%02d', intdiv($row->minutos, 60), $row->minutos % 60),
                        'motivo' => $row->motivo,
                    ];
                } elseif (!empty($row->tipo) && isset($mapaTipos[$row->tipo])) {
                    $dias[$key] = [
                        'id'     => $row->incidencia_id,
                        'valor'  => $mapaTipos[$row->tipo],
                        'motivo' => $row->motivo,
                    ];
                }
            }

            $minutosBruto = (int) ($brutos[$user->id] ?? 0);
            $minutosNeto  = max(0, $minutosBruto - $minutosIncidencias);

            return [
                'id' => $user->id,
                'dni' => $user->dni,
                'apellidos' => $user->apellidos,
                'nombre' => $user->nombre,
                'email' => $user->email,
                'departamento' => $user->departamento,
                'empresa' => $user->empresa,
                'bruto_minutos' => $minutosBruto,
                'bruto_hhmm' => sprintf('%02d:%02d', intdiv($minutosBruto, 60), $minutosBruto % 60),
                'incidencias_minutos' => $minutosIncidencias,
                'incidencias_hhmm' => sprintf('%02d:%02d', intdiv($minutosIncidencias, 60), $minutosIncidencias % 60),
                'neto_minutos' => $minutosNeto,
                'neto_hhmm' => sprintf('%02d:%02d', intdiv($minutosNeto, 60), $minutosNeto % 60),
                'dias' => (object) $dias,
            ];
        })->values();

        if ($request->descargar) {
            $diasDelMes = [];
            if ($usarRango) {
                $fechaInicio = Carbon::parse($request->fecha_desde);
                $fechaFin = Carbon::parse($request->fecha_hasta);
                for ($fecha = $fechaInicio->copy(); $fecha->lte($fechaFin); $fecha->addDay()) {
                    $key = $fecha->locale('es')->translatedFormat('j-M');
                    $key = preg_replace_callback(
                        '/-(\p{L}+)/u',
                        fn($m) => '-' . ucfirst($m[1]),
                        str_replace('.', '', $key)
                    );
                    $diasDelMes[] = $key;
                }
                $nombreArchivo = "Incidencias_{$fechaInicio->format('d-m-Y')}_a_{$fechaFin->format('d-m-Y')}.xlsx";
            } else {
                $ultimoDia = Carbon::create($request->anio, $request->mes, 1)->endOfMonth()->day;
                for ($dia = 1; $dia <= $ultimoDia; $dia++) {
                    $fecha = Carbon::create($request->anio, $request->mes, $dia);
                    $key = $fecha->locale('es')->translatedFormat('j-M');
                    $key = preg_replace_callback(
                        '/-(\p{L}+)/u',
                        fn($m) => '-' . ucfirst($m[1]),
                        str_replace('.', '', $key)
                    );
                    $diasDelMes[] = $key;
                }
                $nombreMes = Carbon::create($request->anio, $request->mes, 1)
                    ->locale('es')
                    ->translatedFormat('F');
                $nombreArchivo = "Incidencias_{$nombreMes}_{$request->anio}.xlsx";
            }

            return Excel::download(
                new IncidenciasExport($data->toArray(), $diasDelMes),
                $nombreArchivo
            );
        }

        return response()->json($data);
    }
    public function store(Request $request)
    {
        try {
            $request->validate([
                'ID_Marcacion' => 'nullable',
                'creado_por' => 'required|integer',
                'usuario_id' => 'required|integer',
                'fecha'      => 'required|date',
                'tipo'       => 'required|string',
                'minutos'    => 'nullable|integer|min:1',
                'motivo'     => 'required|string|max:255',

            ]);

            $tiposSinMinutos = [
                'DESCANSO_MEDICO',
                'FALTA_JUSTIFICADA',
            ];

            $minutos = in_array($request->tipo, $tiposSinMinutos)
                ? null
                : $request->minutos;

            DB::connection('pgsql_external')
                ->table('incidencias')
                ->insert([
                    'usuario_id' => $request->usuario_id,
                    'fecha'      => $request->fecha,
                    'tipo'       => $request->tipo,
                    'minutos'    => $minutos,
                    'motivo'     => $request->motivo,
                    'created_at' => now(),
                ]);

            DB::connection('pgsql_external')
                ->table('iclock_transaction')
                ->where('id', $request->ID_Marcacion)
                ->update(['tiene_incidencia' => true]);

            return response()->json([
                'message' => 'Incidencia registrada correctamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la incidencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'fecha'      => 'nullable|date',
            'tipo'       => 'nullable|string',
            'minutos'    => 'nullable|integer|min:1',
            'motivo'     => 'required|string|max:255',
        ]);

        // Verificar que la incidencia existe
        $incidencia = DB::connection('pgsql_external')
            ->table('incidencias')
            ->where('id', $id)
            ->first();

        if (!$incidencia) {
            return response()->json([
                'message' => 'Incidencia no encontrada'
            ], 404);
        }

        $tiposSinMinutos = [
            'DESCANSO_MEDICO',
            'FALTA_JUSTIFICADA',
        ];

        $minutos = in_array($request->tipo, $tiposSinMinutos)
            ? null
            : $request->minutos;

        DB::connection('pgsql_external')
            ->table('incidencias')
            ->where('id', $id)
            ->update([
                //'fecha'      => $request->fecha,
                //'tipo'       => $request->tipo,
                'minutos'    => $minutos,
                'motivo'     => $request->motivo,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Incidencia actualizada correctamente'
        ], 200);
    }

    public function destroy($id)
    {
        // Verificar que la incidencia existe
        $incidencia = DB::connection('pgsql_external')
            ->table('incidencias')
            ->where('id', $id)
            ->first();

        if (!$incidencia) {
            return response()->json([
                'message' => 'Incidencia no encontrada'
            ], 404);
        }

        // Eliminar la incidencia
        DB::connection('pgsql_external')
            ->table('incidencias')
            ->where('id', $id)
            ->delete();

        return response()->json([
            'message' => 'Incidencia eliminada correctamente'
        ], 200);
    }
}
