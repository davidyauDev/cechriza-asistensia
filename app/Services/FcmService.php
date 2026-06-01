<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmService
{
    public function sendToToken(string $targetToken, string $title, string $body, array $data = []): array
    {
        $accessToken = $this->getAccessToken();
        $projectId = $this->getProjectId();
        $url = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId);

        $payload = [
            'message' => [
                'token' => $targetToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->normalizeDataPayload($data),
                'android' => [
                    'priority' => 'high',
                ],
            ],
        ];

        try {
            $response = Http::retry(2, 200)
                ->timeout(10)
                ->withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'status' => 0,
                'response' => ['error' => 'connection_timeout'],
                'invalid_token' => false,
                'message' => $e->getMessage(),
            ];
        } catch (RequestException $e) {
            $response = $e->response;
            $bodyJson = $response?->json() ?? ['error' => ['message' => $e->getMessage()]];
            $status = $response?->status() ?? 0;
            $invalidToken = $this->isInvalidTokenResponse($status, $bodyJson);

            return [
                'ok' => false,
                'status' => $status,
                'response' => $bodyJson,
                'invalid_token' => $invalidToken,
                'message' => (string) data_get($bodyJson, 'error.message', $e->getMessage()),
            ];
        }

        $bodyJson = $response->json();
        $invalidToken = $this->isInvalidTokenResponse($response->status(), $bodyJson);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'response' => $bodyJson,
            'invalid_token' => $invalidToken,
            'message' => $response->successful()
                ? 'sent'
                : (string) data_get($bodyJson, 'error.message', 'fcm_error'),
        ];
    }

    protected function getAccessToken(): string
    {
        $credentials = $this->getServiceAccountCredentials();
        $issuedAt = time();
        $expiresAt = $issuedAt + 3600;

        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ]));

        $unsignedJwt = $jwtHeader.'.'.$jwtClaim;
        $privateKey = $credentials['private_key'] ?? null;
        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new RuntimeException('La service account no contiene private_key valida.');
        }

        $signature = '';
        $signed = openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new RuntimeException('No se pudo firmar JWT para FCM.');
        }

        $assertion = $unsignedJwt.'.'.$this->base64UrlEncode($signature);

        try {
            $tokenResponse = Http::retry(2, 200)
                ->timeout(10)
                ->asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Timeout al obtener access token de Google: '.$e->getMessage());
        }

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Error al obtener access token de Google.');
        }

        $accessToken = (string) data_get($tokenResponse->json(), 'access_token', '');
        if ($accessToken === '') {
            throw new RuntimeException('Google devolvio access_token vacio.');
        }

        return $accessToken;
    }

    protected function getServiceAccountCredentials(): array
    {
        $path = (string) config('services.firebase.service_account_path', '');
        if ($path === '') {
            throw new RuntimeException('Falta FIREBASE_SERVICE_ACCOUNT_PATH en configuracion.');
        }

        if (! file_exists($path)) {
            throw new RuntimeException('No existe el archivo de service account de Firebase.');
        }

        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            throw new RuntimeException('No se pudo leer el archivo de service account.');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('El archivo de service account no contiene JSON valido.');
        }

        return $decoded;
    }

    protected function getProjectId(): string
    {
        $credentials = $this->getServiceAccountCredentials();
        $projectId = (string) ($credentials['project_id'] ?? '');
        if ($projectId === '') {
            $configuredProjectId = (string) config('services.firebase.project_id', '');
            if ($configuredProjectId !== '') {
                return $configuredProjectId;
            }

            throw new RuntimeException('No se pudo resolver project_id de Firebase desde JSON ni config.');
        }

        return $projectId;
    }

    protected function normalizeDataPayload(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                $value = json_encode($value);
            }

            $normalized[(string) $key] = (string) ($value ?? '');
        }

        return $normalized;
    }

    protected function isInvalidTokenResponse(int $status, mixed $body): bool
    {
        if ($status < 400) {
            return false;
        }

        $errorStatus = strtoupper((string) data_get($body, 'error.status', ''));
        $errorMessage = strtoupper((string) data_get($body, 'error.message', ''));
        $details = data_get($body, 'error.details', []);

        if (in_array($errorStatus, ['NOT_FOUND', 'UNREGISTERED'], true)) {
            return true;
        }

        if (str_contains($errorMessage, 'UNREGISTERED') || str_contains($errorMessage, 'INVALID REGISTRATION TOKEN')) {
            return true;
        }

        if (is_array($details)) {
            $jsonDetails = strtoupper(json_encode($details));
            return str_contains($jsonDetails, 'UNREGISTERED')
                || str_contains($jsonDetails, 'INVALID_ARGUMENT')
                || str_contains($jsonDetails, 'REGISTRATION TOKEN');
        }

        return false;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
