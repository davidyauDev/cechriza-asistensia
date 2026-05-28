<?php

namespace App\Http\Controllers\Api;

use App\Events\ExternalFrontendTestEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReverbTestController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Reverb test API is reachable.',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ]);

        $payload = [
            'message' => $validated['message'] ?? 'Evento de prueba desde backend Laravel/Reverb',
            'meta' => $validated['meta'] ?? [],
            'sent_at' => now()->toISOString(),
        ];

        broadcast(new ExternalFrontendTestEvent($payload));

        return response()->json([
            'ok' => true,
            'event' => 'external.frontend.test',
            'channel' => 'external-demo',
            'payload' => $payload,
        ]);
    }
}
