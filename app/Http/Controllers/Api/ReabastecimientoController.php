<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReabastecimientoController extends Controller
{
    use ApiResponseTrait;

    private const AREA_ID = 11;

    private const TAB_STATE_IDS = [
        'pendientes' => [1,9],
        'procesando' => [4],
        'cerrados' => [ 7],
    ];

    private const STATE_META = [
        7 => [
            'key' => 'pendiente_aprobacion_compras',
            'label' => 'Pendiente Aprobacion Compras',
            'color' => 'green',
            'tab' => 'pendientes',
        ],
        6 => [
            'key' => 'procesando',
            'label' => 'Procesando',
            'color' => 'blue',
            'tab' => 'procesando',
        ],
        1 => [
            'key' => 'cerrado',
            'label' => 'Cerrado',
            'color' => 'gray',
            'tab' => 'cerrados',
        ],
        9 => [
            'key' => 'cerrado',
            'label' => 'Cerrado',
            'color' => 'gray',
            'tab' => 'cerrados',
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => 'nullable|string|in:pendientes,procesando,cerrados,todos,all',
            'search' => 'nullable|string|max:255',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            return $this->successResponse(
                $this->buildIndexPayload($validated),
                'Solicitudes de reabastecimiento consultadas correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron consultar las solicitudes de reabastecimiento.', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $payload = $this->buildShowPayload($id);

            if (! $payload) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            return $this->successResponse(
                $payload,
                'Solicitud de reabastecimiento consultada correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el detalle de la solicitud.', 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_usuario_solicitante' => 'nullable|integer',
            'id_area_solicitante' => 'nullable|integer',
            'id_estado_general' => 'nullable|integer',
            'justificacion' => 'required|string|max:1000',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|integer|min:1',
        ]);

        try {
            $usuarioId = $validated['id_usuario_solicitante'] ?? $request->user()?->id;
            $areaId = $validated['id_area_solicitante'] ?? data_get($request->user(), 'department_id');

            if (! $usuarioId || ! $areaId) {
                return $this->errorResponse(
                    'No se pudo resolver el usuario o el área solicitante.',
                    422
                );
            }

            $connection = $this->getConnection();
            $result = $connection->transaction(function () use ($connection, $validated, $usuarioId, $areaId) {
                $solicitudId = $connection->table('solicitudes_reabastecimiento')->insertGetId([
                    'id_usuario_solicitante' => $usuarioId,
                    'id_area_solicitante' => $areaId,
                    'id_estado_general' => $validated['id_estado_general'] ?? 1,
                    'fecha_creacion' => now(),
                    'justificacion' => $validated['justificacion'],
                ]);

                $detalles = array_map(
                    fn (array $detalle) => [
                        'id_solicitud_reb' => $solicitudId,
                        'id_producto' => $detalle['id_producto'],
                        'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                    ],
                    $validated['detalles']
                );

                $connection->table('reabastecimiento_detalles')->insert($detalles);

                return [
                    'id_solicitud_reb' => $solicitudId,
                    'detalles_registrados' => count($detalles),
                ];
            });

            return $this->successResponse($result, 'Solicitud de reabastecimiento registrada correctamente', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo registrar la solicitud de reabastecimiento.', 500);
        }
    }

    protected function getConnection()
    {
        return DB::connection('mysql_external');
    }

    /**
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    protected function buildIndexPayload(array $filters): array
    {
        $connection = $this->getConnection();
        $tab = $this->normalizeTab($filters['tab'] ?? null);
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        $baseQuery = $this->buildSolicitudesQuery($connection, $filters, null);
        $tabsQuery = $this->buildSolicitudesCountQuery($connection, $filters);

        $tabs = $this->buildTabsMeta($tabsQuery);

        if ($tab !== 'all') {
            $baseQuery->whereIn('sr.id_estado_general', $this->getStateIdsForTab($tab));
        }

        $paginator = $baseQuery
            ->orderByDesc('sr.fecha_creacion')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $this->hydrateSolicitudesRows(collect($paginator->items()), $connection);

        return [
            'data' => $items,
            'meta' => [
                'tabs' => $tabs,
                'active_tab' => $tab,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ];
    }

    protected function buildShowPayload(int $id): ?array
    {
        $connection = $this->getConnection();

        $solicitud = $connection->table('solicitudes_reabastecimiento as sr')
            ->leftJoin('area as a', 'a.id_area', '=', 'sr.id_area_solicitante')
            ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'sr.id_usuario_solicitante')
            ->leftJoin('estados_inventario as ei', 'ei.id_estado', '=', 'sr.id_estado_general')
            ->select([
                'sr.id_solicitud_reb',
                'sr.id_usuario_solicitante',
                'sr.id_area_solicitante',
                'sr.id_estado_general',
                'sr.fecha_creacion',
                'sr.justificacion',
                DB::raw('a.descripcion_area as area'),
                DB::raw('os.staff_id as staff_id'),
                DB::raw('os.dept_id as staff_dept_id'),
                DB::raw('os.role_id as staff_role_id'),
                DB::raw('os.username as staff_username'),
                DB::raw('os.firstname as staff_firstname'),
                DB::raw('os.lastname as staff_lastname'),
                DB::raw('ei.id_estado as estado_id'),
                DB::raw('ei.descripcion as estado_descripcion'),
            ])
            ->where('sr.id_solicitud_reb', $id)
            ->where('sr.id_area_solicitante', self::AREA_ID)
            ->first();

        if (! $solicitud) {
            return null;
        }

        $detalles = $connection->table('reabastecimiento_detalles as rd')
            ->join('productos as p', 'p.id_producto', '=', 'rd.id_producto')
            ->leftJoin('tipos_stock as ts', 'ts.id_tipo_stock', '=', 'p.id_tipo_stock')
            ->leftJoin('categorias_inventario as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->leftJoin('inventario as i', function ($join) use ($solicitud): void {
                $join->on('i.id_producto', '=', 'rd.id_producto')
                    ->where('i.id_area', '=', $solicitud->id_area_solicitante);
            })
            ->select([
                'rd.id_detalle_reb',
                'rd.id_solicitud_reb',
                'rd.id_producto',
                'rd.cantidad_solicitada',
                'p.codigo_producto as codigo',
                'p.descripcion',
                'c.nombre_categoria as categoria',
                'ts.descripcion as tipo',
                'i.stock_actual as stock',
            ])
            ->where('rd.id_solicitud_reb', $id)
            ->orderBy('rd.id_detalle_reb')
            ->get();

        $estado = $this->resolveEstado((int) $solicitud->id_estado_general);

        return [
            'solicitud' => [
                'id_solicitud_reb' => (int) $solicitud->id_solicitud_reb,
                'codigo' => $this->formatCodigo((int) $solicitud->id_solicitud_reb),
                'id_usuario_solicitante' => (int) $solicitud->id_usuario_solicitante,
                'solicitante' => $this->formatStaffFullName($solicitud),
                'staff' => $this->buildStaffPayload($solicitud),
                'id_area_solicitante' => (int) $solicitud->id_area_solicitante,
                'area' => $solicitud->area,
                'id_estado_general' => (int) $solicitud->id_estado_general,
                'estado' => $estado,
                'estado_inventario' => $this->buildEstadoInventarioPayload($solicitud),
                'fecha_creacion' => $solicitud->fecha_creacion,
                'justificacion' => $solicitud->justificacion,
                'detalles_count' => $detalles->count(),
            ],
            'detalles' => $detalles->map(function ($detalle) {
                return [
                    'id_detalle_reb' => (int) $detalle->id_detalle_reb,
                    'id_solicitud_reb' => (int) $detalle->id_solicitud_reb,
                    'id_producto' => (int) $detalle->id_producto,
                    'codigo' => $detalle->codigo,
                    'descripcion' => $detalle->descripcion,
                    'categoria' => $detalle->categoria,
                    'tipo' => $detalle->tipo,
                    'stock' => $detalle->stock !== null ? (int) $detalle->stock : null,
                    'cantidad_solicitada' => (int) $detalle->cantidad_solicitada,
                ];
            })->all(),
        ];
    }

    protected function buildSolicitudesQuery($connection, array $filters, ?string $tab = null)
    {
        $query = $this->buildSolicitudBaseQuery($connection, $filters, $tab)
            ->select([
                'sr.id_solicitud_reb',
                'sr.id_usuario_solicitante',
                'sr.id_area_solicitante',
                'sr.id_estado_general',
                'sr.fecha_creacion',
                'sr.justificacion',
                DB::raw('a.descripcion_area as area'),
                DB::raw('os.staff_id as staff_id'),
                DB::raw('os.dept_id as staff_dept_id'),
                DB::raw('os.role_id as staff_role_id'),
                DB::raw('os.username as staff_username'),
                DB::raw('os.firstname as staff_firstname'),
                DB::raw('os.lastname as staff_lastname'),
                DB::raw('ei.id_estado as estado_id'),
                DB::raw('ei.descripcion as estado_descripcion'),
            ]);

        return $query;
    }

    protected function buildSolicitudesCountQuery($connection, array $filters)
    {
        return $this->buildSolicitudBaseQuery($connection, $filters)
            ->selectRaw('sr.id_estado_general as estado_id, COUNT(*) as total')
            ->groupBy('sr.id_estado_general');
    }

    protected function buildTabsMeta($query): array
    {
        $counts = $query
            ->pluck('total', 'estado_id')
            ->all();

        return [
            'pendientes' => [
                'label' => 'PENDIENTES',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['pendientes']),
            ],
            'procesando' => [
                'label' => 'PROCESANDO',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['procesando']),
            ],
            'cerrados' => [
                'label' => 'CERRADOS',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['cerrados']),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function hydrateSolicitudesRows(Collection $rows, $connection): array
    {
        $detailCounts = $this->getDetailCountsForSolicitudes($connection, $rows->pluck('id_solicitud_reb')->map(fn ($id) => (int) $id)->all());

        return $rows->map(function ($row) use ($detailCounts) {
            $solicitudId = (int) $row->id_solicitud_reb;

            return [
                'id_solicitud_reb' => $solicitudId,
                'codigo' => $this->formatCodigo($solicitudId),
                'id_usuario_solicitante' => (int) $row->id_usuario_solicitante,
                'solicitante' => $this->formatStaffFullName($row),
                'staff' => $this->buildStaffPayload($row),
                'id_area_solicitante' => (int) $row->id_area_solicitante,
                'area' => $row->area,
                'id_estado_general' => (int) $row->id_estado_general,
                'estado' => $this->resolveEstado((int) $row->id_estado_general),
                'estado_inventario' => $this->buildEstadoInventarioPayload($row),
                'fecha_creacion' => $row->fecha_creacion,
                'justificacion' => $row->justificacion,
                'detalles_count' => $detailCounts[$solicitudId] ?? 0,
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function getDetailCountsForSolicitudes($connection, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $connection->table('reabastecimiento_detalles')
            ->selectRaw('id_solicitud_reb, COUNT(*) as total')
            ->whereIn('id_solicitud_reb', $ids)
            ->groupBy('id_solicitud_reb')
            ->pluck('total', 'id_solicitud_reb')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  array<int, int>  $counts
     * @param  array<int, int>  $stateIds
     */
    protected function sumStateCounts(array $counts, array $stateIds): int
    {
        return array_reduce($stateIds, function (int $carry, int $stateId) use ($counts): int {
            return $carry + (int) ($counts[$stateId] ?? 0);
        }, 0);
    }

    protected function getStateIdsForTab(string $tab): array
    {
        return self::TAB_STATE_IDS[$tab] ?? [];
    }

    protected function normalizeTab(?string $tab): string
    {
        if ($tab === null || $tab === '') {
            return 'pendientes';
        }

        return in_array($tab, ['all', 'todos'], true) ? 'all' : $tab;
    }

    protected function resolveEstado(int $estadoId): array
    {
        return self::STATE_META[$estadoId] ?? [
            'key' => 'desconocido',
            'label' => 'Desconocido',
            'color' => 'slate',
            'tab' => 'pendientes',
        ];
    }

    protected function formatCodigo(int $id): string
    {
        return 'CECH_REA_'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int>
     */
    protected function findMatchingStaffIds($connection, string $search): array
    {
        return $connection->table('ost_staff as os')
            ->where(function ($query) use ($search): void {
                $query->where('os.username', 'like', '%'.$search.'%')
                    ->orWhere('os.firstname', 'like', '%'.$search.'%')
                    ->orWhere('os.lastname', 'like', '%'.$search.'%');
            })
            ->pluck('staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function formatStaffFullName(object $row): ?string
    {
        $firstName = trim((string) ($row->staff_firstname ?? ''));
        $lastName = trim((string) ($row->staff_lastname ?? ''));

        $fullName = trim($firstName.' '.$lastName);

        return $fullName !== '' ? $fullName : null;
    }

    protected function buildStaffPayload(object $row): ?array
    {
        if (
            empty($row->staff_id) &&
            empty($row->staff_dept_id) &&
            empty($row->staff_role_id) &&
            empty($row->staff_username) &&
            empty($row->staff_firstname) &&
            empty($row->staff_lastname)
        ) {
            return null;
        }

        return [
            'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null,
            'dept_id' => $row->staff_dept_id !== null ? (int) $row->staff_dept_id : null,
            'role_id' => $row->staff_role_id !== null ? (int) $row->staff_role_id : null,
            'username' => $row->staff_username,
            'firstname' => $row->staff_firstname,
            'lastname' => $row->staff_lastname,
            'full_name' => $this->formatStaffFullName($row),
        ];
    }

    protected function buildEstadoInventarioPayload(object $row): ?array
    {
        if ($row->estado_id === null && $row->estado_descripcion === null) {
            return null;
        }

        return [
            'id_estado' => $row->estado_id !== null ? (int) $row->estado_id : (int) $row->id_estado_general,
            'descripcion' => $row->estado_descripcion,
        ];
    }

    protected function buildSolicitudBaseQuery($connection, array $filters, ?string $tab = null)
    {
        $query = $connection->table('solicitudes_reabastecimiento as sr')
            ->leftJoin('area as a', 'a.id_area', '=', 'sr.id_area_solicitante')
            ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'sr.id_usuario_solicitante')
            ->leftJoin('estados_inventario as ei', 'ei.id_estado', '=', 'sr.id_estado_general');

        $query->where('sr.id_area_solicitante', self::AREA_ID);

        if ($tab && $tab !== 'all') {
            $query->whereIn('sr.id_estado_general', $this->getStateIdsForTab($tab));
        }

        if (! empty($filters['from'])) {
            $query->whereDate('sr.fecha_creacion', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('sr.fecha_creacion', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $staffIds = $this->findMatchingStaffIds($connection, $search);

            $query->where(function ($subquery) use ($search, $staffIds): void {
                $subquery->where('sr.justificacion', 'like', '%'.$search.'%')
                    ->orWhere('a.descripcion_area', 'like', '%'.$search.'%')
                    ->orWhere('os.username', 'like', '%'.$search.'%')
                    ->orWhere('os.firstname', 'like', '%'.$search.'%')
                    ->orWhere('os.lastname', 'like', '%'.$search.'%');

                if (is_numeric($search)) {
                    $subquery->orWhere('sr.id_solicitud_reb', (int) $search)
                        ->orWhere('sr.id_usuario_solicitante', (int) $search);
                }

                if ($staffIds !== []) {
                    $subquery->orWhereIn('sr.id_usuario_solicitante', $staffIds);
                }
            });
        }

        return $query;
    }
}
