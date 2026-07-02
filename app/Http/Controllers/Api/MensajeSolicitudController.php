<?php

namespace App\Http\Controllers\Api;

use App\Events\MensajeSolicitudEnviado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMensajeSolicitudRequest;
use App\Models\MensajeSolicitud;
use App\Models\UserFcmToken;
use App\Services\FcmService;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MensajeSolicitudController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected FcmService $fcmService
    ) {}

    public function index(int $idSolicitud): JsonResponse
    {
        try {
            if (! $this->solicitudExists($idSolicitud)) {
                return $this->errorResponse('Solicitud no encontrada.', 404);
            }

            $rows = $this->getConnection()->select(
                'SELECT m.id_mensaje, m.id_solicitud, m.staff_id, m.mensaje, m.tipo, m.archivo_url, m.archivo_nombre, m.archivo_mime, m.archivo_size, m.leido, m.created_at, os.firstname, os.lastname
                 FROM mensajes_solicitud m
                 LEFT JOIN ost_staff os ON os.staff_id = m.staff_id
                 WHERE m.id_solicitud = ?
                 ORDER BY m.created_at ASC, m.id_mensaje ASC',
                [$idSolicitud]
            );

            $payload = collect($rows)->map(fn (object $row): array => $this->mapMensajeRow($row))->values()->all();

            return $this->successResponse($payload, 'Mensajes consultados correctamente.');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron consultar los mensajes.', 500);
        }
    }

    public function store(StoreMensajeSolicitudRequest $request, int $idSolicitud): JsonResponse
    {
        $validated = $request->validated();

        try {
            if (! $this->solicitudExists($idSolicitud)) {
                return $this->errorResponse('Solicitud no encontrada.', 404);
            }

            if (! $this->staffExists((int) $validated['staff_id'])) {
                return $this->errorResponse('Staff no encontrado.', 422);
            }

            $archivoData = $this->storeArchivo($request, $idSolicitud);
            $mensaje = MensajeSolicitud::query()->create([
                'id_solicitud' => $idSolicitud,
                'staff_id' => (int) $validated['staff_id'],
                'mensaje' => $validated['mensaje'] ?? null,
                'tipo' => $validated['tipo'] ?? 'texto',
                'archivo_url' => $archivoData['archivo_url'],
                'archivo_nombre' => $archivoData['archivo_nombre'],
                'archivo_mime' => $archivoData['archivo_mime'],
                'archivo_size' => $archivoData['archivo_size'],
                'leido' => (bool) ($validated['leido'] ?? false),
            ]);

            $row = $this->getConnection()->selectOne(
                'SELECT m.id_mensaje, m.id_solicitud, m.staff_id, m.mensaje, m.tipo, m.archivo_url, m.archivo_nombre, m.archivo_mime, m.archivo_size, m.leido, m.created_at, os.firstname, os.lastname
                 FROM mensajes_solicitud m
                 LEFT JOIN ost_staff os ON os.staff_id = m.staff_id
                 WHERE m.id_mensaje = ?
                 LIMIT 1',
                [$mensaje->id_mensaje]
            );

            $payload = $row ? $this->mapMensajeRow($row) : [
                'id_mensaje' => (int) $mensaje->id_mensaje,
                'id_solicitud' => $idSolicitud,
            ];

            broadcast(new MensajeSolicitudEnviado($idSolicitud, $payload));
            $this->sendFcmForReply(
                $idSolicitud,
                (int) $validated['staff_id'],
                $validated['mensaje'] ?? null
            );

            return $this->successResponse($payload, 'Mensaje enviado correctamente.', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo enviar el mensaje.', 500);
        }
    }

    public function markAsRead(Request $request, int $idSolicitud, int $idMensaje): JsonResponse
    {
        $validated = $request->validate([
            'leido' => ['required', 'boolean'],
        ]);

        try {
            $affected = $this->getConnection()->update(
                'UPDATE mensajes_solicitud
                 SET leido = ?
                 WHERE id_mensaje = ?
                   AND id_solicitud = ?',
                [
                    (bool) $validated['leido'],
                    $idMensaje,
                    $idSolicitud,
                ]
            );

            if ($affected === 0) {
                return $this->errorResponse('Mensaje no encontrado.', 404);
            }

            return $this->successResponse([
                'id_mensaje' => $idMensaje,
                'id_solicitud' => $idSolicitud,
                'leido' => (bool) $validated['leido'],
            ], 'Estado de lectura actualizado correctamente.');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo actualizar el estado de lectura.', 500);
        }
    }

    protected function getConnection(): ConnectionInterface
    {
        return DB::connection('mysql_external');
    }

    protected function solicitudExists(int $idSolicitud): bool
    {
        return $this->getConnection()->selectOne(
            'SELECT id_solicitud FROM solicitudes WHERE id_solicitud = ? LIMIT 1',
            [$idSolicitud]
        ) !== null;
    }

    protected function staffExists(int $staffId): bool
    {
        return $this->getConnection()->selectOne(
            'SELECT staff_id FROM ost_staff WHERE staff_id = ? LIMIT 1',
            [$staffId]
        ) !== null;
    }

    /**
     * @return array{archivo_url:?string,archivo_nombre:?string,archivo_mime:?string,archivo_size:?int}
     */
    protected function storeArchivo(StoreMensajeSolicitudRequest $request, int $idSolicitud): array
    {
        if (! $request->hasFile('archivo')) {
            return [
                'archivo_url' => null,
                'archivo_nombre' => null,
                'archivo_mime' => null,
                'archivo_size' => null,
            ];
        }

        $file = $request->file('archivo');
        $directory = 'uploads/solicitudes/'.$idSolicitud.'/mensajes';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $filename = sprintf(
            'msg_%d_%s_%s.%s',
            $idSolicitud,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );

        $storedPath = $file->storeAs($directory, $filename, 'public');

        return [
            'archivo_url' => $this->buildPublicUrl($storedPath),
            'archivo_nombre' => $file->getClientOriginalName(),
            'archivo_mime' => $file->getMimeType(),
            'archivo_size' => $file->getSize(),
        ];
    }

    protected function buildPublicUrl(string $path): ?string
    {
        $appUrl = trim((string) config('app.url'), '/');
        if ($appUrl === '') {
            return null;
        }

        return $appUrl.'/storage/'.ltrim($path, '/');
    }

    protected function mapMensajeRow(object $row): array
    {
        $fullName = trim(trim((string) ($row->firstname ?? '')).' '.trim((string) ($row->lastname ?? '')));

        return [
            'id_mensaje' => (int) $row->id_mensaje,
            'id_solicitud' => (int) $row->id_solicitud,
            'staff_id' => (int) $row->staff_id,
            'mensaje' => $row->mensaje ?? null,
            'tipo' => $row->tipo ?? null,
            'archivo_url' => $row->archivo_url ?? null,
            'archivo_nombre' => $row->archivo_nombre ?? null,
            'archivo_mime' => $row->archivo_mime ?? null,
            'archivo_size' => $row->archivo_size !== null ? (int) $row->archivo_size : null,
            'leido' => (bool) $row->leido,
            'created_at' => $row->created_at ?? null,
            'staff' => [
                'firstname' => $row->firstname ?? null,
                'lastname' => $row->lastname ?? null,
                'fullname' => $fullName !== '' ? $fullName : null,
            ],
        ];
    }

    protected function sendFcmForReply(int $idSolicitud, int $senderStaffId, ?string $mensajeTexto): void
    {
        try {
            $targetStaffIds = $this->resolveTargetStaffIds($idSolicitud, $senderStaffId);
            if ($targetStaffIds === []) {
                Log::info('FCM omitido: no hay destinatarios para respuesta de solicitud', [
                    'id_solicitud' => $idSolicitud,
                    'sender_staff_id' => $senderStaffId,
                ]);
                return;
            }

            $tokens = UserFcmToken::query()
                ->active()
                ->whereIn('staff_id', $targetStaffIds)
                ->pluck('token')
                ->map(fn ($token): string => (string) $token)
                ->filter(fn (string $token): bool => trim($token) !== '')
                ->values();

            if ($tokens->isEmpty()) {
                Log::info('FCM omitido: no hay tokens activos para respuesta de solicitud', [
                    'id_solicitud' => $idSolicitud,
                    'sender_staff_id' => $senderStaffId,
                    'target_staff_ids' => $targetStaffIds,
                ]);
                return;
            }

            $title = 'Nuevo mensaje en solicitud';
            $body = trim((string) $mensajeTexto) !== ''
                ? Str::limit(trim((string) $mensajeTexto), 120)
                : 'Tienes una nueva respuesta.';

            Log::info('FCM respuesta de solicitud preparada', [
                'id_solicitud' => $idSolicitud,
                'sender_staff_id' => $senderStaffId,
                'target_staff_ids' => $targetStaffIds,
                'tokens_count' => $tokens->count(),
                'mensaje_preview' => Str::limit((string) $body, 80),
            ]);

            foreach ($tokens as $token) {
                $result = $this->fcmService->sendToToken($token, $title, $body, [
                    'type' => 'solicitud_mensaje',
                    'id_solicitud' => (string) $idSolicitud,
                    'sender_staff_id' => (string) $senderStaffId,
                ]);

                if (($result['ok'] ?? false) === true) {
                    continue;
                }

                if (($result['invalid_token'] ?? false) === true) {
                    UserFcmToken::query()
                        ->where('token', $token)
                        ->update(['is_active' => false]);
                }

                Log::warning('Fallo envio FCM en respuesta de solicitud', [
                    'id_solicitud' => $idSolicitud,
                    'sender_staff_id' => $senderStaffId,
                    'status' => $result['status'] ?? null,
                    'message' => $result['message'] ?? null,
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, int>
     */
    protected function resolveTargetStaffIds(int $idSolicitud, int $senderStaffId): array
    {
        $solicitudOwnerId = $this->getConnection()->selectOne(
            'SELECT id_usuario_solicitante FROM solicitudes WHERE id_solicitud = ? LIMIT 1',
            [$idSolicitud]
        );

        $messageParticipants = $this->getConnection()->select(
            'SELECT DISTINCT staff_id FROM mensajes_solicitud WHERE id_solicitud = ?',
            [$idSolicitud]
        );

        $staffIds = [];

        if ($solicitudOwnerId !== null && isset($solicitudOwnerId->id_usuario_solicitante)) {
            $staffIds[] = (int) $solicitudOwnerId->id_usuario_solicitante;
        }

        foreach ($messageParticipants as $participant) {
            if (isset($participant->staff_id)) {
                $staffIds[] = (int) $participant->staff_id;
            }
        }

        return array_values(array_filter(array_unique($staffIds), fn (int $id): bool => $id > 0 && $id !== $senderStaffId));
    }
}
