<?php

namespace App\Repositories;

use App\Helpers\ImageHelper;
use App\Http\Requests\EventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Models\Evento;
use App\Models\ImagenEvento;
use Carbon\CarbonPeriod;
use DB;
use Storage;
use Str;
use Symfony\Component\CssSelector\Exception\InternalErrorException;

use Illuminate\Support\Collection;

class EventoServiceRepository implements EventoServiceRepositoryInterface
{

    public function createEvent(EventoRequest $request): Collection
    {
        DB::beginTransaction();

        $urlFilesInserted = [];


        $start = $request->fecha_inicio;  // "2025-01-20"
        $end = $request->fecha_fin;        // "2025-01-28"

        $period = CarbonPeriod::create($start, $end);

        $events = [];
        foreach ($period as $date) {
            // ds($date);
            $events[] = [
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'fecha' => $date->format('Y-m-d'),
                'active' => $request->active,
                'imagenes_archivos' => $request->imagenes_archivos,
                'imagenes' => $request->imagenes,

            ];
        }


        try {

            $createdAt = now();


            $hasInserted = Evento::insert(array_map(function ($event) use ($createdAt) {
                return [
                    'titulo' => $event['titulo'],
                    'descripcion' => $event['descripcion'],
                    'fecha' => $event['fecha'],
                    'active' => $event['active'] ?? true,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }, $events));
            if (!$hasInserted) {
                throw new InternalErrorException('Error al crear los eventos en la base de datos.');
            }


            $eventos = Evento::where('created_at', $createdAt)->get();


            ds($eventos);


            foreach ($eventos as $index => $evento) {
                // $evento = Evento::where('titulo', $event['titulo'])
                //     ->where('fecha', $event['fecha'])
                //     ->first();

                $event = $events[$index];

                ds($event);

                // Procesar nuevos archivos de imagen subidos
                if (isset($event['imagenes_archivos']) && is_array($event['imagenes_archivos'])) {
                    // $descripcionesArchivos = $request->input('descripcion_imagenes', []);
                    // $autoresArchivos = $request->input('autor_imagenes', []);

                    foreach ($event['imagenes_archivos'] as $index => $archivo) {
                        // Generar nombre único para el archivo
                        $nombreArchivo = time() . '_' . Str::random(10) . '.' . $archivo->getClientOriginalExtension();


                        //* Redimensionar la imagen antes de guardarla (opcional)
                        // $rutaArchivo = ImageHelper::resizeWithVips($archivo, 600);

                        // Guardar en storage/app/public/eventos
                        $rutaArchivo = $archivo->storeAs('eventos', $nombreArchivo, 'public');



                        // Crear la URL completa usando el helper
                        $urlImagen = ImageHelper::getStorageUrl($rutaArchivo);

                        // Crear el registro en la base de datos
                        $image = ImagenEvento::create([
                            'evento_id' => $evento->id,
                            'url_imagen' => $urlImagen,
                            'descripcion' => '',
                            // 'orden' => $orden++,
                            'order' => $index + 1,
                            'autor' => null,
                        ]);

                        $urlFilesInserted[] = $image->url_imagen;
                    }
                }

                // Procesar nuevas imágenes por URL
                if (isset($event['imagenes']) && is_array($event['imagenes'])) {
                    foreach ($event['imagenes'] as $imagenData) {
                        $image = ImagenEvento::create([
                            'evento_id' => $evento->id,
                            'url_imagen' => $imagenData['url_imagen'],
                            'descripcion' => $imagenData['descripcion'] ?? '',
                            // 'orden' => $imagenData['orden'] ?? $orden++,
                            'order' => $index + 1,
                            'autor' => $imagenData['autor'] ?? null,
                        ]);
                        $urlFilesInserted[] = $image->url_imagen;
                    }
                }

                $evento->load('imagenes');
            }



            DB::commit();


            return $eventos;
        } catch (\Exception $e) {
            DB::rollback();

            if ($urlFilesInserted) {
                foreach ($urlFilesInserted as $path) {
                    // $nombreArchivo = time() . '_' . Str::random(10) . '.' . $archivo->getClientOriginalExtension();
                    // $rutaArchivo = 'eventos/' . $nombreArchivo;

                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            ds($e->getMessage());

            throw new InternalErrorException('Error al crear el evento: ' . $e->getMessage());
        }
    }


    public function updateEvent(UpdateEventoRequest $request, Evento $evento): Evento
    {
        DB::beginTransaction();

        $serialized = [
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'fecha' => $request->fecha_inicio,
            'active' => $request->active ?? $evento->active,
        ];

        ds($serialized);

        try {

            // Actualizar el evento
            $evento->update($serialized);

            $orden = 1;

            // Si hay nuevas imágenes (archivos o URLs), eliminar las actuales
            if ($request->images_delete && count($request->images_delete) > 0) {
                // Obtener las URLs de las imágenes actuales para eliminar archivos físicos
                $imagenesActuales = $evento->imagenes()->whereIn('id', $request->images_delete)->get();


                // Eliminar registros de la base de datos
                $evento->imagenes()->whereIn('id', $request->images_delete)->delete();


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