<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SolicitudCompraWorkflowController extends Controller
{
    use ApiResponseTrait;
    private const STATE_PENDING_RRHH = 'pendiente_rrhh';
    private const STATE_PENDING_GERENCIA = 'pendiente_gerencia';
    private const STATE_APPROVED_FINAL = 'aprobada_final';
    private const STATE_REJECTED = 'rechazada';

    private const ACTION_SEND_TO_GERENCIA = 'validar_y_enviar_gerencia';
    private const ACTION_APPROVE_FINAL = 'aprobar_final';
    private const ACTION_REJECT = 'rechazar';

    private const WORKFLOW_STATES = [
        self::STATE_PENDING_RRHH,
        self::STATE_PENDING_GERENCIA,
        self::STATE_APPROVED_FINAL,
        self::STATE_REJECTED,
    ];

    private const FINAL_STATES = [
        self::STATE_APPROVED_FINAL,
        self::STATE_REJECTED,
    ];

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'solicitud_gasto_id' => ['nullable', 'integer', 'min:1'],
                'staff_id' => ['nullable', 'integer', 'min:1'],
                'id_area' => ['nullable', 'integer', 'min:1'],
                'workflow_state' => ['nullable', 'string', 'in:'.implode(',', self::WORKFLOW_STATES)],
            ]);

            $query = $this->getConnection()
                ->table('solicitudes_gasto as sg')
                ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'sg.staff_id')
                ->leftJoin('area as a', 'a.id_area', '=', 'sg.id_area')
                ->select([
                    'sg.id',
                    'sg.staff_id',
                    'sg.id_area',
                    'sg.motivo',
                    'sg.monto_estimado',
                    'sg.monto_real',
                    'sg.estado',
                    'sg.fecha_solicitud',
                    'sg.fecha_aprobacion',
                    'sg.fecha_reembolso',
                    'os.username',
                    'os.firstname',
                    'os.lastname',
                    'a.descripcion_area as area',
                ]);

            if (isset($validated['solicitud_gasto_id'])) {
                $query->where('sg.id', (int) $validated['solicitud_gasto_id']);
            }

            if (isset($validated['staff_id'])) {
                $query->where('sg.staff_id', (int) $validated['staff_id']);
            }

            if (isset($validated['id_area'])) {
                $query->where('sg.id_area', (int) $validated['id_area']);
            }

            $rows = $query
                ->orderByDesc('sg.id')
                ->get();

            $solicitudIds = $rows->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            $detallesPorSolicitud = $this->getDetallesPorSolicitudIds($solicitudIds);

            $payload = $rows->map(function (object $row) use ($detallesPorSolicitud): array {
                $workflowState = $this->normalizeWorkflowState($row->estado ?? null);
                $solicitudId = (int) $row->id;

                return [
                    'id' => $solicitudId,
                    'workflow_state' => $workflowState,
                    'workflow_allowed_actions' => $this->allowedActionsForState($workflowState),
                    'solicitud_gasto' => [
                        'id' => $solicitudId,
                        'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null,
                        'id_area' => $row->id_area !== null ? (int) $row->id_area : null,
                        'solicitante' => $this->formatStaffFullName($row),
                        'username' => $row->username ?? null,
                        'area' => $row->area ?? null,
                        'motivo' => $row->motivo ?? null,
                        'monto_estimado' => $row->monto_estimado !== null ? (float) $row->monto_estimado : null,
                        'monto_real' => $row->monto_real !== null ? (float) $row->monto_real : null,
                        'estado' => $row->estado ?? null,
                        'fecha_solicitud' => $row->fecha_solicitud ?? null,
                        'fecha_aprobacion' => $row->fecha_aprobacion ?? null,
                        'fecha_reembolso' => $row->fecha_reembolso ?? null,
                    ],
                    'detalles' => $detallesPorSolicitud[$solicitudId] ?? [],
                ];
            });

            if (isset($validated['workflow_state'])) {
                $payload = $payload->filter(
                    fn (array $item): bool => $item['workflow_state'] === $validated['workflow_state']
                );
            }

            return $this->successResponse(
                $payload->values()->all(),
                'Solicitudes de compra RRHH consultadas correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron consultar las solicitudes de compra RRHH.', 500);
        }
    }

    public function enviarGerencia(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comentario' => ['nullable', 'string', 'max:1000'],
            'staff_id' => ['nullable', 'integer', 'min:1', 'exists:mysql_external.ost_staff,staff_id'],
        ]);

        $user = $request->user();
      

        $connection = $this->getConnection();

        try {
            $result = $connection->transaction(function () use ($connection, $id, $validated, $user): array {
                $solicitud = $connection->table('solicitudes_gasto')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $solicitud) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'message' => 'Solicitud de compra no encontrada.',
                    ];
                }

                $currentState = $this->normalizeWorkflowState($solicitud->estado ?? null);
                if ($currentState !== self::STATE_PENDING_RRHH) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'Transicion invalida. Solo se puede enviar a gerencia desde pendiente_rrhh.',
                    ];
                }

                 $this->sendGerenciaNotification(
                     $id,
                     $validated['comentario'] ?? null,
                     $user->name ?? null
                 );

                $connection->table('solicitudes_gasto')
                    ->where('id', $id)
                    ->update([
                        'estado' => self::STATE_PENDING_GERENCIA,
                        'estado_id' => 8,
                    ]);

                $this->insertSeguimiento(
                    $connection,
                    $id,
                    $currentState,
                    self::STATE_PENDING_GERENCIA,
                    $validated['comentario'] ?? null,
                    $this->resolveActorStaffId($validated, (int) $solicitud->staff_id)
                );

                return [
                    'ok' => true,
                    'data' => [
                        'id' => $id,
                        'previous_state' => $currentState,
                        'workflow_state' => self::STATE_PENDING_GERENCIA,
                        'action' => self::ACTION_SEND_TO_GERENCIA,
                    ],
                ];
            });

            if (! $result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse(
                $result['data'],
                'Solicitud enviada a gerencia correctamente'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo enviar la solicitud a gerencia: '.$e->getMessage(), 500);
        }
    }

    public function aprobarFinal(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comentario' => ['nullable', 'string', 'max:1000'],
            'staff_id' => ['nullable', 'integer', 'min:1', 'exists:mysql_external.ost_staff,staff_id'],
        ]);

        $user = $request->user();
        if (! $this->isGerenciaUser($user)) {
            return $this->errorResponse('No autorizado. Solo gerencia puede aprobar final.', 403);
        }

        $connection = $this->getConnection();

        try {
            $result = $connection->transaction(function () use ($connection, $id, $validated, $user): array {
                $solicitud = $connection->table('solicitudes_gasto')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $solicitud) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'message' => 'Solicitud de compra no encontrada.',
                    ];
                }

                $currentState = $this->normalizeWorkflowState($solicitud->estado ?? null);
                if ($currentState !== self::STATE_PENDING_GERENCIA) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'Transicion invalida. Solo se puede aprobar final desde pendiente_gerencia.',
                    ];
                }

                $connection->table('solicitudes_gasto')
                    ->where('id', $id)
                    ->update([
                        'estado' => self::STATE_APPROVED_FINAL,
                        'fecha_aprobacion' => now(),
                    ]);

                $this->insertSeguimiento(
                    $connection,
                    $id,
                    $currentState,
                    self::STATE_APPROVED_FINAL,
                    $validated['comentario'] ?? null,
                    $this->resolveActorStaffId($validated, (int) $solicitud->staff_id)
                );

                return [
                    'ok' => true,
                    'data' => [
                        'id' => $id,
                        'previous_state' => $currentState,
                        'workflow_state' => self::STATE_APPROVED_FINAL,
                        'action' => self::ACTION_APPROVE_FINAL,
                    ],
                ];
            });

            if (! $result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse(
                $result['data'],
                'Solicitud aprobada final correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo aprobar final la solicitud.', 500);
        }
    }

    public function rechazarRrhh(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comentario' => ['nullable', 'string', 'max:1000'],
            'staff_id' => ['nullable', 'integer', 'min:1', 'exists:mysql_external.ost_staff,staff_id'],
        ]);

        $user = $request->user();
        $connection = $this->getConnection();

        try {
            $result = $connection->transaction(function () use ($connection, $id, $validated, $user): array {
                $solicitud = $connection->table('solicitudes_gasto')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $solicitud) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'message' => 'Solicitud de compra no encontrada.',
                    ];
                }

                $currentState = $this->normalizeWorkflowState($solicitud->estado ?? null);
                if (in_array($currentState, self::FINAL_STATES, true)) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'La solicitud ya esta en estado final y no acepta mas transiciones.',
                    ];
                }

                
                // if (
                //     $currentState === self::STATE_PENDING_RRHH
                //     && ! $this->isRrhhUser($user)
                // ) {
                //     return [
                //         'ok' => false,
                //         'status' => 403,
                //         'message' => 'No autorizado. Desde pendiente_rrhh solo RRHH puede rechazar.',
                //     ];
                // }

               

                if (
                    $currentState === self::STATE_PENDING_GERENCIA
                    && ! $this->isGerenciaUser($user)
                ) {
                    return [
                        'ok' => false,
                        'status' => 403,
                        'message' => 'No autorizado. Desde pendiente_gerencia solo gerencia puede rechazar.',
                    ];
                }

                $connection->table('solicitudes_gasto')
                    ->where('id', $id)
                    ->update([
                        'estado' => self::STATE_REJECTED,
                        'estado_id' => 10,
                    ]);

                $this->insertSeguimiento(
                    $connection,
                    $id,
                    $currentState,
                    self::STATE_REJECTED,
                    $validated['comentario'] ?? null,
                    $this->resolveActorStaffId($validated, (int) $solicitud->staff_id)
                );

                return [
                    'ok' => true,
                    'data' => [
                        'id' => $id,
                        'previous_state' => $currentState,
                        'workflow_state' => self::STATE_REJECTED,
                        'action' => self::ACTION_REJECT,
                        'comentario' => $validated['comentario'] ?? null,
                    ],
                ];
            });

            if (! $result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse(
                $result['data'],
                'Solicitud rechazada correctamente'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo rechazar la solicitud.', 500);
        }
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        return $this->rechazarRrhh($request, $id);
    }

    /**
     * @param  array<int, int>  $solicitudIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function getDetallesPorSolicitudIds(array $solicitudIds): array
    {
        if ($solicitudIds === []) {
            return [];
        }

        $rows = $this->getConnection()
            ->table('solicitud_gasto_detalles as d')
            ->leftJoin('productos as p', 'p.id_producto', '=', 'd.id_producto')
            ->whereIn('d.solicitud_gasto_id', $solicitudIds)
            ->select([
                'd.id',
                'd.solicitud_gasto_id',
                'd.id_producto',
                'd.cantidad',
                'd.precio_estimado',
                'd.precio_real',
                'd.descripcion_adicional',
                'd.ruta_imagen',
                'p.codigo_producto',
                'p.descripcion as producto_descripcion',
            ])
            ->orderBy('d.id')
            ->get();

        return $rows
            ->groupBy(fn (object $row): int => (int) $row->solicitud_gasto_id)
            ->map(fn ($items): array => collect($items)
                ->map(function (object $row): array {
                    return [
                        'id' => (int) $row->id,
                        'solicitud_gasto_id' => (int) $row->solicitud_gasto_id,
                        'id_producto' => $row->id_producto !== null ? (int) $row->id_producto : null,
                        'cantidad' => $row->cantidad !== null ? (int) $row->cantidad : null,
                        'precio_estimado' => $row->precio_estimado !== null ? (float) $row->precio_estimado : null,
                        'precio_real' => $row->precio_real !== null ? (float) $row->precio_real : null,
                        'descripcion_adicional' => $row->descripcion_adicional ?? null,
                        'ruta_imagen' => $row->ruta_imagen ?? null,
                        'producto' => [
                            'codigo_producto' => $row->codigo_producto ?? null,
                            'descripcion' => $row->producto_descripcion ?? null,
                        ],
                    ];
                })
                ->values()
                ->all())
            ->all();
    }

    protected function insertSeguimiento(
        object $connection,
        int $solicitudId,
        string $estadoAnterior,
        string $estadoNuevo,
        ?string $comentario,
        ?int $staffId
    ): void {
        $connection->table('seguimientos_solicitud_gasto')->insert([
            'solicitud_gasto_id' => $solicitudId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'comentario' => $comentario,
            'staff_id' => $staffId,
            'fecha' => now(),
        ]);
    }

    protected function sendGerenciaNotification(int $solicitudId, ?string $comentario, ?string $actorName): void
    {
        $to = $this->resolveGerenciaEmails();
        if ($to === []) {
            throw new RuntimeException('No hay correos de gerencia configurados para notificar.');
        }

        $solicitud = $this->getConnection()
            ->table('solicitudes_gasto as sg')
            ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'sg.staff_id')
            ->leftJoin('area as a', 'a.id_area', '=', 'sg.id_area')
            ->where('sg.id', $solicitudId)
            ->select([
                'sg.id',
                'sg.motivo',
                'sg.monto_estimado',
                'sg.fecha_solicitud',
                'sg.staff_id',
                'os.username',
                'os.firstname',
                'os.lastname',
                'a.descripcion_area as area',
            ])
            ->first();

        if (! $solicitud) {
            throw new RuntimeException('No se pudo construir el correo para gerencia: solicitud no encontrada.');
        }

        $solicitanteNombre = $this->formatStaffFullName($solicitud) ?? 'N/A';
        $motivo = trim((string) ($solicitud->motivo ?? ''));
        $motivoTexto = $motivo !== '' ? $motivo : 'Solicitud de botas';
        if (mb_strtolower($motivoTexto) === 'solicitud de botas: botas') {
            $motivoTexto = 'Solicitud de compra de botas de seguridad';
        }
        $subject = 'Solicitud de compra de botas - '.$solicitanteNombre;
        $imagenes = $this->resolveSolicitudImagenes($solicitudId);
        $montoEstimado = $solicitud->monto_estimado !== null ? (float) $solicitud->monto_estimado : null;
        $derivadoPor = 'Marjorie Alexandra Osorio';

        $escape = static fn (?string $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $comentarioTexto = trim((string) $comentario);
        $montoTexto = ($montoEstimado !== null && $montoEstimado > 0)
            ? 'S/ '.number_format($montoEstimado, 2, '.', '')
            : null;

        $rowsHtml = ''
            .'<tr><td style="padding:8px 0;color:#6b7280;width:180px;">Solicitante</td><td style="padding:8px 0;color:#111827;">'.$escape($solicitanteNombre).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Area</td><td style="padding:8px 0;color:#111827;">'.$escape((string) ($solicitud->area ?? 'N/A')).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Motivo</td><td style="padding:8px 0;color:#111827;">'.$escape($motivoTexto).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Fecha solicitud</td><td style="padding:8px 0;color:#111827;">'.$escape((string) ($solicitud->fecha_solicitud ?? 'N/A')).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Derivado por</td><td style="padding:8px 0;color:#111827;">'.$escape($derivadoPor).'</td></tr>';

        if ($montoTexto !== null) {
            $rowsHtml .= '<tr><td style="padding:8px 0;color:#6b7280;">Monto estimado</td><td style="padding:8px 0;color:#111827;">'.$escape($montoTexto).'</td></tr>';
        }

        if ($comentarioTexto !== '') {
            $rowsHtml .= '<tr><td style="padding:8px 0;color:#6b7280;">Comentario RRHH</td><td style="padding:8px 0;color:#111827;">'.$escape($comentarioTexto).'</td></tr>';
        }

        $imagenesHtml = '';
        if ($imagenes !== []) {
            $items = '';
            foreach ($imagenes as $imagen) {
                $label = $escape($imagen['label']);
                $url = $imagen['url'] !== null ? $escape($imagen['url']) : null;
                $items .= '<li style="margin:0 0 8px 0;color:#111827;">'
                    .$label
                    .($url !== null ? ' - <a href="'.$url.'" style="color:#2563eb;text-decoration:none;">Ver imagen</a>' : '')
                    .'</li>';
            }

            $imagenesHtml = ''
                .'<div style="margin-top:20px;">'
                .'<div style="font-size:14px;font-weight:600;color:#111827;margin-bottom:8px;">Botas usadas adjuntas</div>'
                .'<ul style="padding-left:18px;margin:0;">'.$items.'</ul>'
                .'</div>';
        }

        $gestionComprasUrl = 'https://osticket.cechriza.com/system/gestion_compras';
        $accionesHtml = ''
            .'<div style="margin-top:22px;padding:14px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;">'
            .'<div style="font-size:14px;font-weight:600;color:#111827;margin-bottom:10px;">Acciones en el sistema</div>'
            .'<a href="'.$escape($gestionComprasUrl).'" style="display:inline-block;padding:10px 16px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:8px;font-size:13px;font-weight:600;margin-right:8px;">Aprobar</a>'
            .'<a href="'.$escape($gestionComprasUrl).'" style="display:inline-block;padding:10px 16px;background:#dc2626;color:#ffffff;text-decoration:none;border-radius:8px;font-size:13px;font-weight:600;">Rechazar</a>'
            .'<div style="font-size:12px;color:#6b7280;margin-top:10px;">Ingresa al sistema para registrar la decision final.</div>'
            .'</div>';

        $htmlBody = ''
            .'<!doctype html><html><body style="margin:0;padding:0;background:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">'
            .'<tr><td align="center">'
            .'<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            .'<tr><td style="padding:20px 24px;background:#0f172a;color:#ffffff;">'
            .'<div style="font-size:20px;font-weight:700;">Solicitud de compra de botas</div>'
            .'<div style="font-size:13px;color:#cbd5e1;margin-top:4px;">Derivada a gerencia para decision final</div>'
            .'</td></tr>'
            .'<tr><td style="padding:24px;">'
            .'<p style="margin:0 0 16px 0;color:#374151;font-size:14px;line-height:1.5;">Se ha derivado una solicitud de compra para su revision y aprobacion final.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">'.$rowsHtml.'</table>'
            .$accionesHtml
            .$imagenesHtml
            .'</td></tr>'
            .'</table>'
            .'</td></tr></table>'
            .'</body></html>';

        $fromAddress = config('mail.from_test.address')
            ?: config('mail.mailers.smtp_test.username')
            ?: config('mail.from.address');
        $fromName = config('mail.from_test.name') ?: config('mail.from.name');

        $cc = $this->resolveGerenciaCcEmails();

        Mail::mailer('smtp_test')->send(
            [],
            [],
            function (Message $message) use ($to, $cc, $subject, $fromAddress, $fromName, $imagenes, $htmlBody): void {
                $message->to($to)->subject($subject);
                if ($cc !== []) {
                    $message->cc($cc);
                }
                $message->html($htmlBody);

                if (! empty($fromAddress)) {
                    $message->from((string) $fromAddress, $fromName ? (string) $fromName : null);
                }

                foreach ($imagenes as $imagen) {
                    $path = $imagen['path'];
                    $filename = $imagen['filename'];

                    if ($path !== null && Storage::disk('public')->exists($path)) {
                        $absolutePath = Storage::disk('public')->path($path);
                        $message->attach($absolutePath, ['as' => $filename]);
                    }
                }
            }
        );
    }

    /**
     * @return array<int, array{path:?string,filename:string,url:?string,label:string}>
     */
    protected function resolveSolicitudImagenes(int $solicitudId): array
    {
        $rows = $this->getConnection()
            ->table('solicitud_gasto_detalles as d')
            ->leftJoin('productos as p', 'p.id_producto', '=', 'd.id_producto')
            ->where('d.solicitud_gasto_id', $solicitudId)
            ->whereNotNull('d.ruta_imagen')
            ->where('d.ruta_imagen', '<>', '')
            ->select([
                'd.id',
                'd.ruta_imagen',
                'p.descripcion as producto_descripcion',
            ])
            ->orderBy('d.id')
            ->get();

        return $rows->map(function (object $row): array {
            $path = trim((string) ($row->ruta_imagen ?? ''));
            $path = $path !== '' ? ltrim($path, '/') : null;
            $extension = $path !== null ? pathinfo($path, PATHINFO_EXTENSION) : '';
            $extension = $extension !== '' ? '.'.strtolower((string) $extension) : '';
            $filename = 'solicitud_'.$row->id.$extension;
            $producto = trim((string) ($row->producto_descripcion ?? ''));

            return [
                'path' => $path,
                'filename' => $filename,
                'url' => $path !== null ? $this->buildPublicUrl($path) : null,
                'label' => $producto !== '' ? $producto : ('Detalle #'.$row->id),
            ];
        })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveGerenciaEmails(): array
    {
        $fromWorkflowSetting = $this->parseCsvValues((string) env('SOLICITUD_COMPRA_GERENCIA_EMAILS', ''));
        $fallback = trim((string) config('services.solicitudes.pedido_compra_notify_email'));

        $emails = collect($fromWorkflowSetting);
        if ($emails->isEmpty() && $fallback !== '') {
            $emails->push($fallback);
        }

        return $emails
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveGerenciaCcEmails(): array
    {
        return collect($this->parseCsvValues((string) config('services.solicitudes.gerencia_cc_emails', '')))
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function parseCsvValues(string $csv): array
    {
        return collect(explode(',', $csv))
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    protected function normalizeWorkflowState(?string $estado): string
    {
        $state = mb_strtolower(trim((string) $estado));

        return match ($state) {
            self::STATE_PENDING_RRHH, 'pendiente' => self::STATE_PENDING_RRHH,
            self::STATE_PENDING_GERENCIA => self::STATE_PENDING_GERENCIA,
            self::STATE_APPROVED_FINAL, 'aprobada', 'aprobado' => self::STATE_APPROVED_FINAL,
            self::STATE_REJECTED, 'rechazado' => self::STATE_REJECTED,
            default => self::STATE_PENDING_RRHH,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function allowedActionsForState(string $state): array
    {
        return match ($state) {
            self::STATE_PENDING_RRHH => [self::ACTION_SEND_TO_GERENCIA, self::ACTION_REJECT],
            self::STATE_PENDING_GERENCIA => [self::ACTION_APPROVE_FINAL, self::ACTION_REJECT],
            default => [],
        };
    }

    protected function resolveActorStaffId(array $validated, int $fallbackStaffId): int
    {
        if (isset($validated['staff_id']) && (int) $validated['staff_id'] > 0) {
            return (int) $validated['staff_id'];
        }

        return $fallbackStaffId;
    }

    protected function isRrhhUser(object $user): bool
    {
        return $this->matchesRoleOrEmail(
            $user,
            $this->parseCsvValues((string) env('SOLICITUD_COMPRA_RRHH_ROLES', 'RRHH,ADMIN,SUPER_ADMIN')),
            $this->parseCsvValues((string) env('SOLICITUD_COMPRA_RRHH_EMAILS', ''))
        );
    }

    protected function isGerenciaUser(object $user): bool
    {
        return $this->matchesRoleOrEmail(
            $user,
            $this->parseCsvValues((string) env('SOLICITUD_COMPRA_GERENCIA_ROLES', 'GERENCIA,ADMIN,SUPER_ADMIN')),
            $this->parseCsvValues((string) env('SOLICITUD_COMPRA_GERENCIA_EMAILS', ''))
        );
    }

    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $emails
     */
    protected function matchesRoleOrEmail(object $user, array $roles, array $emails): bool
    {
        $normalizedRoles = collect($roles)
            ->map(fn (string $role): string => mb_strtolower(trim($role)))
            ->filter(fn (string $role): bool => $role !== '')
            ->values();

        $normalizedEmails = collect($emails)
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(fn (string $email): bool => $email !== '')
            ->values();

        $userRole = mb_strtolower(trim((string) ($user->role ?? '')));
        if ($userRole !== '' && $normalizedRoles->contains($userRole)) {
            return true;
        }

        if (method_exists($user, 'getRoleNames')) {
            $spatieRoles = collect($user->getRoleNames())
                ->map(fn ($role): string => mb_strtolower(trim((string) $role)))
                ->filter(fn (string $role): bool => $role !== '')
                ->values();

            if ($spatieRoles->intersect($normalizedRoles)->isNotEmpty()) {
                return true;
            }
        }

        $userEmail = mb_strtolower(trim((string) ($user->email ?? '')));
        if ($userEmail !== '' && $normalizedEmails->contains($userEmail)) {
            return true;
        }

        return false;
    }

    protected function formatStaffFullName(object $row): ?string
    {
        $firstname = trim((string) ($row->firstname ?? ''));
        $lastname = trim((string) ($row->lastname ?? ''));
        $fullName = trim($firstname.' '.$lastname);

        return $fullName !== '' ? $fullName : null;
    }

    protected function getConnection()
    {
        return DB::connection('mysql_external');
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
