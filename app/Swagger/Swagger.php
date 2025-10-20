<?php

namespace App\Swagger;

/**
 * Minimal OpenAPI annotations to allow swagger-php to generate docs.
 *
 * @OA\Info(
 *     title="Cechriza Asistencia API",
 *     version="1.0.0",
 *     description="Documentación API mínima para l5-swagger"
 * )
 *
 * // Security scheme for Bearer tokens
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Bearer"
 * )
 */
class Swagger
{
    // This class only exists to hold file-level annotations for swagger-php.
}

// Keep this file only for global annotations (Info). Add endpoint annotations
// directly in controllers.
