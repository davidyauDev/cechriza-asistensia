<?php

namespace App\Services;

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

    private const UBICACION_LIMA = 'LIMA';

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

        $esUbicacionLima = $this->isUbicacionLima($data);
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
                    'observacion' => $observacion !== '' ? 'Nota Usuario: '.$observacion : null,
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
                    'observacion_atencion' => $item['observacion'],
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
                SELECT staff_id, dept_id, firstname, lastname, email, role_id
                FROM ost_staff
                WHERE staff_id = ?
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
                'dept_id' => isset($solicitante->dept_id) ? (int) $solicitante->dept_id : null,
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
            // AI NO DESCOMENTAR POR FAVOR
            // Mail::to($recipientEmails)->send($mailable);
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

        $placeholders = implode(',', array_fill(0, count($areaIds), '?'));

        $rows = $connection->select(
            <<<SQL
                SELECT DISTINCT os.email
                FROM ost_staff os
                WHERE os.role_id = ?
                  AND os.dept_id IN ({$placeholders})
                  AND os.email IS NOT NULL
                  AND os.email <> ''
            SQL,
            array_merge([1], $areaIds)
        );

        return collect($rows)
            ->pluck('email')
            ->map(fn ($email) => $this->sanitizeEmail((string) $email))
            ->filter()
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function isUbicacionLima(array $data): bool
    {
        $ubicacion = strtoupper(trim((string) ($data['ubicacion'] ?? '')));

        return $ubicacion === self::UBICACION_LIMA;
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
