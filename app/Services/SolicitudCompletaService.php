<?php

namespace App\Services;

use App\Events\SolicitudCompletaCreada;
use App\Mail\SolicitudRegistradaMail;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SolicitudCompletaService implements SolicitudCompletaServiceInterface
{
    private const ESTADO_INICIAL = 11;

    private const AREA_INTERNO_RRHH = 11;
    private const AREA_LOGISTICA = 7;
    private const AREA_LOGISTICA_SECUNDARIA = 12;

    private const ID_DEPARTAMENTO_LIMA = 1;

    private const TIPO_SOLICITUD_INTERNO = 'INTERNO';

    private const TIPO_SOLICITUD_MIXTO = 'MIXTO';

    /**
     * @var array<int, string>
     */
    private const CATEGORIES = ['insumos', 'ssgg', 'rrhh'];

    public function registrar(array $data, array $files = []): array
    {
        $connection = DB::connection('mysql_external');
        $solicitante = $this->findSolicitante($connection, (int) $data['id_usuario_solicitante']);

        if ($solicitante === null) {
            throw new DomainException('No se encontró el usuario solicitante.');
        }

        if (empty($solicitante->dept_id)) {
            throw new DomainException('No se pudo resolver el área de origen del solicitante.');
        }

        $items = $this->collectItems($connection, $data, $files);

        if ($items === []) {
            throw new DomainException($this->buildNoValidProductsMessage($data));
        }

        $esUbicacionLima = $this->isSolicitanteDeLima($solicitante);
        $itemsNoPscrAreaInterna = $esUbicacionLima
            ? $this->extractItemsByArea($items, self::AREA_INTERNO_RRHH)
            : [];
        $itemsNoPscrRestantes = $items;

        if ($itemsNoPscrAreaInterna !== []) {
            $itemsNoPscrRestantes = $this->excludeItemsByArea($items, self::AREA_INTERNO_RRHH);
        }

        $solicitudesARegistrar = [];

        if ($itemsNoPscrAreaInterna !== []) {
            $solicitudesARegistrar[] = $itemsNoPscrAreaInterna;
        }

        if ($itemsNoPscrRestantes !== []) {
            $solicitudesARegistrar[] = $itemsNoPscrRestantes;
        }

        $tickets = [];
        $uploadedFiles = [];

        foreach ($solicitudesARegistrar as $solicitudItems) {
            $registro = $this->persistSolicitud(
                $connection,
                $data,
                $solicitante,
                $solicitudItems
            );

            $solicitudId = (int) $registro['solicitud_id'];
            $areaIds = $registro['area_ids'];
            $uploadedFilesSolicitud = [];

            try {
                $uploadedFilesSolicitud = $this->storeFiles($solicitudId, $solicitudItems);
                $this->syncDetalleImagenes($connection, $solicitudId, $uploadedFilesSolicitud);
            } catch (Throwable $e) {
                $this->deleteStoredFiles($uploadedFilesSolicitud);
                $this->rollbackSolicitud($connection, $solicitudId);
                throw $e;
            }

            $this->sendNotifications(
                $connection,
                $data,
                $solicitante,
                $solicitudItems,
                $areaIds,
                $solicitudId,
                $uploadedFilesSolicitud
            );

            broadcast(new SolicitudCompletaCreada([
                'id_solicitud' => $solicitudId,
                'ticket' => $this->formatTicket($solicitudId),
                'solicitante' => [
                    'staff_id' => (int) $solicitante->staff_id,
                    'nombre' => trim((string) ($solicitante->firstname ?? '').' '.(string) ($solicitante->lastname ?? '')),
                    'firstname' => $solicitante->firstname ?? null,
                    'lastname' => $solicitante->lastname ?? null,
                    'area_id' => isset($solicitante->id_area) ? (int) $solicitante->id_area : null,
                    'area' => $solicitante->area ?? null,
                    'cargo_id' => isset($solicitante->id_cargo) ? (int) $solicitante->id_cargo : null,
                    'cargo' => $solicitante->cargo ?? null,
                ],
                'detalles_count' => count($solicitudItems),
            ]));

            $tickets[] = $this->formatTicket($solicitudId);
            $uploadedFiles = array_merge($uploadedFiles, $uploadedFilesSolicitud);
        }

        if ($tickets === []) {
            throw new DomainException('No se pudo registrar la solicitud.');
        }

        return [
            'ticket' => $tickets[0],
            'uploaded_files' => $uploadedFiles,
            'tickets' => $tickets,
        ];
    }

    public function actualizarDetalles(int $idSolicitud, array $data, array $files = []): array
    {
        $connection = DB::connection('mysql_external');
        $solicitud = $this->findSolicitudById($connection, $idSolicitud);

        if ($solicitud === null) {
            throw new DomainException('Solicitud no encontrada.');
        }

        $detalles = $this->normalizeDetallesUpdatePayload($data, $files);
        
        $detallesEliminados = $this->normalizeDeleteIds($data);

        Log::info('Actualizando detalles de solicitud', [
            'id_solicitud' => $idSolicitud,
            'detalles_recibidos' => count($detalles),
            'detalles_eliminados_recibidos' => count($detallesEliminados),
        ]);

        if ($detalles === [] && $detallesEliminados === []) {
            throw new DomainException('Debes enviar al menos un detalle para actualizar.');
        }

        $existingDetalles = $this->getDetallesBySolicitudId($connection, $idSolicitud);
        $existingById = collect($existingDetalles)->keyBy('id_detalle_solicitud')->all();
        $existingByInventario = collect($existingDetalles)->keyBy('id_inventario')->all();

        $uploadedFiles = [];
        $pathsToDelete = [];
        $updatedCount = 0;
        $createdCount = 0;
        $deletedCount = 0;

        $connection->transaction(function () use (
            $connection,
            $idSolicitud,
            $detalles,
            $detallesEliminados,
            $existingById,
            $existingByInventario,
            &$uploadedFiles,
            &$pathsToDelete,
            &$updatedCount,
            &$createdCount,
            &$deletedCount
        ): void {
            $usedInventarioIds = [];

            foreach ($detalles as $index => $detalleData) {
                $detalleId = isset($detalleData['id_detalle_solicitud'])
                    ? (int) $detalleData['id_detalle_solicitud']
                    : null;
                $idInventario = (int) $detalleData['id_inventario'];
                $cantidadSolicitada = (int) $detalleData['cantidad_solicitada'];
                $areaId = isset($detalleData['area_id']) && (int) $detalleData['area_id'] > 0
                    ? (int) $detalleData['area_id']
                    : null;
                $comentario = isset($detalleData['comentario'])
                    ? trim((string) $detalleData['comentario'])
                    : '';
                $comentario = $comentario !== '' ? $comentario : null;
                $quitarImagen = (bool) ($detalleData['quitar_imagen'] ?? false);
                $file = $detalleData['file'] ?? null;

                if (isset($usedInventarioIds[$idInventario])) {
                    throw new DomainException("El inventario {$idInventario} esta duplicado en la actualizacion.");
                }
                $usedInventarioIds[$idInventario] = true;

                $existingDetalle = $detalleId !== null
                    ? ($existingById[$detalleId] ?? null)
                    : ($existingByInventario[$idInventario] ?? null);

                if ($detalleId !== null && $existingDetalle === null) {
                    throw new DomainException("El detalle {$detalleId} no existe en la solicitud.");
                }

                if ($existingDetalle !== null) {
                    foreach ($existingByInventario as $otherExistingDetalle) {
                        if (
                            (int) $otherExistingDetalle->id_detalle_solicitud !== (int) $existingDetalle->id_detalle_solicitud
                            && (int) $otherExistingDetalle->id_inventario === $idInventario
                        ) {
                            throw new DomainException("El inventario {$idInventario} ya esta usado en otro detalle de la solicitud.");
                        }
                    }
                }

                $payload = [
                    'id_inventario' => $idInventario,
                    'cantidad_solicitada' => $cantidadSolicitada,
                    'comentario' => $comentario,
                    'area_id' => $areaId,
                ];

                if ($existingDetalle !== null) {
                    $connection->table('solicitud_detalles')
                        ->where('id_detalle_solicitud', (int) $existingDetalle->id_detalle_solicitud)
                        ->update($payload);

                    $updatedCount++;

                    if ($quitarImagen && ! empty($existingDetalle->ruta_imagen)) {
                        $pathsToDelete[] = [
                            'path' => (string) $existingDetalle->ruta_imagen,
                        ];

                        $connection->table('solicitud_detalles')
                            ->where('id_detalle_solicitud', (int) $existingDetalle->id_detalle_solicitud)
                            ->update([
                                'ruta_imagen' => null,
                                'url_imagen' => null,
                            ]);
                    }

                    if ($file instanceof UploadedFile) {
                        if (! empty($existingDetalle->ruta_imagen)) {
                            $pathsToDelete[] = [
                                'path' => (string) $existingDetalle->ruta_imagen,
                            ];
                        }

                        $storedFile = $this->storeDetalleFile(
                            $idSolicitud,
                            (int) $existingDetalle->id_detalle_solicitud,
                            $idInventario,
                            $file
                        );

                        $uploadedFiles[] = $storedFile;

                        $connection->table('solicitud_detalles')
                            ->where('id_detalle_solicitud', (int) $existingDetalle->id_detalle_solicitud)
                            ->update([
                                'ruta_imagen' => $storedFile['path'],
                                'url_imagen' => $storedFile['url'] ?? null,
                            ]);
                    }

                    continue;
                }

                $detalleIdCreado = (int) $connection->table('solicitud_detalles')->insertGetId([
                    'id_solicitud' => $idSolicitud,
                    'id_inventario' => $idInventario,
                    'cantidad_solicitada' => $cantidadSolicitada,
                    'id_estado_detalle' => self::ESTADO_INICIAL,
                    'comentario' => $comentario,
                    'area_id' => $areaId,
                    'ruta_imagen' => null,
                    'url_imagen' => null,
                ]);

                $createdCount++;

                if ($file instanceof UploadedFile) {
                    $storedFile = $this->storeDetalleFile(
                        $idSolicitud,
                        $detalleIdCreado,
                        $idInventario,
                        $file
                    );

                    $uploadedFiles[] = $storedFile;

                    $connection->table('solicitud_detalles')
                        ->where('id_detalle_solicitud', $detalleIdCreado)
                        ->update([
                            'ruta_imagen' => $storedFile['path'],
                            'url_imagen' => $storedFile['url'] ?? null,
                        ]);
                }
            }

            if ($detallesEliminados !== []) {
                foreach ($detallesEliminados as $detalleId) {
                    $existingDetalle = $existingById[$detalleId] ?? null;
                    if ($existingDetalle === null) {
                        continue;
                    }

                    if (! empty($existingDetalle->ruta_imagen)) {
                        $pathsToDelete[] = [
                            'path' => (string) $existingDetalle->ruta_imagen,
                        ];
                    }

                    $connection->table('solicitud_detalles')
                        ->where('id_detalle_solicitud', (int) $detalleId)
                        ->delete();

                    $deletedCount++;
                }
            }
        });

        if ($pathsToDelete !== []) {
            $this->deleteStoredFiles($pathsToDelete);
        }

        return [
            'id_solicitud' => $idSolicitud,
            'ticket' => $this->formatTicket($idSolicitud),
            'detalles_actualizados' => $updatedCount,
            'detalles_creados' => $createdCount,
            'detalles_eliminados' => $deletedCount,
            'uploaded_files' => $uploadedFiles,
            'detalles' => $this->getDetallesBySolicitudId($connection, $idSolicitud)
                ->map(fn (object $row): array => $this->buildDetalleResponsePayload($row))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @return array<int, array<string, mixed>>
     */
    protected function collectItems(object $connection, array $data, array $files): array
    {
        ['data' => $data, 'files' => $files] = $this->normalizeItemsPayload($data, $files);

        $items = [];
        $seenInventarios = [];
        $areasGlobales = $this->normalizeList($data, 'id_area');
        $areaGlobalIndex = 0;

        foreach (self::CATEGORIES as $category) {
            $inventarios = $this->normalizeList($data, "id_producto_{$category}");
            $cantidades = $this->normalizeList($data, "cantidad_{$category}");
            $observaciones = $this->normalizeList($data, "observacion_{$category}");
            $fotos = $this->normalizeListPreserveKeys($files, "foto_{$category}");
            $areasCategoria = $this->normalizeList($data, "id_area_{$category}");
            foreach ($inventarios as $index => $inventarioRaw) {
                $idReferencia = (int) $inventarioRaw;
                $cantidad = (int) ($cantidades[$index] ?? 0);
                $areaDesdeCategoria = (int) ($areasCategoria[$index] ?? 0);
                $areaDesdeGlobal = (int) ($areasGlobales[$areaGlobalIndex] ?? 0);
                $areaGlobalIndex++;

                if ($idReferencia <= 0 || $cantidad <= 0) {
                    continue;
                }

                // Compatibilidad: el cliente puede enviar id_producto en el campo id_inventario.
                // Priorizamos buscar por id_producto y, si no existe, intentamos por id_inventario.
                $inventario = $this->findInventarioByProducto($connection, $idReferencia);
                if ($inventario === null) {
                    $inventario = $this->findInventario($connection, $idReferencia);
                }

                if ($inventario === null) {
                    continue;
                }

                $idInventario = (int) $inventario->id_inventario;

                if (isset($seenInventarios[$idInventario])) {
                    throw new DomainException("El inventario {$idInventario} esta duplicado en la solicitud.");
                }

                $idAreaDetalle = $areaDesdeCategoria > 0
                    ? $areaDesdeCategoria
                    : $areaDesdeGlobal;

                if ($idAreaDetalle <= 0) {
                    continue;
                }

                $file = Arr::get($fotos, $index);
                $requiereFoto = (int) ($inventario->requiere_foto_producto_anterior ?? 0) === 1;

                if ($requiereFoto && ! $file instanceof UploadedFile) {
                    $producto = (string) ($inventario->producto ?? $idInventario);
                    throw new DomainException("El producto {$producto} requiere foto y no se adjuntó archivo en la categoría {$category}.");
                }

                $observacion = trim((string) ($observaciones[$index] ?? ''));

                $items[] = [
                    'category' => $category,
                    'index' => $index,
                    'id_inventario' => $idInventario,
                    'id_producto' => (int) $inventario->id_producto,
                    'id_area' => $idAreaDetalle,
                    'product_name' => (string) ($inventario->producto ?? ''),
                    'requires_photo' => $requiereFoto,
                    'quantity' => $cantidad,
                    'observacion' => $observacion ,
                    'file' => $file instanceof UploadedFile ? $file : null,
                ];

                $seenInventarios[$idInventario] = true;
            }
        }

        return $items;
    }

    /**
     * Adapta payloads tipo items[n][...] al formato por categorías existente.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @return array{data: array<string, mixed>, files: array<string, mixed>}
     */
    protected function normalizeItemsPayload(array $data, array $files): array
    {
        $items = Arr::get($data, 'items');
        if (! is_array($items) || $items === []) {
            return ['data' => $data, 'files' => $files];
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $category = $this->resolveItemCategory($item);

            // Priorizamos id_producto para resolver el inventario real.
            $inventarioId = (int) ($item['id_producto'] ?? $item['id_inventario'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? $item['quantity'] ?? 0);
            $idArea = (int) ($item['id_area'] ?? $item['area_id'] ?? 0);
            $observacion = trim((string) ($item['observacion'] ?? $item['observation'] ?? ''));

            $data["id_producto_{$category}"][] = $inventarioId;
            $data["cantidad_{$category}"][] = $cantidad;
            $data["id_area_{$category}"][] = $idArea;
            $data["observacion_{$category}"][] = $observacion;

            $file = Arr::get($files, "items.{$index}.imagen");
            if (! $file instanceof UploadedFile) {
                $file = Arr::get($files, "items.{$index}.foto");
            }
            if (! $file instanceof UploadedFile) {
                $file = Arr::get($files, "items.{$index}.image");
            }

            $files["foto_{$category}"][] = $file instanceof UploadedFile ? $file : null;
        }

        return ['data' => $data, 'files' => $files];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveItemCategory(array $item): string
    {
        $raw = strtolower(trim((string) ($item['categoria'] ?? $item['category'] ?? '')));

        return match ($raw) {
            'insumo', 'insumos' => 'insumos',
            'ssgg', 'servicios', 'servicio', 'servicios_generales', 'servicios-generales' => 'ssgg',
            'rrhh', 'rh', 'rr.h.' => 'rrhh',
            default => 'insumos',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     * @return array{solicitud_id:int,area_ids:array<int,int>}
     */
    protected function persistSolicitud(
        object $connection,
        array $data,
        object $solicitante,
        array $items
    ): array {
        $areaIds = collect($items)
            ->pluck('id_area')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $areaIds = array_values(array_unique(array_map('intval', $areaIds)));
        $tipoSolicitud = $this->resolveTipoSolicitud($items);
        $now = now();

        $solicitudId = $connection->transaction(function () use ($connection, $data, $items, $areaIds, $solicitante, $tipoSolicitud, $now): int {
            $solicitudId = (int) $connection->table('solicitudes')->insertGetId([
                'id_usuario_solicitante' => (int) $data['id_usuario_solicitante'],
                'id_area_origen' => (int) $solicitante->dept_id,
                'id_estado_general' => self::ESTADO_INICIAL,
                'fecha_registro' => $now,
                'prioridad' => $data['prioridad'] ?? 'Media',
                'fecha_necesaria' => $data['fecha_necesaria'] ?? null,
                'tipo_entrega_preferida' => $data['tipo_entrega_preferida'] ?? 'Directo',
                'id_direccion_entrega' => $data['id_direccion_entrega'] ?? null,
                'id_ubicacion' => $data['id_ubicacion'] ?? null,
                'ubicacion' => $data['ubicacion'] ?? null,
                'es_pedido_compra' => 0,
                'pedido_compra_estado' => 0,
                'tipo_solicitud' => $tipoSolicitud,
                'justificacion' => $data['justificacion'] ?? null,
            ]);

            $detalleRows = [];
            foreach ($items as $item) {
                $detalleRows[] = [
                    'id_solicitud' => $solicitudId,
                    'id_inventario' => (int) $item['id_inventario'],
                    'cantidad_solicitada' => (int) $item['quantity'],
                    'id_estado_detalle' => self::ESTADO_INICIAL,
                    'comentario' => $item['observacion'],
                    'area_id' => (int) $item['id_area'],
                    'ruta_imagen' => null,
                    'url_imagen' => null,
                ];
            }

            if ($detalleRows !== []) {
                $connection->table('solicitud_detalles')->insert($detalleRows);
            }

            $areaRows = [];
            foreach ($areaIds as $areaId) {
                $areaRows[] = [
                    'id_solicitud' => $solicitudId,
                    'id_area' => (int) $areaId,
                    'id_estado_area' => self::ESTADO_INICIAL,
                    'fecha_recepcion' => $now,
                ];
            }

            if ($areaRows !== []) {
                $connection->table('solicitud_areas')->insert($areaRows);
            }
            $comentarios = sprintf(
                'Solicitud creada con %d ítems. Derivada a %d áreas.',
                count($items),
                count($areaIds)
            );

            $connection->table('solicitud_flujo_aprobaciones')->insertGetId([
                'id_solicitud' => $solicitudId,
                'id_area_responsable' => (int) $solicitante->dept_id,
                'id_usuario_asignado' => (int) $data['id_usuario_solicitante'],
                'id_estado' => self::ESTADO_INICIAL,
                'comentarios' => $comentarios,
                'fecha_actualizacion' => $now,
            ]);

            return $solicitudId;
        });

        return [
            'solicitud_id' => $solicitudId,
            'area_ids' => $areaIds,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function extractItemsByArea(array $items, int $areaId): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => (int) ($item['id_area'] ?? 0) === $areaId
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function excludeItemsByArea(array $items, int $areaId): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => (int) ($item['id_area'] ?? 0) !== $areaId
        ));
    }

    protected function findSolicitante(object $connection, int $staffId): ?object
    {
        $rows = $connection->select(
            <<<'SQL'
                SELECT
                    u.staff_id,
                    u.dept_id,
                    u.firstname,
                    u.lastname,
                    u.email,
                    u.role_id,
                    u.id_departamento,
                    u.id_area,
                    u.id_cargo,
                    a.descripcion_area AS area,
                    c.descripcion_cargo AS cargo
                FROM ost_staff u
                LEFT JOIN area a
                    ON a.id_area = u.id_area
                LEFT JOIN cargo c
                    ON c.id_cargo = u.id_cargo
                WHERE u.staff_id = ?
                LIMIT 1
            SQL,
            [$staffId]
        );

        return $rows[0] ?? null;
    }

    protected function findInventario(object $connection, int $idInventario): ?object
    {
        $rows = $connection->select(
            <<<'SQL'
                SELECT
                    i.id_inventario,
                    i.id_area,
                    i.id_producto,
                    p.descripcion AS producto,
                    p.requiere_foto_producto_anterior,
                    a.descripcion_area
                FROM inventario i
                INNER JOIN productos p ON p.id_producto = i.id_producto
                LEFT JOIN area a ON a.id_area = i.id_area
                WHERE i.id_inventario = ?
                LIMIT 1
            SQL,
            [$idInventario]
        );

        return $rows[0] ?? null;
    }

    protected function findInventarioByProducto(object $connection, int $idProducto): ?object
    {
        $rows = $connection->select(
            <<<'SQL'
                SELECT
                    i.id_inventario,
                    i.id_area,
                    i.id_producto,
                    p.descripcion AS producto,
                    p.requiere_foto_producto_anterior,
                    a.descripcion_area
                FROM inventario i
                INNER JOIN productos p ON p.id_producto = i.id_producto
                LEFT JOIN area a ON a.id_area = i.id_area
                WHERE i.id_producto = ?
                ORDER BY i.id_inventario ASC
                LIMIT 1
            SQL,
            [$idProducto]
        );

        return $rows[0] ?? null;
    }


    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function storeFiles(int $registroId, array $items, string $baseDirectory = 'uploads/solicitudes'): array
    {
        $uploadedFiles = [];
        $directory = trim($baseDirectory, '/').'/'.$registroId;
        $disk = Storage::disk('public');

        foreach ($items as $item) {
            if (! ($item['file'] instanceof UploadedFile)) {
                continue;
            }

            $filename = $this->buildSafeFileName($registroId, (int) $item['id_inventario'], $item['file']);
            $path = trim($directory, '/').'/'.$filename;
            $contents = file_get_contents($item['file']->getRealPath());

            if ($contents === false) {
                throw new DomainException("No se pudo leer el archivo del inventario {$item['id_inventario']}.");
            }

            $stored = $disk->put($path, $contents);

            if (! $stored) {
                throw new DomainException("No se pudo guardar el archivo del inventario {$item['id_inventario']}.");
            }

            $uploadedFiles[] = [
                'id_inventario' => (int) $item['id_inventario'],
                'id_producto' => (int) $item['id_producto'],
                'path' => $path,
                'url' => $this->buildPublicUrl($path),
                'original_name' => $item['file']->getClientOriginalName(),
            ];
        }

        return $uploadedFiles;
    }

    /**
     * @param  array<int, array<string, mixed>>  $uploadedFiles
     */
    protected function syncDetalleImagenes(object $connection, int $solicitudId, array $uploadedFiles): void
    {
        if ($uploadedFiles === []) {
            return;
        }

        $connection->transaction(function () use ($connection, $solicitudId, $uploadedFiles): void {
            foreach ($uploadedFiles as $uploadedFile) {
                $connection->table('solicitud_detalles')
                    ->where('id_solicitud', $solicitudId)
                    ->where('id_inventario', (int) $uploadedFile['id_inventario'])
                    ->update([
                        'ruta_imagen' => $uploadedFile['path'],
                        'url_imagen' => $uploadedFile['url'] ?? null,
                    ]);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $uploadedFiles
     */
    protected function deleteStoredFiles(array $uploadedFiles): void
    {
        if ($uploadedFiles === []) {
            return;
        }

        $disk = Storage::disk('public');

        foreach ($uploadedFiles as $uploadedFile) {
            $path = $uploadedFile['path'] ?? null;

            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, int>  $areaIds
     * @param  array<int, array<string, mixed>>  $uploadedFiles
     */
    protected function sendNotifications(object $connection, array $data, object $solicitante, array $items, array $areaIds, int $solicitudId, array $uploadedFiles): void
    {
        $recipientEmails = $this->resolveRecipientEmails($connection, $areaIds);

        if ($recipientEmails === []) {
            Log::warning('solicitudes.registrar_completa.sin_destinatarios', [
                'id_solicitud' => $solicitudId,
                'areas' => $areaIds,
            ]);

            return;
        }

        $replyToEmail = $this->sanitizeEmail((string) ($solicitante->email ?? ''));
        $replyToName = trim((string) ($solicitante->firstname ?? '').' '.(string) ($solicitante->lastname ?? ''));

        $mailable = new SolicitudRegistradaMail(
            ticket: $this->formatTicket($solicitudId),
            solicitante: [
                'staff_id' => (int) $solicitante->staff_id,
                'firstname' => $solicitante->firstname ?? null,
                'lastname' => $solicitante->lastname ?? null,
                'email' => $replyToEmail,
                'dept_id' => isset($solicitante->id_departamento) ? (int) $solicitante->id_departamento : null,
                'id_departamento' => isset($solicitante->id_departamento) ? (int) $solicitante->id_departamento : null,
            ],
            areas: $areaIds,
            items: $items,
            isPurchaseOrder: false,
            justificacion: $data['justificacion'] ?? null,
            uploadedFiles: $uploadedFiles,
            ccRecipients: $this->resolveCcRecipients(),
            replyToEmail: $replyToEmail,
            replyToName: $replyToName !== '' ? $replyToName : null
        );

        try {
            Mail::mailer('smtp_test')->to($recipientEmails)->send($mailable);
        } catch (Throwable $e) {
            Log::warning('solicitudes.registrar_completa.mail_error', [
                'id_solicitud' => $solicitudId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $areaIds
     * @return array<int, string>
     */
    protected function resolveRecipientEmails(object $connection, array $areaIds): array
    {
        if ($areaIds === []) {
            return [];
        }

        // Solo destinatarios definidos en .env, aplicando logica por area.
        $emailsFijos = collect([]);

        if (
            in_array(self::AREA_LOGISTICA, $areaIds, true)
            || in_array(self::AREA_LOGISTICA_SECUNDARIA, $areaIds, true)
        ) {
            $emailsFijos->push(config('services.solicitudes.correo_logistica'));
        }

        if (in_array(self::AREA_INTERNO_RRHH, $areaIds, true)) {
            $emailsFijos->push(config('services.solicitudes.correo_soma'));
        }

        $emailsFijos = $emailsFijos
            ->map(fn ($email) => $this->sanitizeEmail((string) $email))
            ->filter()
            ->values();

        return $emailsFijos
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveCcRecipients(): array
    {
        return collect((array) config('services.solicitudes.smtp_always_cc', []))
            ->map(fn ($email) => $this->sanitizeEmail((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function rollbackSolicitud(object $connection, int $solicitudId): void
    {
        try {
            $connection->transaction(function () use ($connection, $solicitudId): void {
                $connection->table('solicitud_flujo_aprobaciones')
                    ->where('id_solicitud', $solicitudId)
                    ->delete();

                $connection->table('solicitud_areas')
                    ->where('id_solicitud', $solicitudId)
                    ->delete();

                $connection->table('solicitud_detalles')
                    ->where('id_solicitud', $solicitudId)
                    ->delete();

                $connection->table('solicitudes')
                    ->where('id_solicitud', $solicitudId)
                    ->delete();
            });
        } catch (Throwable $e) {
            Log::error('solicitudes.registrar_completa.rollback_error', [
                'id_solicitud' => $solicitudId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeList(array $data, string $key): array
    {
        $value = Arr::get($data, $key, []);

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    protected function normalizeListPreserveKeys(array $data, string $key): array
    {
        $value = Arr::get($data, $key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    protected function resolveDetalleIdsByInventario(object $connection, int $solicitudId, array $items): array
    {
        $inventarioIds = collect($items)
            ->pluck('id_inventario')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($inventarioIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($inventarioIds), '?'));
        $rows = $connection->select(
            <<<SQL
                SELECT id_detalle_solicitud, id_inventario
                FROM solicitud_detalles
                WHERE id_solicitud = ?
                  AND id_inventario IN ({$placeholders})
            SQL,
            array_merge([$solicitudId], $inventarioIds)
        );

        return collect($rows)
            ->mapWithKeys(function ($row): array {
                return [(int) $row->id_inventario => (int) $row->id_detalle_solicitud];
            })
            ->all();
    }

    protected function findSolicitudById(object $connection, int $idSolicitud): ?object
    {
        $rows = $connection->select(
            'SELECT id_solicitud
             FROM solicitudes
             WHERE id_solicitud = ?
             LIMIT 1',
            [$idSolicitud]
        );

        return $rows[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDetallesUpdatePayload(array $data, array $files): array
    {
        $detalles = Arr::get($data, 'detalles', []);
        if (! is_array($detalles)) {
            return [];
        }

        $filesDetalles = Arr::get($files, 'detalles', []);

        $normalized = [];
        foreach ($detalles as $index => $detalle) {
            if (! is_array($detalle)) {
                continue;
            }

            $file = Arr::get($filesDetalles, "{$index}.imagen");
            if (! $file instanceof UploadedFile) {
                $file = Arr::get($filesDetalles, "{$index}.foto");
            }
            if (! $file instanceof UploadedFile) {
                $file = Arr::get($filesDetalles, "{$index}.image");
            }

            $normalized[] = [
                'id_detalle_solicitud' => isset($detalle['id_detalle_solicitud']) && (int) $detalle['id_detalle_solicitud'] > 0
                    ? (int) $detalle['id_detalle_solicitud']
                    : null,
                'id_inventario' => (int) ($detalle['id_inventario'] ?? 0),
                'cantidad_solicitada' => (int) ($detalle['cantidad_solicitada'] ?? 0),
                'area_id' => isset($detalle['area_id']) && (int) $detalle['area_id'] > 0
                    ? (int) $detalle['area_id']
                    : null,
                'comentario' => array_key_exists('comentario', $detalle) ? (string) $detalle['comentario'] : null,
                'quitar_imagen' => filter_var($detalle['quitar_imagen'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'file' => $file instanceof UploadedFile ? $file : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    protected function normalizeDeleteIds(array $data): array
    {
        $ids = Arr::get($data, 'detalles_eliminados', []);
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function storeDetalleFile(int $idSolicitud, int $idDetalle, int $idInventario, UploadedFile $file): array
    {
        $directory = trim('uploads/solicitudes/'.$idSolicitud.'/detalles/'.$idDetalle, '/');
        $disk = Storage::disk('public');
        $filename = $this->buildSafeFileName($idSolicitud, $idInventario, $file);
        $path = $directory.'/'.$filename;
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new DomainException("No se pudo leer la imagen del detalle {$idDetalle}.");
        }

        $stored = $disk->put($path, $contents);
        if (! $stored) {
            throw new DomainException("No se pudo guardar la imagen del detalle {$idDetalle}.");
        }

        return [
            'id_detalle_solicitud' => $idDetalle,
            'id_inventario' => $idInventario,
            'path' => $path,
            'url' => $this->buildPublicUrl($path),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    protected function getDetallesBySolicitudId(object $connection, int $idSolicitud)
    {
        return collect($connection->select(
            <<<SQL
                SELECT
                    d.id_detalle_solicitud,
                    d.id_solicitud,
                    d.id_inventario,
                    d.cantidad_solicitada,
                    d.comentario,
                    d.area_id,
                    d.ruta_imagen,
                    d.url_imagen,
                    d.id_estado_detalle,
                    i.id_producto,
                    p.descripcion AS producto,
                    a.descripcion_area AS area
                FROM solicitud_detalles d
                LEFT JOIN inventario i ON i.id_inventario = d.id_inventario
                LEFT JOIN productos p ON p.id_producto = i.id_producto
                LEFT JOIN area a ON a.id_area = d.area_id
                WHERE d.id_solicitud = ?
                ORDER BY COALESCE(NULLIF(d.area_id, 0), i.id_area) ASC, p.descripcion ASC
            SQL,
            [$idSolicitud]
        ));
    }

    protected function buildDetalleResponsePayload(object $row): array
    {
        $urlImagen = $row->url_imagen ?? null;
        if (! $urlImagen && ! empty($row->ruta_imagen)) {
            $urlImagen = $this->buildPublicUrl((string) $row->ruta_imagen);
        }

        return [
            'id_detalle_solicitud' => (int) $row->id_detalle_solicitud,
            'id_solicitud' => (int) $row->id_solicitud,
            'id_inventario' => (int) $row->id_inventario,
            'id_producto' => $row->id_producto !== null ? (int) $row->id_producto : null,
            'producto' => $row->producto ?? null,
            'area_id' => $row->area_id !== null ? (int) $row->area_id : null,
            'area' => $row->area ?? null,
            'cantidad_solicitada' => $row->cantidad_solicitada !== null ? (int) $row->cantidad_solicitada : null,
            'comentario' => $row->comentario ?? null,
            'id_estado_detalle' => $row->id_estado_detalle !== null ? (int) $row->id_estado_detalle : null,
            'ruta_imagen' => $row->ruta_imagen ?? null,
            'url_imagen' => $urlImagen,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function buildNoValidProductsMessage(array $data): string
    {
        $diagnosticos = [];

        foreach (self::CATEGORIES as $category) {
            $inventarios = $this->normalizeList($data, "id_producto_{$category}");
            $cantidades = $this->normalizeList($data, "cantidad_{$category}");
            $validas = 0;
            $invalidas = [];

            foreach ($inventarios as $index => $inventarioRaw) {
                $idInventario = (int) $inventarioRaw;
                $cantidad = (int) ($cantidades[$index] ?? 0);

                if ($idInventario > 0 && $cantidad > 0) {
                    $validas++;

                    continue;
                }

                $motivos = [];
                if ($idInventario <= 0) {
                    $motivos[] = 'id<=0';
                }
                if ($cantidad <= 0) {
                    $motivos[] = 'cantidad<=0';
                }

                $invalidas[] = sprintf('#%d[%s]', $index, implode(',', $motivos));
                if (count($invalidas) >= 3) {
                    break;
                }
            }

            $diagnosticos[] = sprintf(
                '%s(ids=%d,validas=%d,invalidas=%s)',
                $category,
                count($inventarios),
                $validas,
                $invalidas === [] ? '0' : implode(';', $invalidas)
            );
        }

        $keysRecibidas = implode(',', array_keys($data));
        return sprintf(
            'No se encontraron productos validos para registrar. Diagnostico: %s. keys_recibidas=%s.',
            implode(' | ', $diagnosticos),
            $keysRecibidas === '' ? 'ninguna' : $keysRecibidas
        );
    }

    protected function isSolicitanteDeLima(object $solicitante): bool
    {
        return (int) ($solicitante->id_departamento ?? 0) === self::ID_DEPARTAMENTO_LIMA;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function resolveTipoSolicitud(array $items): string
    {
        $itemAreaIds = collect($items)
            ->pluck('id_area')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $isOnlyArea11 = $itemAreaIds !== []
            && collect($itemAreaIds)->every(fn (int $id): bool => $id === 11);

        if ($isOnlyArea11) {
            return self::TIPO_SOLICITUD_INTERNO;
        }

        return self::TIPO_SOLICITUD_MIXTO;
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function buildSafeFileName(int $solicitudId, int $idInventario, UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));

        return sprintf(
            'sol_%d_inv_%d_%s_%s.%s',
            $solicitudId,
            $idInventario,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );
    }

    protected function formatTicket(int $solicitudId): string
    {
        return 'SOL-'.str_pad((string) $solicitudId, 6, '0', STR_PAD_LEFT);
    }

    protected function buildPublicUrl(string $path): ?string
    {
        $appUrl = trim((string) config('app.url'), '/');

        if ($appUrl === '') {
            return null;
        }

        return $appUrl.'/storage/'.ltrim($path, '/');
    }
}
