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
            throw new DomainException('No se encontraron productos válidos para registrar.');
        }

        $areaIds = collect($items)
            ->pluck('id_area')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ((int) $data['es_pedido_compra'] === 1) {
            $areaIds[] = (int) config('services.solicitudes.area_compras_id', 7);
        }

        $areaIds = array_values(array_unique(array_map('intval', $areaIds)));
        $now = now();

        $solicitudId = $connection->transaction(function () use ($connection, $data, $items, $areaIds, $solicitante, $now): int {
            $solicitudId = (int) $connection->table('solicitudes')->insertGetId([
                'id_usuario_solicitante' => (int) $data['id_usuario_solicitante'],
                'id_area_origen' => (int) $solicitante->dept_id,
                'id_estado_general' => self::ESTADO_INICIAL,
                'fecha_registro' => $now,
                'prioridad' => $data['prioridad'] ?? 'Media',
                'fecha_necesaria' => $data['fecha_necesaria'] ?? null,
                'tipo_entrega_preferida' => $data['tipo_entrega_preferida'] ?? 'Directo',
                'id_direccion_entrega' => $data['id_direccion_entrega'] ?? null,
                'es_pedido_compra' => (int) ($data['es_pedido_compra'] ?? 0),
                'pedido_compra_estado' => (int) ($data['es_pedido_compra'] ?? 0),
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
                ];
            }

            $connection->table('solicitud_detalles')->insert($detalleRows);

            $areaRows = [];
            foreach ($areaIds as $areaId) {
                $areaRows[] = [
                    'id_solicitud' => $solicitudId,
                    'id_area' => (int) $areaId,
                    'id_estado_area' => self::ESTADO_INICIAL,
                    'fecha_recepcion' => $now,
                ];
            }

            $connection->table('solicitud_areas')->insert($areaRows);

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

        try {
            $uploadedFiles = $this->storeFiles($solicitudId, $items);
        } catch (Throwable $e) {
            $this->rollbackSolicitud($connection, $solicitudId);
            throw $e;
        }

        // $this->sendNotifications($connection, $data, $solicitante, $items, $areaIds, $solicitudId, $uploadedFiles);

        return [
            'ticket' => $this->formatTicket($solicitudId),
            'uploaded_files' => $uploadedFiles,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @return array<int, array<string, mixed>>
     */
    protected function collectItems(object $connection, array $data, array $files): array
    {
        $items = [];
        $seenInventarios = [];

        foreach (self::CATEGORIES as $category) {
            $inventarios = $this->normalizeList($data, "id_producto_{$category}");
            $cantidades = $this->normalizeList($data, "cantidad_{$category}");
            $observaciones = $this->normalizeList($data, "observacion_{$category}");
            $fotos = $this->normalizeList($files, "foto_{$category}");

            foreach ($inventarios as $index => $inventarioRaw) {
                $idInventario = (int) $inventarioRaw;
                $cantidad = (int) ($cantidades[$index] ?? 0);

                if ($idInventario <= 0 || $cantidad <= 0) {
                    continue;
                }

                if (isset($seenInventarios[$idInventario])) {
                    throw new DomainException("El inventario {$idInventario} está duplicado en la solicitud.");
                }

                $inventario = $this->findInventario($connection, $idInventario);

                if ($inventario === null) {
                    throw new DomainException("No se encontró el inventario {$idInventario}.");
                }

                if (empty($inventario->id_area)) {
                    throw new DomainException("El inventario {$idInventario} no tiene un área asociada.");
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
                    'id_area' => (int) $inventario->id_area,
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

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function storeFiles(int $solicitudId, array $items): array
    {
        $uploadedFiles = [];
        $directory = 'uploads/solicitudes/'.$solicitudId;
        $disk = Storage::disk('public');

        foreach ($items as $item) {
            if (! ($item['file'] instanceof UploadedFile)) {
                continue;
            }

            $filename = $this->buildSafeFileName($solicitudId, (int) $item['id_inventario'], $item['file']);
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
                'path' => $path,
                'original_name' => $item['file']->getClientOriginalName(),
            ];
        }

        return $uploadedFiles;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, int>  $areaIds
     * @param  array<int, array<string, mixed>>  $uploadedFiles
     */
    protected function sendNotifications(object $connection, array $data, object $solicitante, array $items, array $areaIds, int $solicitudId, array $uploadedFiles): void
    {
        $recipientEmails = $this->resolveRecipientEmails($connection, (int) $data['es_pedido_compra'], $areaIds);

        if ($recipientEmails === []) {
            Log::warning('solicitudes.registrar_completa.sin_destinatarios', [
                'id_solicitud' => $solicitudId,
                'es_pedido_compra' => (int) $data['es_pedido_compra'],
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
            isPurchaseOrder: (int) $data['es_pedido_compra'] === 1,
            justificacion: $data['justificacion'] ?? null,
            uploadedFiles: $uploadedFiles,
            ccRecipients: $this->resolveCcRecipients(),
            replyToEmail: $replyToEmail,
            replyToName: $replyToName !== '' ? $replyToName : null
        );

        try {
            Mail::to($recipientEmails)->send($mailable);
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
    protected function resolveRecipientEmails(object $connection, int $esPedidoCompra, array $areaIds): array
    {
        if ($esPedidoCompra === 1) {
            return array_values(array_filter([
                $this->sanitizeEmail((string) config('services.solicitudes.pedido_compra_notify_email')),
            ]));
        }

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
}
