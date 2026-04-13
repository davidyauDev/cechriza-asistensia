<?php

namespace App\Services;

interface SolicitudCompletaServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @return array{ticket: string, uploaded_files: array<int, array<string, mixed>>}
     */
    public function registrar(array $data, array $files = []): array;
}
