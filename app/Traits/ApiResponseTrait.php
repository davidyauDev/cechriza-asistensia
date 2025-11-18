<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function successResponse(
        mixed $data,
        string $message,
        int $status = 200
    ): JsonResponse {
        if (is_object($data) && method_exists($data, 'response')) {
            $resourcePayload = $data->response()->getData(true);
            $payload = array_merge([
                'success' => true,
                'message' => $message,
            ], $resourcePayload);
            return response()->json($payload, $status);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    protected function errorResponse(string $message, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], $status);
    }

    protected function messageResponse(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $status);
    }
}
