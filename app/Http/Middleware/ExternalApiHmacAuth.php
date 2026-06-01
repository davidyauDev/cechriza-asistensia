<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalApiHmacAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) $request->header('X-API-KEY', '');
        $timestamp = (string) $request->header('X-TIMESTAMP', '');
        $signature = (string) $request->header('X-SIGNATURE', '');

        if ($apiKey === '' || $timestamp === '' || $signature === '') {
            return $this->unauthorized('Missing authentication headers.');
        }

        $expectedApiKey = (string) config('services.external_api.key', '');
        $secret = (string) config('services.external_api.secret', '');
        $ttlSeconds = (int) config('services.external_api.signature_ttl', 300);

        if ($expectedApiKey === '' || $secret === '') {
            return $this->unauthorized('External API credentials are not configured.');
        }

        if (! hash_equals($expectedApiKey, $apiKey)) {
            return $this->unauthorized('Invalid API key.');
        }

        if (! ctype_digit($timestamp)) {
            return $this->unauthorized('Invalid timestamp.');
        }

        $timestampInt = (int) $timestamp;
        if (abs(time() - $timestampInt) > $ttlSeconds) {
            return $this->unauthorized('Expired signature timestamp.');
        }

        $method = strtoupper($request->getMethod());
        $path = '/'.ltrim($request->path(), '/');
        $body = (string) $request->getContent();
        $payload = $method."\n".$path."\n".$timestamp."\n".$body;
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return $this->unauthorized('Invalid signature.');
        }

        return $next($request);
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 401);
    }
}
