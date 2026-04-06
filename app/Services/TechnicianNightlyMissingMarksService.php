<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TechnicianNightlyMissingMarksService
{
    private const MYSQL_CONNECTION = 'mysql_external';

    private const PGSQL_CONNECTION = 'pgsql_external';

    private const DEPARTMENTS = [9, 7, 2, 10, 5];

    private const MISSING_MARK_CONCEPT_ID = 5;

    private const MISSING_MARK_COMMENT = 'Registro automático generado por seguimiento técnico nocturno.';

    public function __construct(
        private readonly EmployeeConceptServiceInterface $employeeConceptService
    ) {}

    public function getUsersWithRouteWithoutMark(string $fecha, ?string $dni = null): array
    {
        $fechaNormalizada = Carbon::parse($fecha)->format('Y-m-d');

        $rutasResults = $this->getRutasResults($fechaNormalizada, $dni);
        $marcacionesByEmpCode = $this->getMarcacionesByEmpCode($fechaNormalizada);
        $dailyRecordsByEmpCode = $this->getDailyRecordsByEmpCode($fechaNormalizada);
        $usuarios = $this->getTechnicians(self::DEPARTMENTS, $dni);

        $usuariosConRutaMap = [];
        foreach ($rutasResults as $ruta) {
            $usuariosConRutaMap[(string) $ruta->dni][] = $ruta;
        }

        $usuariosConRuta = [];
        $usuariosSinRuta = [];
        $usuariosConRutaSinMarcacion = [];

        foreach ($usuarios as $usuario) {
            $dniUsuario = (string) $usuario->dni;
            $tieneRuta = isset($usuariosConRutaMap[$dniUsuario]);
            $tieneMarcacion = isset($marcacionesByEmpCode[$dniUsuario]);

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
                'status' => $usuario->status,
                'marcaciones' => $tieneMarcacion ? $marcacionesByEmpCode[$dniUsuario] : ['message' => 'No marcó'],
                'daily_record' => $dailyRecordsByEmpCode[$dniUsuario] ?? null,
            ];

            if ($tieneRuta) {
                $userData['rutas'] = $usuariosConRutaMap[$dniUsuario];
                $usuariosConRuta[] = $userData;

                if (! $tieneMarcacion) {
                    $usuariosConRutaSinMarcacion[] = $userData;
                }
            } else {
                $userData['rutas'] = [];
                $usuariosSinRuta[] = $userData;
            }
        }

        return [
            'success' => true,
            'fecha' => $fechaNormalizada,
            'dni' => $dni,
            'total_usuarios' => count($usuarios),
            'total_con_ruta' => count($usuariosConRuta),
            'total_sin_ruta' => count($usuariosSinRuta),
            'total_con_ruta_sin_marcacion' => count($usuariosConRutaSinMarcacion),
            'usuarios_con_ruta' => $usuariosConRuta,
            'usuarios_sin_ruta' => $usuariosSinRuta,
            'usuarios_con_ruta_sin_marcacion' => $usuariosConRutaSinMarcacion,
        ];
    }

    public function getPreviousDayNotifications(?string $dni = null): array
    {
        $fecha = Carbon::yesterday('America/Lima')->format('Y-m-d');
        $data = $this->getUsersWithRouteWithoutMark($fecha, $dni);

        $notifications = collect($data['usuarios_con_ruta_sin_marcacion'])
            ->map(function (array $user) use ($fecha): array {
                return [
                    'id' => $user['id'],
                    'employee_id' => $user['id'],
                    'dni' => $user['dni'],
                    'nombre' => $user['nombre'],
                    'apellido' => $user['apellido'],
                    'nombre_completo' => $user['nombre_completo'],
                    'fecha_referencia' => $fecha,
                    'title' => 'Técnico sin marcación',
                    'message' => "{$user['nombre_completo']} tenía ruta y no marcó el {$fecha}.",
                    'selected' => true,
                    'type' => 'technician_missing_mark',
                    'rutas_count' => count($user['rutas'] ?? []),
                    'rutas' => $user['rutas'] ?? [],
                    'daily_record' => $user['daily_record'] ?? null,
                    'source' => 'seguimiento_tecnico',
                ];
            })
            ->values()
            ->all();

        return [
            'success' => true,
            'fecha_referencia' => $fecha,
            'total_notificaciones' => count($notifications),
            'notifications' => $notifications,
            'selected_users' => $notifications,
            'raw' => $data,
        ];
    }

    public function registerMissingConceptForDay(array $user, string $fecha, int $conceptId = self::MISSING_MARK_CONCEPT_ID, ?string $comment = null): array
    {
        $comment ??= self::MISSING_MARK_COMMENT;

        return $this->employeeConceptService->storeConcept(
            (int) $user['id'],
            (string) $user['dni'],
            $conceptId,
            $fecha,
            $fecha,
            $comment
        );
    }

    public function processMissingConcepts(string $fecha, ?string $dni = null): array
    {
        $preview = $this->getUsersWithRouteWithoutMark($fecha, $dni);
        $users = $preview['usuarios_con_ruta_sin_marcacion'];
        $conceptId = self::MISSING_MARK_CONCEPT_ID;
        $comment = self::MISSING_MARK_COMMENT;

        $processed = [];
        $failed = [];

        foreach ($users as $user) {
            try {
                $processed[] = array_merge(
                    $user,
                    $this->registerMissingConceptForDay($user, $preview['fecha'], $conceptId, $comment)
                );
            } catch (\Throwable $e) {
                $failed[] = [
                    'employee_id' => $user['id'] ?? null,
                    'dni' => $user['dni'] ?? null,
                    'error' => $e->getMessage(),
                ];

                Log::error('Error registrando concepto nocturno', [
                    'fecha' => $preview['fecha'],
                    'employee_id' => $user['id'] ?? null,
                    'dni' => $user['dni'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'preview' => $preview,
            'processed_count' => count($processed),
            'failed_count' => count($failed),
            'processed_users' => $processed,
            'failed_users' => $failed,
            'concept_id' => $conceptId,
        ];
    }

    public function processNoRouteMissingConcepts(string $fecha, ?string $dni = null): array
    {
        $preview = $this->getUsersWithRouteWithoutMark($fecha, $dni);
        $users = array_values(array_filter(
            $preview['usuarios_sin_ruta'],
            static fn (array $user): bool => ($user['marcaciones']['message'] ?? null) === 'No marcó'
                && empty($user['daily_record'])
        ));
        $conceptId = 1;
        $comment = 'Registro automático generado por seguimiento técnico nocturno sin ruta.';

        $processed = [];
        $failed = [];

        foreach ($users as $user) {
            try {
                $processed[] = array_merge(
                    $user,
                    $this->registerMissingConceptForDay($user, $preview['fecha'], $conceptId, $comment)
                );
            } catch (\Throwable $e) {
                $failed[] = [
                    'employee_id' => $user['id'] ?? null,
                    'dni' => $user['dni'] ?? null,
                    'error' => $e->getMessage(),
                ];

                Log::error('Error registrando concepto nocturno sin ruta', [
                    'fecha' => $preview['fecha'],
                    'employee_id' => $user['id'] ?? null,
                    'dni' => $user['dni'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'preview' => $preview,
            'processed_count' => count($processed),
            'failed_count' => count($failed),
            'processed_users' => $processed,
            'failed_users' => $failed,
            'concept_id' => $conceptId,
        ];
    }

    private function getRutasResults(string $fecha, ?string $dni = null): array
    {
        try {
            return DB::connection(self::MYSQL_CONNECTION)->select(
                'CALL sp_get_rutas_tecnicos_dia_fecha(?, ?)',
                [$dni, $fecha]
            );
        } catch (\Throwable $e) {
            Log::error('Error consultando rutas técnicas nocturnas', [
                'fecha' => $fecha,
                'dni' => $dni,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('No se pudieron obtener las rutas técnicas.', 0, $e);
        }
    }

    private function getMarcacionesByEmpCode(string $fecha): array
    {
        $inicio = Carbon::parse($fecha, 'America/Lima')->startOfDay()->format('Y-m-d H:i:sP');
        $fin = Carbon::parse($fecha, 'America/Lima')->addDay()->startOfDay()->format('Y-m-d H:i:sP');

        $result = DB::connection(self::PGSQL_CONNECTION)
            ->table('iclock_transaction')
            ->whereRaw('punch_time >= ? AND punch_time < ?', [$inicio, $fin])
            ->where('terminal_sn', 'App')
            ->get();

        $byEmpCode = [];
        foreach ($result as $row) {
            $byEmpCode[(string) $row->emp_code][] = $row;
        }

        return $byEmpCode;
    }

    private function getDailyRecordsByEmpCode(string $fecha): array
    {
        $records = DB::connection(self::PGSQL_CONNECTION)
            ->table('daily_records as dr')
            ->leftJoin('concepts as c', 'dr.concept_id', '=', 'c.id')
            ->select([
                'dr.id',
                'dr.date',
                'dr.employee_id',
                'dr.emp_code',
                'dr.concept_id',
                'dr.day_code',
                'dr.mobility_eligible',
                DB::raw('COALESCE(dr.mobility_eligible, c.affects_mobility, false) as mobility_eligible_resolved'),
                'dr.source',
                'dr.notes',
                'dr.processed',
                'dr.created_at',
                'dr.updated_at',
                'c.code as concept_code',
                'c.name as concept_name',
                'c.affects_mobility as concept_affects_mobility',
            ])
            ->where('dr.date', $fecha)
            ->get();

        $byEmpCode = [];
        foreach ($records as $record) {
            $byEmpCode[(string) $record->emp_code] = [
                'id' => $record->id,
                'date' => $record->date,
                'employee_id' => $record->employee_id,
                'emp_code' => $record->emp_code,
                'concept_id' => $record->concept_id,
                'day_code' => $record->day_code,
                'mobility_eligible' => $record->mobility_eligible,
                'mobility_eligible_resolved' => (bool) ($record->mobility_eligible_resolved ?? false),
                'concept_code' => $record->concept_code,
                'concept_name' => $record->concept_name,
                'concept_affects_mobility' => (bool) ($record->concept_affects_mobility ?? false),
                'source' => $record->source,
                'notes' => $record->notes,
                'processed' => $record->processed,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ];
        }

        return $byEmpCode;
    }

    private function getTechnicians(array $departments, ?string $dni = null): array
    {
        return DB::connection(self::PGSQL_CONNECTION)
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
                'pe.status',
            ])
            ->where('pe.status', 0)
            ->whereIn('pe.department_id', $departments)
            ->when($dni, fn ($query) => $query->where('pe.emp_code', $dni))
            ->orderBy('pe.last_name')
            ->orderBy('pe.first_name')
            ->get()
            ->all();
    }
}
