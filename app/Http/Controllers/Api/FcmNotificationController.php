<?php

namespace App\Http\Controllers\Api;

use App\Events\PedidoRecogidoEnviado;
use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use App\Services\FcmService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmNotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected FcmService $fcmService
    ) {}

    public function registerToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'nullable|integer|min:1',
            'user_id' => 'nullable|integer|min:1',
            'token' => 'required|string|min:1|max:255',
            'device_name' => 'nullable|string|max:150',
        ]);

        $authUser = $request->user();
        if (! $authUser) {
            return $this->errorResponse('No autenticado.', 401);
        }

        $staffId = (int) ($validated['staff_id'] ?? $validated['user_id'] ?? 0);
        if ($staffId <= 0) {
            return $this->errorResponse('staff_id es requerido.', 422);
        }

        try {
            DB::connection('mysql_external')->statement(
                <<<'SQL'
                INSERT INTO staff_fcm_tokens (staff_id, token, device_name, platform, is_active)
                VALUES (?, ?, ?, 'android', 1)
                ON DUPLICATE KEY UPDATE
                    staff_id = VALUES(staff_id),
                    device_name = VALUES(device_name),
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                SQL,
                [
                    $staffId,
                    trim((string) $validated['token']),
                    isset($validated['device_name']) ? trim((string) $validated['device_name']) : null,
                ]
            );

            Log::info('FCM token registrado', [
                'staff_id' => $staffId,
                'token_preview' => $this->maskToken((string) $validated['token']),
            ]);

            return $this->messageResponse('Token registrado');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo registrar el token FCM.', 500);
        }
    }

    public function sendTestNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'nullable|integer|min:1',
            'user_id' => 'nullable|integer|min:1',
            'title' => 'required|string|min:1|max:150',
            'body' => 'required|string|min:1|max:500',
            'data' => 'nullable|array',
        ]);

        $staffId = (int) ($validated['staff_id'] ?? $validated['user_id'] ?? 0);
        if ($staffId <= 0) {
            return $this->errorResponse('staff_id es requerido.', 422);
        }

        $authUser = $request->user();
        if (! $authUser) {
            return $this->errorResponse('No autenticado.', 401);
        }

        $isAdmin = in_array((string) $authUser->role, ['ADMIN', 'SUPER_ADMIN'], true);
        if (! $isAdmin) {
            return $this->errorResponse('Solo administradores pueden enviar pruebas FCM.', 403);
        }

        try {
            $tokens = UserFcmToken::query()
                ->active()
                ->where('staff_id', $staffId)
                ->pluck('token')
                ->map(fn ($token): string => (string) $token)
                ->filter(fn (string $token): bool => trim($token) !== '')
                ->values();

            if ($tokens->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'sent' => 0,
                    'failed' => 0,
                    'message' => 'Staff sin tokens FCM activos.',
                ]);
            }

            $sent = 0;
            $failed = 0;

            foreach ($tokens as $token) {
                $result = $this->fcmService->sendToToken(
                    $token,
                    (string) $validated['title'],
                    (string) $validated['body'],
                    $validated['data'] ?? []
                );

                Log::info('FCM envio resultado', [
                    'staff_id' => $staffId,
                    'token_preview' => $this->maskToken($token),
                    'status' => $result['status'] ?? null,
                    'ok' => $result['ok'] ?? false,
                    'invalid_token' => $result['invalid_token'] ?? false,
                    'message' => $result['message'] ?? null,
                ]);

                if (($result['ok'] ?? false) === true) {
                    $sent++;
                    continue;
                }

                $failed++;
                if (($result['invalid_token'] ?? false) === true) {
                    UserFcmToken::query()
                        ->where('token', $token)
                        ->update(['is_active' => false]);
                }
            }

            return response()->json([
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron enviar las notificaciones FCM.', 500);
        }
    }

    public function sendPedidoRecogidoNotification(Request $request, int $idSolicitud): JsonResponse
    {
        $validated = $request->validate([
            'mensaje' => 'nullable|string|min:1|max:500',
            'titulo' => 'nullable|string|min:1|max:150',
            'key' => 'nullable|string|min:1|max:255',
        ]);

        try {
            $configuredKey = (string) config('services.notifications.pedido_recogido_key', '');
            $providedKey = (string) ($request->header('X-Pedido-Key') ?? $validated['key'] ?? '');

            if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
                Log::warning('Pedido recogido: llave invalida', [
                    'id_solicitud' => $idSolicitud,
                    'has_configured_key' => $configuredKey !== '',
                    'provided_key_present' => trim($providedKey) !== '',
                ]);

                return $this->errorResponse('Llave invalida.', 401);
            }

            $solicitud = DB::connection('mysql_external')->selectOne(
                'SELECT id_solicitud, id_usuario_solicitante FROM solicitudes WHERE id_solicitud = ? LIMIT 1',
                [$idSolicitud]
            );

            if (! $solicitud || ! isset($solicitud->id_usuario_solicitante)) {
                return $this->errorResponse('Solicitud no encontrada.', 404);
            }

            $recipientStaffId = (int) $solicitud->id_usuario_solicitante;
            if ($recipientStaffId <= 0) {
                return $this->errorResponse('No se pudo resolver el destinatario de la solicitud.', 422);
            }

            $tokens = UserFcmToken::query()
                ->active()
                ->where('staff_id', $recipientStaffId)
                ->pluck('token')
                ->map(fn ($token): string => (string) $token)
                ->filter(fn (string $token): bool => trim($token) !== '')
                ->values();

            $payload = [
                'id_solicitud' => $idSolicitud,
                'recipient_staff_id' => $recipientStaffId,
                'titulo' => (string) ($validated['titulo'] ?? 'Pedido listo para recoger'),
                'mensaje' => (string) ($validated['mensaje'] ?? 'Tu pedido ya está listo para recoger.'),
                'tokens_count' => $tokens->count(),
                'sent_at' => now()->toISOString(),
            ];

            broadcast(new PedidoRecogidoEnviado($payload));

            if ($tokens->isEmpty()) {
                Log::info('Pedido recogido: sin tokens activos', [
                    'id_solicitud' => $idSolicitud,
                    'recipient_staff_id' => $recipientStaffId,
                ]);

                return response()->json([
                    'success' => true,
                    'sent' => 0,
                    'failed' => 0,
                    'recipient_staff_id' => $recipientStaffId,
                    'message' => 'Solicitud encontrada, pero el destinatario no tiene tokens FCM activos.',
                    'broadcast' => $payload,
                ]);
            }

            $sent = 0;
            $failed = 0;

            foreach ($tokens as $token) {
                $result = $this->fcmService->sendToToken(
                    $token,
                    $payload['titulo'],
                    $payload['mensaje'],
                    [
                        'type' => 'pedido_recogido',
                        'id_solicitud' => (string) $idSolicitud,
                        'recipient_staff_id' => (string) $recipientStaffId,
                    ]
                );

                Log::info('FCM pedido recogido resultado', [
                    'id_solicitud' => $idSolicitud,
                    'recipient_staff_id' => $recipientStaffId,
                    'token_preview' => $this->maskToken($token),
                    'status' => $result['status'] ?? null,
                    'ok' => $result['ok'] ?? false,
                    'invalid_token' => $result['invalid_token'] ?? false,
                    'message' => $result['message'] ?? null,
                ]);

                if (($result['ok'] ?? false) === true) {
                    $sent++;
                    continue;
                }

                $failed++;
                if (($result['invalid_token'] ?? false) === true) {
                    UserFcmToken::query()
                        ->where('token', $token)
                        ->update(['is_active' => false]);
                }
            }

            return response()->json([
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'recipient_staff_id' => $recipientStaffId,
                'broadcast' => $payload,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudo notificar el pedido recogido.', 500);
        }
    }

    protected function maskToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        if (strlen($token) <= 12) {
            return '***';
        }

        return substr($token, 0, 6).'...'.substr($token, -6);
    }

}
