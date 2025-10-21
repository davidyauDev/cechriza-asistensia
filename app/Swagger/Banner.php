<?php

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="Banner",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="image_url", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="start_at", type="string", format="date-time"),
 *     @OA\Property(property="end_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Banner
{
}
