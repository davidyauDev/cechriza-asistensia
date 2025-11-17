<?php

namespace App\Repositories;

use App\Helpers\ImageHelper;
use App\Http\Requests\EventoRequest;
use App\Models\Evento;
use App\Models\ImagenEvento;
use DB;
use Storage;
use Str;
use Symfony\Component\CssSelector\Exception\InternalErrorException;


class EventoServiceRepository implements EventoServiceRepositoryInterface
{

    public function createEvent(EventoRequest $request): Evento
    {
        DB::beginTransaction();

        try {
            $evento = Evento::create($request->only([
                'titulo',
                'descripcion',
                'fecha_inicio',
                'fecha_fin',
                'estado'
            ]));

            $orden = 1;

            if ($request->hasFile('imagenes_archivos')) {
                $descripcionesArchivos = $request->input('descripcion_imagenes', []);
                $autoresArchivos = $request->input('autor_imagenes', []);

                foreach ($request->file('imagenes_archivos') as $index => $archivo) {
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $archivo->getClientOriginalExtension();

                    $rutaArchivo = $archivo->storeAs('eventos', $nombreArchivo, 'public');

                    $urlImagen = ImageHelper::getStorageUrl($rutaArchivo);

                    ImagenEvento::create([
                        'evento_id' => $evento->id,
                        'url_imagen' => $urlImagen,
                        'descripcion' => $descripcionesArchivos[$index] ?? '',
                        'orden' => $orden++,
                        'autor' => $autoresArchivos[$index] ?? null,
                    ]);
                }
            }

            if ($request->has('imagenes') && is_array($request->imagenes)) {
                foreach ($request->imagenes as $imagenData) {
                    ImagenEvento::create([
                        'evento_id' => $evento->id,
                        'url_imagen' => $imagenData['url_imagen'],
                        'descripcion' => $imagenData['descripcion'] ?? '',
                        'orden' => $imagenData['orden'] ?? $orden++,
                        'autor' => $imagenData['autor'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $evento->load('imagenes');
            return $evento;
        } catch (\Exception $e) {
            DB::rollback();

            if ($request->hasFile('imagenes_archivos')) {
                foreach ($request->file('imagenes_archivos') as $archivo) {
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $archivo->getClientOriginalExtension();
                    $rutaArchivo = 'eventos/' . $nombreArchivo;

                    if (Storage::disk('public')->exists($rutaArchivo)) {
                        Storage::disk('public')->delete($rutaArchivo);
                    }
                }
            }

            throw new InternalErrorException('Error al crear el evento: ' . $e->getMessage());
        }
    }


    public function updateEvent(EventoRequest $request, Evento $evento): Evento
    {
        DB::beginTransaction();

        try {

            // Actualizar el evento
            $evento->update($request->only([
                'titulo',
                'descripcion',
                'fecha_inicio',
                'fecha_fin',
                'estado'
            ]));

            $orden = 1;

            // Si hay nuevas imágenes (archivos o URLs), eliminar las actuales
            if (($request->hasFile('imagenes_archivos')) || ($request->has('imagenes') && is_array($request->imagenes))) {
                // Obtener las URLs de las imágenes actuales para eliminar archivos físicos
                $imagenesActuales = $evento->imagenes;

                // Eliminar registros de la base de datos
                $evento->imagenes()->delete();

                // Eliminar archivos físicos del storage (solo los que están en nuestro storage)
                foreach ($imagenesActuales as $imagen) {
                    $url = $imagen->url_imagen;

                    // Si la URL apunta a nuestro storage, eliminar el archivo
                    if (str_contains($url, '/storage/eventos/')) {
                        $rutaArchivo = str_replace([config('app.url') . '/storage/', '/storage/'], '', $url);

                        if (Storage::disk('public')->exists($rutaArchivo)) {
                            Storage::disk('public')->delete($rutaArchivo);
                        }
                    }
                }
            }

            // Procesar nuevos archivos de imagen subidos
            if ($request->hasFile('imagenes_archivos')) {
                $descripcionesArchivos = $request->input('descripcion_imagenes', []);
                $autoresArchivos = $request->input('autor_imagenes', []);

                foreach ($request->file('imagenes_archivos') as $index => $archivo) {
                    // Generar nombre único para el archivo
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $archivo->getClientOriginalExtension();

                    // Guardar en storage/app/public/eventos
                    $rutaArchivo = $archivo->storeAs('eventos', $nombreArchivo, 'public');

                    // Crear la URL completa usando el helper
                    $urlImagen = ImageHelper::getStorageUrl($rutaArchivo);

                    // Crear el registro en la base de datos
                    ImagenEvento::create([
                        'evento_id' => $evento->id,
                        'url_imagen' => $urlImagen,
                        'descripcion' => $descripcionesArchivos[$index] ?? '',
                        'orden' => $orden++,
                        'autor' => $autoresArchivos[$index] ?? null,
                    ]);
                }
            }

            // Procesar nuevas imágenes por URL
            if ($request->has('imagenes') && is_array($request->imagenes)) {
                foreach ($request->imagenes as $imagenData) {
                    ImagenEvento::create([
                        'evento_id' => $evento->id,
                        'url_imagen' => $imagenData['url_imagen'],
                        'descripcion' => $imagenData['descripcion'] ?? '',
                        'orden' => $imagenData['orden'] ?? $orden++,
                        'autor' => $imagenData['autor'] ?? null,
                    ]);
                }
            }

            DB::commit();

            // Cargar las imágenes para retornar el evento completo
            $evento->load('imagenes');

            return $evento;
        } catch (\Exception $e) {
            DB::rollback();

            throw new InternalErrorException('Error al actualizar el evento: ' . $e->getMessage());
        }
    }

}