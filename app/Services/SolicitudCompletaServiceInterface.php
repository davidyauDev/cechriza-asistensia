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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @return array{
     *     id_solicitud: int,
     *     ticket: string,
     *     detalles_actualizados: int,
     *     detalles_creados: int,
     *     detalles_eliminados: int,
     *     uploaded_files: array<int, array<string, mixed>>,
     *     detalles: array<int, array<string, mixed>>
     * }
     */
    public function actualizarDetalles(int $idSolicitud, array $data, array $files = []): array;
}
