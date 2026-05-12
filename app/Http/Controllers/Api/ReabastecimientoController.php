<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReabastecimientoController extends Controller
{
    use ApiResponseTrait;

    private const AREA_ID = 11;

    private const LOG_TABLE = 'reabastecimiento_log';

    private const FLUJO_TABLE = 'reabastecimiento_flujo';

    private const EXTERNAL_ARCHIVOS_BASE_URL = 'https://osticket.cechriza.com/system/vista/ajax/';

    private const ESTADO_PENDIENTE = 10;

    private const ESTADO_APROBADO = 11;

    private const ESTADO_COMPLETADO = 12;

    private const ESTADO_RECHAZADO = 13;

    private const ESTADO_CANCELADO = 14;

    private const ESTADO_OBSERVADO = 15;

    private const DEFAULT_INITIAL_LOG_COMMENT = 'Solicitud creada y pendiente de revisión.';

    private const DEFAULT_INITIAL_FLUJO_AREA_ID = 7;

    private const DEFAULT_INITIAL_FLUJO_USER_ID = 185;

    private const DEFAULT_INITIAL_FLUJO_STATE_ID = self::ESTADO_PENDIENTE;

    private const TAB_STATE_IDS = [
        'pendientes' => [self::ESTADO_PENDIENTE],
        'observadas' => [self::ESTADO_OBSERVADO],
        'aprobadas' => [self::ESTADO_APROBADO],
        'rechazadas' => [self::ESTADO_RECHAZADO],
        'completadas' => [self::ESTADO_COMPLETADO],
        'canceladas' => [self::ESTADO_CANCELADO],
    ];

    private const STATE_META = [
        self::ESTADO_PENDIENTE => [
            'key' => 'pendiente',
            'label' => 'Pendiente',
            'color' => 'yellow',
            'tab' => 'pendientes',
        ],
        self::ESTADO_OBSERVADO => [
            'key' => 'observado',
            'label' => 'Observado',
            'color' => 'blue',
            'tab' => 'observadas',
        ],
        self::ESTADO_APROBADO => [
            'key' => 'aprobado',
            'label' => 'Aprobado',
            'color' => 'green',
            'tab' => 'aprobadas',
        ],
        self::ESTADO_RECHAZADO => [
            'key' => 'rechazado',
            'label' => 'Rechazado',
            'color' => 'red',
            'tab' => 'rechazadas',
        ],
        self::ESTADO_COMPLETADO => [
            'key' => 'completado',
            'label' => 'Completado',
            'color' => 'sky',
            'tab' => 'completadas',
        ],
        self::ESTADO_CANCELADO => [
            'key' => 'cancelado',
            'label' => 'Cancelado',
            'color' => 'gray',
            'tab' => 'canceladas',
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => 'nullable|string|in:pendientes,observadas,aprobadas,rechazadas,completadas,canceladas,todos,all',
            'search' => 'nullable|string|max:255',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $profile = $this->resolveRequesterProfile($request);
            if ($profile['staff_id'] > 0) {
                $validated['_solicitante_id'] = $profile['staff_id'];
            }

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
            'justificacion' => 'required|string|max:1000',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|integer|min:1',
        ]);

        try {
            $profile = $this->resolveRequesterProfile($request, $validated);
            $usuarioId = $profile['staff_id'];
            $areaId = (int) ($validated['id_area_solicitante'] ?? self::AREA_ID);

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
                    'id_estado_general' => self::ESTADO_PENDIENTE,
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
                
                date_default_timezone_set('America/Lima');
                $now = now();
                $connection->table(self::LOG_TABLE)->insertGetId([
                    'id_solicitud_reb' => $solicitudId,
                    'id_usuario_comenta' => (int) $usuarioId,
                    'comentario' => self::DEFAULT_INITIAL_LOG_COMMENT,
                    'archivo_ruta' => null,
                    'archivo_nombre_original' => null,
                    'fecha_creacion' => $now,
                ]);

                $connection->table(self::FLUJO_TABLE)->insertGetId([
                    'id_solicitud_reb' => $solicitudId,
                    'id_area_responsable' => self::DEFAULT_INITIAL_FLUJO_AREA_ID,
                    'id_usuario_asignado' => self::DEFAULT_INITIAL_FLUJO_USER_ID,
                    'id_estado' => self::DEFAULT_INITIAL_FLUJO_STATE_ID,
                    'comentarios' => self::DEFAULT_INITIAL_LOG_COMMENT,
                    'archivo' => null,
                    'fecha_actualizacion' => $now,
                ]);

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

    public function updateEstadoSolicitud(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'id_estado_reb' => 'required_without:id_estado|integer',
            'id_estado' => 'required_without:id_estado_reb|integer',
            'id_area_responsable' => 'nullable|integer',
            'comentario' => 'required|string|max:1000',
            'archivo' => 'nullable|file|max:10240',
        ]);

        $archivoRuta = null;

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, $id);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            $estadoDestino = (int) ($validated['id_estado_reb'] ?? $validated['id_estado']);
            $estadoActual = (int) $solicitud->id_estado_general;

            if (! $this->isRequesterStateTransitionAllowed($estadoActual, $estadoDestino)) {
                return $this->errorResponse('No se permite realizar esta transición desde esta pantalla.', 422);
            }

            $profile = $this->resolveRequesterProfile($request);
            $usuarioId = $profile['staff_id'];

            if (! $usuarioId) {
                return $this->errorResponse('No se pudo resolver el usuario solicitante.', 422);
            }

            if (
                isset($solicitud->id_usuario_solicitante)
                && (int) $solicitud->id_usuario_solicitante > 0
                && (int) $solicitud->id_usuario_solicitante !== $usuarioId
            ) {
                return $this->errorResponse('Solo el solicitante puede modificar esta solicitud.', 403);
            }

            if ($estadoDestino === self::ESTADO_PENDIENTE) {
                try {
                    $this->validateSolicitudHasProducts($connection, $id);
                } catch (\RuntimeException $e) {
                    return $this->errorResponse($e->getMessage(), 422);
                }
            }

            $archivo = $request->file('archivo');
            $archivoRuta = $archivo ? $this->storeUploadedFile($archivo, 'reabastecimiento/seguimiento/'.$id) : null;
            $areaId = $validated['id_area_responsable'] ?? ($profile['id_area'] ?: $solicitud->id_area_solicitante);
            $comentario = trim((string) $validated['comentario']);
            $now = now();

            $result = $connection->transaction(function () use ($connection, $id, $usuarioId, $areaId, $estadoDestino, $comentario, $archivoRuta, $now) {
                $flujoId = $connection->table(self::FLUJO_TABLE)->insertGetId([
                    'id_solicitud_reb' => $id,
                    'id_area_responsable' => (int) $areaId,
                    'id_usuario_asignado' => (int) $usuarioId,
                    'id_estado' => $estadoDestino,
                    'comentarios' => $comentario,
                    'archivo' => $archivoRuta,
                    'fecha_actualizacion' => $now,
                ]);

                $connection->table('solicitudes_reabastecimiento')
                    ->where('id_solicitud_reb', $id)
                    ->update(['id_estado_general' => $estadoDestino]);

                return [
                    'id_solicitud_reb' => $id,
                    'id_estado_final' => $estadoDestino,
                    'id_flujo_reb' => (int) $flujoId,
                ];
            });

            return $this->successResponse($result, $this->messageForEstado($estadoDestino));
        } catch (Throwable $e) {
            $this->deleteStoredUploadedFile($archivoRuta);

            report($e);

            return $this->errorResponse('No se pudo actualizar el estado de la solicitud.', 500);
        }
    }

    public function indexArchivos(Request $request, int $id): JsonResponse
    {
        return $this->indexLogHistory($request, $id);
    }

    public function indexSeguimiento(Request $request, int $id): JsonResponse
    {
        return $this->indexFlujoHistory($request, $id);
    }

    public function indexLogHistory(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, $id);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            $query = $connection->table(self::LOG_TABLE.' as rl')
                ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'rl.id_usuario_comenta')
                ->select([
                    'rl.id_log_reb',
                    'rl.id_solicitud_reb',
                    'rl.id_usuario_comenta',
                    'rl.comentario',
                    'rl.archivo_ruta',
                    'rl.archivo_nombre_original',
                    'rl.fecha_creacion',
                    DB::raw('os.staff_id as staff_id'),
                    DB::raw('os.dept_id as staff_dept_id'),
                    DB::raw('os.role_id as staff_role_id'),
                    DB::raw('os.username as staff_username'),
                    DB::raw('os.firstname as staff_firstname'),
                    DB::raw('os.lastname as staff_lastname'),
                ])
                ->where('rl.id_solicitud_reb', $id);

            if (! empty($validated['search'])) {
                $search = trim((string) $validated['search']);

                $query->where(function ($subquery) use ($search): void {
                    $subquery->where('rl.comentario', 'like', '%'.$search.'%')
                        ->orWhere('rl.archivo_nombre_original', 'like', '%'.$search.'%')
                        ->orWhere('os.username', 'like', '%'.$search.'%')
                        ->orWhere('os.firstname', 'like', '%'.$search.'%')
                        ->orWhere('os.lastname', 'like', '%'.$search.'%');

                    if (is_numeric($search)) {
                        $subquery->orWhere('rl.id_log_reb', (int) $search)
                            ->orWhere('rl.id_usuario_comenta', (int) $search);
                    }
                });
            }

            $perPage = (int) ($validated['per_page'] ?? 10);
            $page = (int) ($validated['page'] ?? 1);

            $paginator = $query
                ->orderByDesc('rl.fecha_creacion')
                ->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())->map(function ($row) {
                return $this->buildArchivoPayload($row);
            })->values()->all();

            return $this->successResponse([
                'data' => $items,
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ],
            ], 'Historial de seguimiento consultado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo consultar el historial de seguimiento.', 500);
        }
    }

    public function indexFlujoHistory(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, $id);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            $query = $connection->table(self::FLUJO_TABLE.' as rf')
                ->leftJoin('estados_reabastecimiento as er', 'er.id_estado_reb', '=', 'rf.id_estado')
                ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'rf.id_usuario_asignado')
                ->leftJoin('area as a', 'a.id_area', '=', 'rf.id_area_responsable')
                ->select([
                    'rf.id_flujo_reb',
                    'rf.id_solicitud_reb',
                    'rf.id_area_responsable',
                    'rf.id_usuario_asignado',
                    'rf.id_estado',
                    'er.descripcion as estado_descripcion',
                    'rf.comentarios',
                    'rf.fecha_actualizacion',
                    'rf.archivo',
                    DB::raw('os.staff_id as staff_id'),
                    DB::raw('os.dept_id as staff_dept_id'),
                    DB::raw('os.role_id as staff_role_id'),
                    DB::raw('os.username as staff_username'),
                    DB::raw('os.firstname as staff_firstname'),
                    DB::raw('os.lastname as staff_lastname'),
                    DB::raw('a.descripcion_area as area'),
                ])
                ->where('rf.id_solicitud_reb', $id);

            if (! empty($validated['search'])) {
                $search = trim((string) $validated['search']);

                $query->where(function ($subquery) use ($search): void {
                    $subquery->where('rf.comentarios', 'like', '%'.$search.'%')
                        ->orWhere('rf.archivo', 'like', '%'.$search.'%')
                        ->orWhere('os.username', 'like', '%'.$search.'%')
                        ->orWhere('os.firstname', 'like', '%'.$search.'%')
                        ->orWhere('os.lastname', 'like', '%'.$search.'%');

                    if (is_numeric($search)) {
                        $subquery->orWhere('rf.id_flujo_reb', (int) $search)
                            ->orWhere('rf.id_usuario_asignado', (int) $search)
                            ->orWhere('rf.id_area_responsable', (int) $search)
                            ->orWhere('rf.id_estado', (int) $search);
                    }
                });
            }

            $perPage = (int) ($validated['per_page'] ?? 10);
            $page = (int) ($validated['page'] ?? 1);

            $paginator = $query
                ->orderBy('rf.fecha_actualizacion')
                // ->orderBy('rf.id_flujo_reb')
                ->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())->map(function ($row) {
                return $this->buildFlujoPayload($row);
            })->values()->all();

            return $this->successResponse([
                'data' => $items,
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ],
            ], 'Historial de flujo consultado correctamente');
        } catch (Throwable $e) {
            ds($e->getMessage());
            report($e);

            return $this->errorResponse('No se pudo consultar el historial de flujo.', 500);
        }
    }

    public function storeDetalle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_solicitud_reb' => 'required|integer',
            'id_producto' => 'required|integer',
            'cantidad_solicitada' => 'required|integer|min:1',
        ]);

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, (int) $validated['id_solicitud_reb']);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            $result = $connection->transaction(function () use ($connection, $validated) {
                $detalleId = $connection->table('reabastecimiento_detalles')->insertGetId([
                    'id_solicitud_reb' => (int) $validated['id_solicitud_reb'],
                    'id_producto' => (int) $validated['id_producto'],
                    'cantidad_solicitada' => (int) $validated['cantidad_solicitada'],
                ]);

                return [
                    'id_detalle_reb' => (int) $detalleId,
                    'id_solicitud_reb' => (int) $validated['id_solicitud_reb'],
                    'id_producto' => (int) $validated['id_producto'],
                    'cantidad_solicitada' => (int) $validated['cantidad_solicitada'],
                ];
            });

            return $this->successResponse($result, 'Detalle de reabastecimiento registrado correctamente', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo registrar el detalle de reabastecimiento.', 500);
        }
    }

    public function storeSeguimiento(Request $request, int $id): JsonResponse
    {
        return $this->storeFlujoHistory($request, $id);
    }

    public function storeLogHistory(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'id_usuario_comenta' => 'nullable|integer',
            'comentario' => 'nullable|string|max:1000',
            'archivo' => 'nullable|file|max:10240',
        ]);

        $archivoRuta = null;

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, $id);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            $comentario = $validated['comentario'] ?? null;

            if (blank($comentario) && ! $request->hasFile('archivo')) {
                return $this->errorResponse(
                    'Debes enviar al menos un comentario o un archivo.',
                    422
                );
            }

            $usuarioId = $validated['id_usuario_comenta'] ?? $request->user()?->id;

            if (! $usuarioId) {
                return $this->errorResponse('No se pudo resolver el usuario que comenta.', 422);
            }

            $archivo = $request->file('archivo');
            $archivoRuta = $archivo ? $this->storeUploadedFile($archivo, 'reabastecimientos/adjuntos/'.$id) : null;

            $now = now();

            $result = $connection->transaction(function () use ($connection, $id, $usuarioId, $comentario, $archivoRuta, $archivo, $now) {
                $logId = $connection->table(self::LOG_TABLE)->insertGetId([
                    'id_solicitud_reb' => $id,
                    'id_usuario_comenta' => (int) $usuarioId,
                    'comentario' => $comentario,
                    'archivo_ruta' => $archivoRuta,
                    'archivo_nombre_original' => $archivo?->getClientOriginalName(),
                    'fecha_creacion' => $now,
                ]);

                return [
                    'id_log_reb' => (int) $logId,
                    'id_solicitud_reb' => (int) $id,
                    'id_usuario_comenta' => (int) $usuarioId,
                    'archivo_ruta' => $archivoRuta,
                    'archivo_url' => $archivoRuta ? $this->buildArchivoUrl($archivoRuta) : null,
                    'archivo_nombre_original' => $archivo?->getClientOriginalName(),
                    'comentario' => $comentario,
                    'fecha_creacion' => $now,
                ];
            });

            return $this->successResponse($result, 'Seguimiento registrado correctamente', 201);
        } catch (Throwable $e) {
            $this->deleteStoredUploadedFile($archivoRuta);

            report($e);

            return $this->errorResponse('No se pudo registrar el seguimiento.', 500);
        }
    }

    public function storeArchivo(Request $request, int $id): JsonResponse
    {
        return $this->storeLogHistory($request, $id);
    }

    public function storeFlujoHistory(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'id_usuario_asignado' => 'nullable|integer',
            'id_usuario_comenta' => 'nullable|integer',
            'id_area_responsable' => 'nullable|integer',
            'id_estado' => 'nullable|integer',
            'comentarios' => 'nullable|string|max:1000',
            'comentario' => 'nullable|string|max:1000',
            'archivo' => 'nullable|file|max:10240',
        ]);

        $archivoRuta = null;

        try {
            $connection = $this->getConnection();
            $solicitud = $this->findSolicitudById($connection, $id);

            if (! $solicitud) {
                return $this->errorResponse('Solicitud de reabastecimiento no encontrada.', 404);
            }

            $comentarios = $validated['comentarios'] ?? $validated['comentario'] ?? null;

            if (blank($comentarios) && ! $request->hasFile('archivo')) {
                return $this->errorResponse(
                    'Debes enviar al menos un comentario o un archivo.',
                    422
                );
            }

            $usuarioId = $validated['id_usuario_asignado']
                ?? $validated['id_usuario_comenta']
                ?? $request->user()?->id;

            if (! $usuarioId) {
                return $this->errorResponse('No se pudo resolver el usuario que comenta.', 422);
            }

            $archivo = $request->file('archivo');
            $archivoRuta = $archivo ? $this->storeUploadedFile($archivo, 'reabastecimiento/seguimiento/'.$id) : null;

            $areaId = $validated['id_area_responsable'] ?? data_get($request->user(), 'department_id') ?? $solicitud->id_area_solicitante;
            $estadoId = $validated['id_estado'] ?? $solicitud->id_estado_general;
            $now = now();

            $result = $connection->transaction(function () use ($connection, $id, $usuarioId, $areaId, $estadoId, $comentarios, $archivoRuta, $archivo, $now) {
                $flujoId = $connection->table(self::FLUJO_TABLE)->insertGetId([
                    'id_solicitud_reb' => $id,
                    'id_area_responsable' => (int) $areaId,
                    'id_usuario_asignado' => (int) $usuarioId,
                    'id_estado' => (int) $estadoId,
                    'comentarios' => $comentarios,
                    'archivo' => $archivoRuta,
                    'fecha_actualizacion' => $now,
                ]);

                return [
                    'id_flujo_reb' => (int) $flujoId,
                    'id_solicitud_reb' => (int) $id,
                    'id_area_responsable' => (int) $areaId,
                    'id_usuario_asignado' => (int) $usuarioId,
                    'id_estado' => (int) $estadoId,
                    'comentarios' => $comentarios,
                    'archivo' => $archivoRuta,
                    'archivo_url' => $archivoRuta ? $this->buildArchivoUrl($archivoRuta) : null,
                    'archivo_nombre_original' => $archivo?->getClientOriginalName(),
                    'fecha_actualizacion' => $now,
                ];
            });

            return $this->successResponse($result, 'Seguimiento registrado correctamente', 201);
        } catch (Throwable $e) {
            $this->deleteStoredUploadedFile($archivoRuta);

            report($e);

            return $this->errorResponse('No se pudo registrar el seguimiento.', 500);
        }
    }

    public function updateDetalle(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'id_producto' => 'nullable|integer',
            'cantidad_solicitada' => 'required|integer|min:1',
        ]);

        try {
            $connection = $this->getConnection();
            $detalle = $this->findDetalleById($connection, $id);

            if (! $detalle) {
                return $this->errorResponse('Detalle de reabastecimiento no encontrado.', 404);
            }

            $payload = [
                'id_producto' => $validated['id_producto'] ?? $detalle->id_producto,
                'cantidad_solicitada' => $validated['cantidad_solicitada'],
            ];

            $connection->transaction(function () use ($connection, $id, $payload): void {
                $connection->table('reabastecimiento_detalles')
                    ->where('id_detalle_reb', $id)
                    ->update($payload);
            });

            return $this->successResponse([
                'id_detalle_reb' => (int) $detalle->id_detalle_reb,
                'id_solicitud_reb' => (int) $detalle->id_solicitud_reb,
                'id_producto' => (int) $payload['id_producto'],
                'cantidad_solicitada' => (int) $payload['cantidad_solicitada'],
            ], 'Detalle de reabastecimiento actualizado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo actualizar el detalle de reabastecimiento.', 500);
        }
    }

    public function destroyDetalle(int $id): JsonResponse
    {
        try {
            $connection = $this->getConnection();
            $detalle = $this->findDetalleById($connection, $id);

            if (! $detalle) {
                return $this->errorResponse('Detalle de reabastecimiento no encontrado.', 404);
            }

            $connection->transaction(function () use ($connection, $id): void {
                $connection->table('reabastecimiento_detalles')
                    ->where('id_detalle_reb', $id)
                    ->delete();
            });

            return $this->successResponse([
                'id_detalle_reb' => (int) $detalle->id_detalle_reb,
                'id_solicitud_reb' => (int) $detalle->id_solicitud_reb,
            ], 'Detalle de reabastecimiento eliminado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo eliminar el detalle de reabastecimiento.', 500);
        }
    }

    public function destroyArchivo(int $id): JsonResponse
    {
        return $this->destroyLogHistory($id);
    }

    public function destroyLogHistory(int $id): JsonResponse
    {
        try {
            $connection = $this->getConnection();
            $archivo = $this->findArchivoById($connection, $id);

            if (! $archivo) {
                return $this->errorResponse('Archivo de reabastecimiento no encontrado.', 404);
            }

            $connection->transaction(function () use ($connection, $id, $archivo): void {
                if (
                    ! empty($archivo->archivo_ruta) &&
                    $this->shouldDeleteStoredArchivo($archivo->archivo_ruta) &&
                    Storage::disk('public')->exists($archivo->archivo_ruta)
                ) {
                    Storage::disk('public')->delete($archivo->archivo_ruta);
                }

                $connection->table(self::LOG_TABLE)
                    ->where('id_log_reb', $id)
                    ->delete();
            });

            return $this->successResponse([
                'id_log_reb' => (int) $archivo->id_log_reb,
                'id_solicitud_reb' => (int) $archivo->id_solicitud_reb,
            ], 'Seguimiento eliminado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo eliminar el seguimiento.', 500);
        }
    }

    public function destroySeguimiento(int $id): JsonResponse
    {
        return $this->destroyFlujoHistory($id);
    }

    public function destroyFlujoHistory(int $id): JsonResponse
    {
        try {
            $connection = $this->getConnection();
            $flujo = $this->findFlujoById($connection, $id);

            if (! $flujo) {
                return $this->errorResponse('Seguimiento de flujo no encontrado.', 404);
            }

            $connection->transaction(function () use ($connection, $id, $flujo): void {
                if (
                    ! empty($flujo->archivo) &&
                    $this->shouldDeleteStoredArchivo($flujo->archivo) &&
                    Storage::disk('public')->exists($flujo->archivo)
                ) {
                    Storage::disk('public')->delete($flujo->archivo);
                }

                $connection->table(self::FLUJO_TABLE)
                    ->where('id_flujo_reb', $id)
                    ->delete();
            });

            return $this->successResponse([
                'id_flujo_reb' => (int) $flujo->id_flujo_reb,
                'id_solicitud_reb' => (int) $flujo->id_solicitud_reb,
            ], 'Seguimiento de flujo eliminado correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo eliminar el seguimiento de flujo.', 500);
        }
    }

    protected function getConnection()
    {
        return DB::connection('mysql_external');
    }

    /**
     * @return array{staff_id:int,id_area:int}
     */
    protected function resolveRequesterProfile(Request $request, array $payload = []): array
    {
        $user = $request->user();
        $staffId = (int) (
            ($payload['id_usuario_solicitante'] ?? null)
            ?: data_get($user, 'staff_id')
            ?: 0
        );
        $areaId = (int) (
            ($payload['id_area_solicitante'] ?? null)
            ?: data_get($user, 'department_id')
            ?: data_get($user, 'id_area')
            ?: data_get($user, 'area_id')
            ?: 0
        );
        $empCode = trim((string) (data_get($user, 'emp_code') ?: ''));

        if ($empCode !== '') {
            try {
                $staff = $this->getConnection()
                    ->table('ost_staff')
                    ->select(['staff_id', 'dept_id'])
                    ->where('dni', $empCode)
                    ->first();

                if ($staff) {
                    $staffId = (int) ($payload['id_usuario_solicitante'] ?? $staff->staff_id ?? $staffId);
                    $areaId = (int) ($payload['id_area_solicitante'] ?? $staff->dept_id ?? $areaId);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($staffId <= 0) {
            $staffId = (int) ($user?->id ?? 0);
        }

        if ($areaId <= 0) {
            $areaId = self::AREA_ID;
        }

        return [
            'staff_id' => $staffId,
            'id_area' => $areaId,
        ];
    }

    protected function storeUploadedFile(UploadedFile $archivo, string $directorio): string
    {
        $extension = $archivo->getClientOriginalExtension();
        $nombreArchivo = (string) Str::uuid();

        if ($extension !== '') {
            $nombreArchivo .= '.'.$extension;
        }

        $absoluteDirectory = public_path($directorio);

        if (! is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0775, true);
        }

        $archivo->move($absoluteDirectory, $nombreArchivo);

        return trim($directorio, '/').'/'.$nombreArchivo;
    }

    protected function deleteStoredUploadedFile(?string $archivoRuta): void
    {
        if (blank($archivoRuta)) {
            return;
        }

        $absolutePath = public_path(ltrim($archivoRuta, '/'));

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
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
            ->leftJoin('estados_reabastecimiento as er', 'er.id_estado_reb', '=', 'sr.id_estado_general')
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
                DB::raw('er.id_estado_reb as estado_id'),
                DB::raw('er.descripcion as estado_descripcion'),
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
        $totalUnidades = (int) $detalles->sum(fn ($detalle) => (int) $detalle->cantidad_solicitada);

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
                'total_productos' => $detalles->count(),
                'total_unidades' => $totalUnidades,
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
                DB::raw('er.id_estado_reb as estado_id'),
                DB::raw('er.descripcion as estado_descripcion'),
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
                'label' => 'Pendientes',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['pendientes']),
            ],
            'observadas' => [
                'label' => 'Observadas',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['observadas']),
            ],
            'aprobadas' => [
                'label' => 'Aprobadas',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['aprobadas']),
            ],
            'rechazadas' => [
                'label' => 'Rechazadas',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['rechazadas']),
            ],
            'completadas' => [
                'label' => 'Completadas',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['completadas']),
            ],
            'canceladas' => [
                'label' => 'Canceladas',
                'count' => $this->sumStateCounts($counts, self::TAB_STATE_IDS['canceladas']),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function hydrateSolicitudesRows(Collection $rows, $connection): array
    {
        $detailStats = $this->getDetailStatsForSolicitudes($connection, $rows->pluck('id_solicitud_reb')->map(fn ($id) => (int) $id)->all());

        return $rows->map(function ($row) use ($detailStats) {
            $solicitudId = (int) $row->id_solicitud_reb;
            $stats = $detailStats[$solicitudId] ?? ['productos' => 0, 'unidades' => 0];

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
                'detalles_count' => $stats['productos'],
                'total_productos' => $stats['productos'],
                'total_unidades' => $stats['unidades'],
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function getDetailStatsForSolicitudes($connection, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $connection->table('reabastecimiento_detalles')
            ->selectRaw('id_solicitud_reb, COUNT(*) as total_productos, COALESCE(SUM(cantidad_solicitada), 0) as total_unidades')
            ->whereIn('id_solicitud_reb', $ids)
            ->groupBy('id_solicitud_reb')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->id_solicitud_reb => [
                    'productos' => (int) $row->total_productos,
                    'unidades' => (int) $row->total_unidades,
                ],
            ])
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

    /**
     * @return array<int>
     */
    protected function allStateIds(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::TAB_STATE_IDS))));
    }

    protected function isRequesterStateTransitionAllowed(int $estadoActual, int $estadoDestino): bool
    {
        return match ($estadoActual) {
            self::ESTADO_PENDIENTE => $estadoDestino === self::ESTADO_CANCELADO,
            self::ESTADO_OBSERVADO => $estadoDestino === self::ESTADO_PENDIENTE,
            default => false,
        };
    }

    protected function messageForEstado(int $estadoId): string
    {
        return match ($estadoId) {
            self::ESTADO_CANCELADO => 'Solicitud cancelada correctamente.',
            self::ESTADO_PENDIENTE => 'Solicitud reenviada para revisión.',
            default => 'Estado actualizado correctamente.',
        };
    }

    protected function validateSolicitudHasProducts($connection, int $solicitudId): void
    {
        $total = (int) $connection->table('reabastecimiento_detalles')
            ->where('id_solicitud_reb', $solicitudId)
            ->where('cantidad_solicitada', '>', 0)
            ->count();

        if ($total <= 0) {
            throw new \RuntimeException('La solicitud debe tener al menos un producto.');
        }
    }

    protected function formatCodigo(int $id): string
    {
        return 'CECH_REA_'.str_pad((string) $id, 8, '0', STR_PAD_LEFT);
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
            $estadoId = (int) ($row->id_estado_general ?? 0);
            $estado = $this->resolveEstado($estadoId);

            return [
                'id_estado' => $estadoId,
                'descripcion' => $estado['label'],
            ];
        }

        $estadoId = $row->estado_id !== null ? (int) $row->estado_id : (int) $row->id_estado_general;
        $estado = $this->resolveEstado($estadoId);

        return [
            'id_estado' => $estadoId,
            'descripcion' => $row->estado_descripcion ?: $estado['label'],
        ];
    }

    protected function buildSolicitudBaseQuery($connection, array $filters, ?string $tab = null)
    {
        $query = $connection->table('solicitudes_reabastecimiento as sr')
            ->leftJoin('area as a', 'a.id_area', '=', 'sr.id_area_solicitante')
            ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'sr.id_usuario_solicitante')
            ->leftJoin('estados_reabastecimiento as er', 'er.id_estado_reb', '=', 'sr.id_estado_general');

        $query->where('sr.id_area_solicitante', self::AREA_ID);
        $query->whereIn('sr.id_estado_general', $this->allStateIds());

        if (! empty($filters['_solicitante_id'])) {
            $query->where('sr.id_usuario_solicitante', (int) $filters['_solicitante_id']);
        }

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

    protected function findDetalleById($connection, int $id): ?object
    {
        return $connection->table('reabastecimiento_detalles as rd')
            ->join('solicitudes_reabastecimiento as sr', 'sr.id_solicitud_reb', '=', 'rd.id_solicitud_reb')
            ->select([
                'rd.id_detalle_reb',
                'rd.id_solicitud_reb',
                'rd.id_producto',
                'rd.cantidad_solicitada',
            ])
            ->where('rd.id_detalle_reb', $id)
            ->where('sr.id_area_solicitante', self::AREA_ID)
            ->first();
    }

    protected function findSolicitudById($connection, int $id): ?object
    {
        return $connection->table('solicitudes_reabastecimiento as sr')
            ->select([
                'sr.id_solicitud_reb',
                'sr.id_usuario_solicitante',
                'sr.id_area_solicitante',
                'sr.id_estado_general',
            ])
            ->where('sr.id_solicitud_reb', $id)
            ->where('sr.id_area_solicitante', self::AREA_ID)
            ->first();
    }

    protected function findArchivoById($connection, int $id): ?object
    {
        return $connection->table(self::LOG_TABLE.' as rl')
            ->join('solicitudes_reabastecimiento as sr', 'sr.id_solicitud_reb', '=', 'rl.id_solicitud_reb')
            ->select([
                'rl.id_log_reb',
                'rl.id_solicitud_reb',
                'rl.id_usuario_comenta',
                'rl.comentario',
                'rl.archivo_ruta',
                'rl.archivo_nombre_original',
                'rl.fecha_creacion',
            ])
            ->where('rl.id_log_reb', $id)
            ->where('sr.id_area_solicitante', self::AREA_ID)
            ->first();
    }

    protected function findFlujoById($connection, int $id): ?object
    {
        return $connection->table(self::FLUJO_TABLE.' as rf')
            ->join('solicitudes_reabastecimiento as sr', 'sr.id_solicitud_reb', '=', 'rf.id_solicitud_reb')
            ->select([
                'rf.id_flujo_reb',
                'rf.id_solicitud_reb',
                'rf.id_area_responsable',
                'rf.id_usuario_asignado',
                'rf.id_estado',
                'rf.comentarios',
                'rf.archivo',
                'rf.fecha_actualizacion',
                DB::raw('os.staff_id as staff_id'),
                DB::raw('os.dept_id as staff_dept_id'),
                DB::raw('os.role_id as staff_role_id'),
                DB::raw('os.username as staff_username'),
                DB::raw('os.firstname as staff_firstname'),
                DB::raw('os.lastname as staff_lastname'),
                DB::raw('a.descripcion_area as area'),
            ])
            ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'rf.id_usuario_asignado')
            ->leftJoin('area as a', 'a.id_area', '=', 'rf.id_area_responsable')
            ->where('rf.id_flujo_reb', $id)
            ->where('sr.id_area_solicitante', self::AREA_ID)
            ->first();
    }

    protected function buildArchivoPayload(object $row): array
    {
        $archivo = $row->archivo_ruta ?? null;
        $comentario = $row->comentario ?? null;
        $fecha = $row->fecha_creacion ?? null;
        $logId = (int) $row->id_log_reb;
        $usuarioId = $row->id_usuario_comenta ?? null;

        return [
            'id_log_reb' => $logId,
            'id_flujo_reb' => $logId,
            'id_solicitud_reb' => (int) $row->id_solicitud_reb,
            'id_usuario_comenta' => $usuarioId !== null ? (int) $usuarioId : null,
            'comentario' => $comentario,
            'archivo_ruta' => $archivo,
            'archivo_url' => $archivo ? $this->buildArchivoUrl($archivo) : null,
            'archivo_nombre_original' => $row->archivo_nombre_original ?? ($archivo ? basename((string) $archivo) : null),
            'staff' => $this->buildStaffPayload($row),
            'fecha_creacion' => $fecha,
        ];
    }

    protected function buildFlujoPayload(object $row): array
    {
        $archivo = $row->archivo ?? null;
        $comentarios = $row->comentarios ?? null;
        $fecha = $row->fecha_actualizacion ?? null;
        $flujoId = (int) $row->id_flujo_reb;
        $usuarioId = $row->id_usuario_asignado ?? null;

        return [
            'id_flujo_reb' => $flujoId,
            'id_solicitud_reb' => (int) $row->id_solicitud_reb,
            'id_area_responsable' => isset($row->id_area_responsable) ? (int) $row->id_area_responsable : null,
            'id_usuario_asignado' => $usuarioId !== null ? (int) $usuarioId : null,
            'id_estado' => isset($row->id_estado) ? (int) $row->id_estado : null,
            'comentarios' => $comentarios,
            'archivo' => $archivo,
            'estado_descripcion' => $row->estado_descripcion ?? null,
            'archivo_url' => $archivo ? $this->buildArchivoUrl($archivo) : null,
            'archivo_nombre_original' => $row->archivo_nombre_original ?? ($archivo ? basename((string) $archivo) : null),
            'staff' => $this->buildStaffPayload($row),
            'responsable' => $this->buildResponsableLabel($row),
            'area' => $row->area ?? null,
            'fecha_actualizacion' => $fecha,
        ];
    }

    protected function buildResponsableLabel(object $row): ?string
    {
        $fullName = $this->formatStaffFullName($row);
        $area = trim((string) ($row->area ?? ''));

        if ($fullName === null && $area === '') {
            return null;
        }

        if ($fullName === null) {
            return $area !== '' ? $area : null;
        }

        if ($area === '') {
            return $fullName;
        }

        return $fullName.' ('.$area.')';
    }

    protected function buildArchivoUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalizedPath = preg_replace('#^(\.\./)+#', '', $path) ?? $path;
        $normalizedPath = ltrim($normalizedPath, '/');

        return rtrim(self::EXTERNAL_ARCHIVOS_BASE_URL, '/').'/'.$normalizedPath;
    }

    protected function shouldDeleteStoredArchivo(string $path): bool
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return false;
        }

        return ! str_contains($path, '../');
    }
}
