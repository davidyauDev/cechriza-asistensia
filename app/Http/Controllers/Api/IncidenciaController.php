<?php

namespace App\Http\Controllers\Api;

use App\Exports\IncidenciasExport;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class IncidenciaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2020|max:2030',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'descargar' => 'nullable|boolean',
        ]);

        $usarRango = $request->filled('fecha_desde') && $request->filled('fecha_hasta');
        $usaDuracionSegundos = $this->tieneColumnaDuracionSegundos();
        $usaImagenPath = $this->tieneColumnaImagenPath();
        $departments = [1, 6, 3, 11, 12, 14, 15, 16 , 17, 13, 8];

        $brutosQuery = DB::connection('pgsql_external')
            ->table('att_payloadbase')
            ->selectRaw('
                emp_id,
                COALESCE(SUM(
                    CASE
                        WHEN clock_in > check_in
                        THEN EXTRACT(EPOCH FROM (clock_in - check_in))
                        ELSE 0
                    END
                ), 0) AS duracion_bruto_segundos
            ');

        if ($usarRango) {
            $brutosQuery
                ->whereDate('clock_in', '>=', $request->fecha_desde)
                ->whereDate('clock_in', '<=', $request->fecha_hasta);
        } else {
            $brutosQuery
                ->whereMonth('clock_in', $request->mes)
                ->whereYear('clock_in', $request->anio);
        }

        $brutos = $brutosQuery
            ->groupBy('emp_id')
            ->pluck('duracion_bruto_segundos', 'emp_id');

        $rowsQuery = DB::connection('pgsql_external')
            ->table('personnel_employee as e')
            ->leftJoin('personnel_department as pd', 'e.department_id', '=', 'pd.id')
            ->leftJoin('personnel_company as pc', 'pd.company_id', '=', 'pc.id')
            ->leftJoin('incidencias as i', function ($join) use ($request, $usarRango) {
                $join->on('i.usuario_id', '=', 'e.id');
                $join->where('i.es_recordatorio', false);

                if ($usarRango) {
                    $join
                        ->whereDate('i.fecha', '>=', $request->fecha_desde)
                        ->whereDate('i.fecha', '<=', $request->fecha_hasta);
                } else {
                    $join
                        ->whereMonth('i.fecha', $request->mes)
                        ->whereYear('i.fecha', $request->anio);
                }
            })
            ->leftJoin('personnel_employee as creador', 'i.creado_por', '=', 'creador.id')
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
                'i.es_recordatorio',
                'i.created_at',
                'creador.first_name as creador_nombre',
                'creador.last_name as creador_apellido',
            ])
            ->orderByRaw("CASE pc.company_name WHEN 'Cechriza SAC' THEN 0 WHEN 'Ydieza SAC' THEN 1 ELSE 2 END")
            ->orderBy('pd.dept_name')
            ->orderBy('e.last_name')
            ->orderBy('e.first_name')
            ->orderBy('e.id')
            ->orderBy('i.fecha');

        $rowsQuery->addSelect(DB::raw(
            $usaDuracionSegundos
                ? 'i.duracion_segundos'
                : '(i.minutos * 60) as duracion_segundos'
        ));

        $rowsQuery->addSelect(DB::raw(
            $usaImagenPath
                ? 'i.imagen_path'
                : 'NULL as imagen_path'
        ));

        $rows = $rowsQuery->get();

        $mapaTipos = [
            'DESCANSO_MEDICO' => 'DM',
            'MINUTOS_JUSTIFICADOS' => 'MF',
            'FALTA' => 'F',
            'TRABAJO_EN_CAMPO' => 'TC',
        ];

        $data = $rows->groupBy('id')->map(function ($items) use ($brutos, $mapaTipos, $usarRango, $request) {
            $user = $items->first();
            $dias = [];
            $segundosIncidencias = 0;

            $fechaDesde = $usarRango ? Carbon::parse($request->fecha_desde)->startOfDay() : null;
            $fechaHasta = $usarRango ? Carbon::parse($request->fecha_hasta)->endOfDay() : null;

            foreach ($items as $row) {
                if (! $row->fecha) {
                    continue;
                }

                $fechaRow = Carbon::parse($row->fecha);
                if ($usarRango && ($fechaRow->lt($fechaDesde) || $fechaRow->gt($fechaHasta))) {
                    continue;
                }

                $key = $fechaRow->locale('es')->translatedFormat('j-M');
                $key = preg_replace_callback(
                    '/-(\p{L}+)/u',
                    fn ($match) => '-'.ucfirst($match[1]),
                    str_replace('.', '', $key)
                );

                $duracionSegundos = is_null($row->duracion_segundos) ? null : (int) $row->duracion_segundos;
                $baseDia = [
                    'id' => $row->incidencia_id,
                    'motivo' => $row->motivo,
                    'imagen_path' => $row->imagen_path,
                    'imagen_url' => $this->resolverImagenUrl($row->imagen_path),
                    'es_recordatorio' => (bool) $row->es_recordatorio,
                    'created_at' => $row->created_at,
                    'creado_por' => trim(($row->creador_nombre ?? '').' '.($row->creador_apellido ?? '')),
                ];

                if (! is_null($duracionSegundos)) {
                    $segundosIncidencias += $duracionSegundos;
                    $dias[$key] = array_merge($baseDia, [
                        'valor' => $this->formatearSegundos($duracionSegundos),
                        'minutos' => is_null($row->minutos) ? intdiv($duracionSegundos, 60) : (int) $row->minutos,
                        'segundos' => $duracionSegundos,
                    ]);

                    continue;
                }

                if (! empty($row->tipo) && isset($mapaTipos[$row->tipo])) {
                    $dias[$key] = array_merge($baseDia, [
                        'valor' => $mapaTipos[$row->tipo],
                    ]);
                }
            }

            $segundosBruto = (int) round($brutos[$user->id] ?? 0);
            $segundosNeto = max(0, $segundosBruto - $segundosIncidencias);
            $minutosBruto = intdiv($segundosBruto, 60);
            $minutosIncidencias = intdiv($segundosIncidencias, 60);
            $minutosNeto = intdiv($segundosNeto, 60);

            return [
                'id' => $user->id,
                'dni' => $user->dni,
                'apellidos' => $user->apellidos,
                'nombre' => $user->nombre,
                'email' => $user->email,
                'departamento' => $user->departamento,
                'empresa' => $user->empresa,
                'bruto_segundos' => $segundosBruto,
                'bruto_minutos' => $minutosBruto,
                'bruto_hhmm' => $this->formatearMinutos($segundosBruto),
                'bruto_hhmmss' => $this->formatearSegundos($segundosBruto),
                'incidencias_segundos' => $segundosIncidencias,
                'incidencias_minutos' => $minutosIncidencias,
                'incidencias_hhmm' => $this->formatearMinutos($segundosIncidencias),
                'incidencias_hhmmss' => $this->formatearSegundos($segundosIncidencias),
                'neto_segundos' => $segundosNeto,
                'neto_minutos' => $minutosNeto,
                'neto_hhmm' => $this->formatearMinutos($segundosNeto),
                'neto_hhmmss' => $this->formatearSegundos($segundosNeto),
                'dias' => (object) $dias,
            ];
        })->values();

        if ($request->descargar) {
            $diasDelMes = [];
            $domingos = [];

            if ($usarRango) {
                $fechaInicio = Carbon::parse($request->fecha_desde);
                $fechaFin = Carbon::parse($request->fecha_hasta);

                for ($fecha = $fechaInicio->copy(); $fecha->lte($fechaFin); $fecha->addDay()) {
                    $key = $fecha->locale('es')->translatedFormat('j-M');
                    $key = preg_replace_callback(
                        '/-(\p{L}+)/u',
                        fn ($match) => '-'.ucfirst($match[1]),
                        str_replace('.', '', $key)
                    );

                    $diasDelMes[] = $key;
                    if ($fecha->isSunday()) {
                        $domingos[] = $key;
                    }
                }

                $nombreArchivo = "Incidencias_{$fechaInicio->format('d-m-Y')}_a_{$fechaFin->format('d-m-Y')}.xlsx";
            } else {
                $ultimoDia = Carbon::create($request->anio, $request->mes, 1)->endOfMonth()->day;

                for ($dia = 1; $dia <= $ultimoDia; $dia++) {
                    $fecha = Carbon::create($request->anio, $request->mes, $dia);
                    $key = $fecha->locale('es')->translatedFormat('j-M');
                    $key = preg_replace_callback(
                        '/-(\p{L}+)/u',
                        fn ($match) => '-'.ucfirst($match[1]),
                        str_replace('.', '', $key)
                    );

                    $diasDelMes[] = $key;
                    if ($fecha->isSunday()) {
                        $domingos[] = $key;
                    }
                }

                $nombreMes = Carbon::create($request->anio, $request->mes, 1)
                    ->locale('es')
                    ->translatedFormat('F');
                $nombreArchivo = "Incidencias_{$nombreMes}_{$request->anio}.xlsx";
            }

            return Excel::download(
                new IncidenciasExport($data->toArray(), $diasDelMes, $domingos),
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
                'fecha' => 'required|date',
                'tipo' => 'required|string|max:100',
                'minutos' => 'nullable|integer|min:0',
                'segundos' => 'nullable|integer|min:0|max:59',
                'duracion_segundos' => 'nullable|integer|min:1',
                'motivo' => 'required|string|max:255',
                'es_recordatorio' => 'nullable|boolean',
                'imagen' => $this->reglasImagen(),
                'photo' => $this->reglasImagen(),
            ]);

            $imagen = $this->obtenerArchivoImagen($request);
            if ($imagen && ! $this->tieneColumnaImagenPath()) {
                throw ValidationException::withMessages([
                    'imagen' => 'Debes agregar la columna imagen_path en la tabla incidencias antes de subir imagenes.',
                ]);
            }

            $tiposSinMinutos = [
                'DESCANSO_MEDICO',
                'FALTA_JUSTIFICADA',
            ];

            $duracionSegundos = $this->resolverDuracionSegundos($request, $tiposSinMinutos);
            $minutos = is_null($duracionSegundos)
                ? null
                : intdiv($duracionSegundos, 60);
            $esRecordatorio = (bool) $request->input('es_recordatorio', false);

            $imagenPath = $imagen ? $this->guardarImagen($imagen) : null;

            DB::connection('pgsql_external')->beginTransaction();

            try {
                $payload = [
                    'usuario_id' => $request->usuario_id,
                    'creado_por' => $request->creado_por,
                    'fecha' => $request->fecha,
                    'tipo' => $request->tipo,
                    'minutos' => $minutos,
                    'motivo' => $request->motivo,
                    'es_recordatorio' => $esRecordatorio,
                    'created_at' => now(),
                ];

                if ($this->tieneColumnaDuracionSegundos()) {
                    $payload['duracion_segundos'] = $duracionSegundos;
                }

                if ($this->tieneColumnaImagenPath()) {
                    $payload['imagen_path'] = $imagenPath;
                }

                $incidenciaId = DB::connection('pgsql_external')
                    ->table('incidencias')
                    ->insertGetId($payload);

                if (! $esRecordatorio && ! is_null($request->ID_Marcacion)) {
                    DB::connection('pgsql_external')
                        ->table('iclock_transaction')
                        ->where('id', $request->ID_Marcacion)
                        ->update(['tiene_incidencia' => true]);
                }

                DB::connection('pgsql_external')->commit();
            } catch (\Throwable $e) {
                DB::connection('pgsql_external')->rollBack();
                if ($imagenPath) {
                    $this->eliminarImagen($imagenPath);
                }
                throw $e;
            }

            return response()->json([
                'message' => 'Incidencia registrada correctamente',
                'incidencia' => $this->transformarIncidencia($this->buscarIncidencia($incidenciaId)),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al registrar la incidencia',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'creado_por' => 'sometimes|integer',
                'usuario_id' => 'sometimes|integer',
                'fecha' => 'sometimes|date',
                'tipo' => 'sometimes|string|max:100',
                'minutos' => 'nullable|integer|min:0',
                'segundos' => 'nullable|integer|min:0|max:59',
                'duracion_segundos' => 'nullable|integer|min:1',
                'motivo' => 'sometimes|required|string|max:255',
                'es_recordatorio' => 'nullable|boolean',
                'eliminar_imagen' => 'nullable|boolean',
                'imagen' => $this->reglasImagen(),
                'photo' => $this->reglasImagen(),
            ]);

            $incidencia = $this->buscarIncidencia($id);
            if (! $incidencia) {
                return response()->json([
                    'message' => 'Incidencia no encontrada',
                ], 404);
            }

            $imagen = $this->obtenerArchivoImagen($request);
            if ($imagen && ! $this->tieneColumnaImagenPath()) {
                throw ValidationException::withMessages([
                    'imagen' => 'Debes agregar la columna imagen_path en la tabla incidencias antes de subir imagenes.',
                ]);
            }

            $tiposSinMinutos = [
                'DESCANSO_MEDICO',
                'FALTA_JUSTIFICADA',
            ];

            $duracionSegundos = $this->resolverDuracionSegundos($request, $tiposSinMinutos, $incidencia);
            $minutos = is_null($duracionSegundos)
                ? null
                : intdiv($duracionSegundos, 60);

            $eliminarImagen = $request->boolean('eliminar_imagen');
            $imagenAnterior = $this->tieneColumnaImagenPath() ? ($incidencia->imagen_path ?? null) : null;
            $nuevaImagenPath = $imagen ? $this->guardarImagen($imagen) : null;

            DB::connection('pgsql_external')->beginTransaction();

            try {
                $payload = [
                    'updated_at' => now(),
                ];

                if ($request->has('creado_por')) {
                    $payload['creado_por'] = $request->creado_por;
                }
                if ($request->has('usuario_id')) {
                    $payload['usuario_id'] = $request->usuario_id;
                }
                if ($request->has('fecha')) {
                    $payload['fecha'] = $request->fecha;
                }
                if ($request->has('tipo')) {
                    $payload['tipo'] = $request->tipo;
                }
                if ($request->has('motivo')) {
                    $payload['motivo'] = $request->motivo;
                }
                if ($request->has('es_recordatorio')) {
                    $payload['es_recordatorio'] = (bool) $request->input('es_recordatorio');
                }

                $payload['minutos'] = $minutos;

                if ($this->tieneColumnaDuracionSegundos()) {
                    $payload['duracion_segundos'] = $duracionSegundos;
                }

                if ($this->tieneColumnaImagenPath()) {
                    if ($nuevaImagenPath) {
                        $payload['imagen_path'] = $nuevaImagenPath;
                    } elseif ($eliminarImagen) {
                        $payload['imagen_path'] = null;
                    }
                }

                DB::connection('pgsql_external')
                    ->table('incidencias')
                    ->where('id', $id)
                    ->update($payload);

                DB::connection('pgsql_external')->commit();
            } catch (\Throwable $e) {
                DB::connection('pgsql_external')->rollBack();
                if ($nuevaImagenPath) {
                    $this->eliminarImagen($nuevaImagenPath);
                }
                throw $e;
            }

            if (($nuevaImagenPath || $eliminarImagen) && $imagenAnterior && $imagenAnterior !== $nuevaImagenPath) {
                $this->eliminarImagen($imagenAnterior);
            }

            return response()->json([
                'message' => 'Incidencia actualizada correctamente',
                'incidencia' => $this->transformarIncidencia($this->buscarIncidencia($id)),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar la incidencia',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $incidencia = $this->buscarIncidencia($id);
            if (! $incidencia) {
                return response()->json([
                    'message' => 'Incidencia no encontrada',
                ], 404);
            }

            DB::connection('pgsql_external')->beginTransaction();

            try {
                DB::connection('pgsql_external')
                    ->table('incidencias')
                    ->where('id', $id)
                    ->delete();

                DB::connection('pgsql_external')->commit();
            } catch (\Throwable $e) {
                DB::connection('pgsql_external')->rollBack();
                throw $e;
            }

            if ($this->tieneColumnaImagenPath() && ! empty($incidencia->imagen_path)) {
                $this->eliminarImagen($incidencia->imagen_path);
            }

            return response()->json([
                'message' => 'Incidencia eliminada correctamente',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar la incidencia',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buscarIncidencia(int|string $id): ?object
    {
        $query = DB::connection('pgsql_external')
            ->table('incidencias')
            ->where('id', $id)
            ->select([
                'id',
                'usuario_id',
                'creado_por',
                'fecha',
                'tipo',
                'minutos',
                'motivo',
                'es_recordatorio',
                'created_at',
                'updated_at',
            ]);

        if ($this->tieneColumnaDuracionSegundos()) {
            $query->addSelect('duracion_segundos');
        } else {
            $query->addSelect(DB::raw('(minutos * 60) as duracion_segundos'));
        }

        if ($this->tieneColumnaImagenPath()) {
            $query->addSelect('imagen_path');
        } else {
            $query->addSelect(DB::raw('NULL as imagen_path'));
        }

        return $query->first();
    }

    private function transformarIncidencia(?object $incidencia): ?array
    {
        if (! $incidencia) {
            return null;
        }

        return [
            'id' => $incidencia->id,
            'usuario_id' => $incidencia->usuario_id,
            'creado_por' => $incidencia->creado_por,
            'fecha' => $incidencia->fecha,
            'tipo' => $incidencia->tipo,
            'minutos' => $incidencia->minutos,
            'duracion_segundos' => $incidencia->duracion_segundos,
            'motivo' => $incidencia->motivo,
            'es_recordatorio' => (bool) ($incidencia->es_recordatorio ?? false),
            'imagen_path' => $incidencia->imagen_path,
            'imagen_url' => $this->resolverImagenUrl($incidencia->imagen_path),
            'created_at' => $incidencia->created_at,
            'updated_at' => $incidencia->updated_at,
        ];
    }

    private function tieneColumnaDuracionSegundos(): bool
    {
        static $tieneColumna = null;

        if ($tieneColumna !== null) {
            return $tieneColumna;
        }

        return $tieneColumna = Schema::connection('pgsql_external')
            ->hasColumn('incidencias', 'duracion_segundos');
    }

    private function tieneColumnaImagenPath(): bool
    {
        static $tieneColumna = null;

        if ($tieneColumna !== null) {
            return $tieneColumna;
        }

        return $tieneColumna = Schema::connection('pgsql_external')
            ->hasColumn('incidencias', 'imagen_path');
    }

    private function resolverDuracionSegundos(Request $request, array $tiposSinMinutos, ?object $incidencia = null): ?int
    {
        $tipo = $request->input('tipo', $incidencia->tipo ?? null);

        if (in_array($tipo, $tiposSinMinutos, true)) {
            return null;
        }

        if ($request->filled('duracion_segundos')) {
            $duracionSegundos = (int) $request->input('duracion_segundos');
        } elseif ($request->has('minutos') || $request->has('segundos')) {
            $duracionSegundos = ((int) $request->input('minutos', 0) * 60)
                + (int) $request->input('segundos', 0);
        } elseif ($incidencia && isset($incidencia->duracion_segundos) && ! is_null($incidencia->duracion_segundos)) {
            $duracionSegundos = (int) $incidencia->duracion_segundos;
        } elseif ($incidencia && ! is_null($incidencia->minutos)) {
            $duracionSegundos = (int) $incidencia->minutos * 60;
        } else {
            return null;
        }

        if ($duracionSegundos <= 0) {
            throw ValidationException::withMessages([
                'duracion_segundos' => 'La duracion debe ser mayor a 0 segundos.',
            ]);
        }

        return $duracionSegundos;
    }

    private function obtenerArchivoImagen(Request $request): ?UploadedFile
    {
        if ($request->hasFile('imagen')) {
            return $request->file('imagen');
        }

        if ($request->hasFile('photo')) {
            return $request->file('photo');
        }

        return null;
    }

    private function reglasImagen(): array
    {
        return [
            'nullable',
            'file',
            'max:5120',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = strtolower($value->getClientOriginalExtension());
                $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

                if (! in_array($extension, $permitidas, true)) {
                    $fail("El campo {$attribute} debe ser un archivo JPG, JPEG, PNG o WEBP.");
                }
            },
        ];
    }

    private function guardarImagen(UploadedFile $imagen): string
    {
        $extension = strtolower($imagen->getClientOriginalExtension() ?: 'jpg');
        $nombreArchivo = now()->format('YmdHis').'_'.Str::uuid().'.'.$extension;
        $directorio = storage_path('app/public/incidencias');

        if (! is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }

        $imagen->move($directorio, $nombreArchivo);

        return 'incidencias/'.$nombreArchivo;
    }

    private function eliminarImagen(?string $imagenPath): void
    {
        if (! $imagenPath) {
            return;
        }

        $rutaCompleta = storage_path('app/public/'.ltrim($imagenPath, '/'));

        if (is_file($rutaCompleta)) {
            unlink($rutaCompleta);
        }
    }

    private function resolverImagenUrl(?string $imagenPath): ?string
    {
        return $imagenPath ? ImageHelper::getFullImageUrl($imagenPath) : null;
    }

    private function formatearSegundos(int $segundos): string
    {
        $segundos = max(0, $segundos);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($segundos, 3600),
            intdiv($segundos % 3600, 60),
            $segundos % 60
        );
    }

    private function formatearMinutos(int $segundos): string
    {
        $minutosTotales = intdiv(max(0, $segundos), 60);

        return sprintf('%02d:%02d', intdiv($minutosTotales, 60), $minutosTotales % 60);
    }
}
