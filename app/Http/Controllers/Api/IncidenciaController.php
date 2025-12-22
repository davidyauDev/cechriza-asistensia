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
    $request->validate([
        'mes'  => 'required|integer|min:1|max:12',
        'anio' => 'required|integer|min:2020|max:2030',
    ]);

    $departments = [1, 6, 3, 16, 13, 8];

    $rows = DB::connection('pgsql_external')
        ->table('personnel_employee as e')
        ->leftJoin('incidencias as i', function ($join) use ($request) {
            $join->on('i.usuario_id', '=', 'e.id')
                 ->whereMonth('i.fecha', $request->mes)
                 ->whereYear('i.fecha', $request->anio);
        })
        ->whereIn('e.department_id', $departments)
        ->select(
            'e.id',
            'e.emp_code as dni',
            'e.last_name as apellidos',
            'e.first_name as nombre',
            'i.fecha',
            'i.minutos'
        )
        ->orderBy('e.id')
        ->orderBy('i.fecha')
        ->get();

    $data = $rows->groupBy('id')->map(function ($items) {
        $user = $items->first();
        $dias = [];

        foreach ($items as $row) {
            if ($row->fecha && $row->minutos) {
                $key = Carbon::parse($row->fecha)
                    ->locale('es')
                    ->translatedFormat('j-M');
                
                $key = preg_replace_callback(
                    '/-(\p{L}+)/u',
                    fn ($m) => '-' . ucfirst($m[1]),
                    str_replace('.', '', $key)
                );

                $horas = intdiv($row->minutos, 60);
                $mins  = $row->minutos % 60;

                $dias[$key] = sprintf('%02d:%02d', $horas, $mins);
            }
        }

        return [
            'id'        => $user->id,
            'dni'       => $user->dni,
            'apellidos' => $user->apellidos,
            'nombre'    => $user->nombre,
            'dias'      => (object) $dias,
        ];
    })->values();

    return response()->json($data);
}




    public function store(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
            'fecha'      => 'required|date',
            'minutos'    => 'required|integer|min:1',
            'motivo'     => 'required|string|max:255',
        ]);

        DB::connection('pgsql_external')
            ->table('incidencias')
            ->insert([
                'usuario_id' => $request->usuario_id,
                'fecha'      => $request->fecha,
                'minutos'    => $request->minutos,
                'motivo'     => $request->motivo,
                'created_at' => now(),
            ]);

        return response()->json([
            'message' => 'Incidencia registrada correctamente'
        ], 201);
    }
}
