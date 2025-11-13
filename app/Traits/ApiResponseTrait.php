<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function successResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data
        ], $status);
    }

    protected function errorResponse(string $message, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $status);
    }

    protected function messageResponse(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message
        ], $status);
    }
}
