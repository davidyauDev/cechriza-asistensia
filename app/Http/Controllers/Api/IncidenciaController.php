<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IncidenciaController extends Controller
{
    public function index(Request $request)
    {
        /* =========================
     *  VALIDACIÓN
     * ========================= */
        $request->validate([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2020|max:2030',
        ]);

        $departments = [1, 6, 3, 16, 13, 8];

        /* =========================
     *  MINUTOS BRUTOS (BD)
     * ========================= */
        $brutos = DB::connection('pgsql_external')
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
        ")
            ->whereMonth('clock_in', $request->mes)
            ->whereYear('clock_in', $request->anio)
            ->groupBy('emp_id')
            ->pluck('minutos_bruto', 'emp_id');

        /* =========================
     *  INCIDENCIAS + EMPLEADOS
     * ========================= */
        $rows = DB::connection('pgsql_external')
            ->table('personnel_employee as e')
            ->leftJoin('incidencias as i', function ($join) use ($request) {
                $join->on('i.usuario_id', '=', 'e.id')
                    ->whereMonth('i.fecha', $request->mes)
                    ->whereYear('i.fecha', $request->anio);
            })
            ->whereIn('e.department_id', $departments)
            ->where('e.status', 0)
            ->select([
                'e.id',
                'e.emp_code as dni',
                'e.email',
                'e.last_name as apellidos',
                'e.first_name as nombre',
                'i.fecha',
                'i.minutos',
                'i.tipo',
                'i.motivo',
            ])
            ->orderBy('e.id')
            ->orderBy('i.fecha')
            ->get();

        /* =========================
     *  MAPEO DE TIPOS
     * ========================= */
        $mapaTipos = [
            'DESCANSO_MEDICO'      => 'DM',
            'MINUTOS_JUSTIFICADOS' => 'MF',
            'FALTA'               => 'F',
            'TRABAJO_EN_CAMPO'    => 'TC',
        ];

        /* =========================
     *  PROCESAMIENTO FINAL
     * ========================= */
        $data = $rows->groupBy('id')->map(function ($items) use ($brutos, $mapaTipos) {

            $user = $items->first();
            $dias = [];
            $minutosIncidencias = 0;

            foreach ($items as $row) {

                if (!$row->fecha) {
                    continue;
                }

                $key = Carbon::parse($row->fecha)
                    ->locale('es')
                    ->translatedFormat('j-M');

                $key = preg_replace_callback(
                    '/-(\p{L}+)/u',
                    fn($m) => '-' . ucfirst($m[1]),
                    str_replace('.', '', $key)
                );

                if (!is_null($row->minutos)) {

                    $minutosIncidencias += $row->minutos;

                    $dias[$key] = [
                        'valor'  => sprintf(
                            '%02d:%02d',
                            intdiv($row->minutos, 60),
                            $row->minutos % 60
                        ),
                        'motivo' => $row->motivo,
                    ];
                } elseif (!empty($row->tipo) && isset($mapaTipos[$row->tipo])) {

                    $dias[$key] = [
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

                // BRUTO
                'bruto_minutos' => $minutosBruto,
                'bruto_hhmm' => sprintf('%02d:%02d', intdiv($minutosBruto, 60), $minutosBruto % 60),

                // INCIDENCIAS
                'incidencias_minutos' => $minutosIncidencias,
                'incidencias_hhmm' => sprintf('%02d:%02d', intdiv($minutosIncidencias, 60), $minutosIncidencias % 60),

                // NETO
                'neto_minutos' => $minutosNeto,
                'neto_hhmm' => sprintf('%02d:%02d', intdiv($minutosNeto, 60), $minutosNeto % 60),

                // CALENDARIO
                'dias' => (object) $dias,
            ];
        })->values();

        return response()->json($data);
    }
    public function store(Request $request)
    {
        $request->validate([
            'ID_Marcacion' => 'required',
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
    }
}
