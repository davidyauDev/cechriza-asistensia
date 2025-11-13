<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventoRequest;
use App\Models\Evento;
use App\Models\ImagenEvento;
use App\Helpers\ImageHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventoController extends Controller
{
    /**
     * Listar todos los eventos con sus imágenes
     */
    public function index(): JsonResponse
    {
        try {
            $eventos = Evento::with('imagenes')->get();

            return response()->json([
                'success' => true,
                'data' => $eventos,
                'message' => 'Eventos obtenidos exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un evento específico por ID con sus imágenes
     */
    public function show($id): JsonResponse
    {
        try {
            $evento = Evento::with('imagenes')->find($id);

            if (!$evento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $evento,
                'message' => 'Evento obtenido exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo evento con imágenes asociadas
     */
    public function store(EventoRequest $request): JsonResponse
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

            return response()->json([
                'success' => true,
                'data' => $evento,
                'message' => 'Evento creado exitosamente'
            ], 201);

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

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener eventos activos en una fecha específica
     */
    public function porFecha($fecha): JsonResponse
    {
        try {
            $eventos = Evento::with('imagenes')
                ->activoEnFecha($fecha)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $eventos,
                'message' => $eventos->isEmpty() 
                    ? 'No se encontraron eventos activos para la fecha especificada'
                    : 'Eventos activos obtenidos exitosamente',
                'fecha' => $fecha
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener eventos por fecha: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener eventos activos para hoy
     */
    public function eventosHoy(): JsonResponse
    {
        try {
            $hoy = Carbon::now()->format('Y-m-d');
            Log::info('Fecha de hoy para eventos', ['hoy' => $hoy]);
            $eventos = Evento::with('imagenes')
                ->activoEnFecha($hoy)
                ->get();
            Log::info('Eventos hoy', ['eventos' => $eventos]);

            return response()->json([
                'success' => true,
                'data' => $eventos,
                'message' => $eventos->isEmpty() 
                    ? 'No hay eventos activos para hoy'
                    : 'Eventos activos de hoy obtenidos exitosamente',
                'fecha' => $hoy
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener eventos de hoy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un evento existente
     */
    public function update(EventoRequest $request, $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $evento = Evento::find($id);

            if (!$evento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

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

            return response()->json([
                'success' => true,
                'data' => $evento,
                'message' => 'Evento actualizado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un evento y sus imágenes
     */
    public function destroy($id): JsonResponse
    {
        try {
            $evento = Evento::with('imagenes')->find($id);

            if (!$evento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            // Eliminar archivos físicos del storage antes de eliminar el evento
            foreach ($evento->imagenes as $imagen) {
                $url = $imagen->url_imagen;
                
                // Si la URL apunta a nuestro storage, eliminar el archivo
                if (str_contains($url, '/storage/eventos/')) {
                    $rutaArchivo = str_replace([config('app.url') . '/storage/', '/storage/'], '', $url);
                    
                    if (Storage::disk('public')->exists($rutaArchivo)) {
                        Storage::disk('public')->delete($rutaArchivo);
                    }
                }
            }

            $evento->delete(); // Las imágenes se eliminan automáticamente por cascade

            return response()->json([
                'success' => true,
                'message' => 'Evento eliminado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el evento: ' . $e->getMessage()
            ], 500);
        }
    }


    public function eventosDelMes($anio, $mes)
{
    $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
    $fin = Carbon::create($anio, $mes, 1)->endOfMonth();

    $eventos = Evento::with('imagenes')
        ->where(function($q) use ($inicio, $fin) {
            $q->whereBetween('fecha_inicio', [$inicio, $fin])
              ->orWhereBetween('fecha_fin', [$inicio, $fin])
              ->orWhere(function($q2) use ($inicio, $fin) {
                  $q2->where('fecha_inicio', '<', $inicio)
                     ->where('fecha_fin', '>', $fin);
              });
        })
        ->orderBy('fecha_inicio')
        ->get();

    return response()->json($eventos);
}
}
