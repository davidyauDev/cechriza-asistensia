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

/**
 * @OA\Components(
 *   @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email")
 *   ),
 *   @OA\Schema(
 *     schema="Attendance",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="timestamp", type="string", format="date-time"),
 *     @OA\Property(property="latitude", type="number", format="float"),
 *     @OA\Property(property="longitude", type="number", format="float"),
 *     @OA\Property(property="notes", type="string")
 *   )
 * )
 */

// Keep this file only for global annotations (Info). Add endpoint annotations
// directly in controllers.
