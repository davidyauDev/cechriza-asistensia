<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Services\EventoServiceInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;


class EventoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private EventoServiceInterface $eventoService
    ) {
    }

    /**
     * Listar todos los eventos con sus imágenes
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->eventoService->index(),
            'Eventos retrieved successfully'
        );
    }

    /**
     * Mostrar un evento específico por ID con sus imágenes
     */
    public function show($id): JsonResponse
    {
        return $this->successResponse(
            $this->eventoService->show($id),
            'Evento retrieved successfully'
        );
    }

    /**
     * Crear un nuevo evento con imágenes asociadas
     */
    public function store(EventoRequest $request): JsonResponse
    {

        return $this->successResponse($this->eventoService->store($request), 'Evento created successfully');

    }

    /**
     * Obtener eventos activos en una fecha específica
     */
    public function porFecha($fecha): JsonResponse
    {
        $data = $this->eventoService->getByDate($fecha);

        return $this->successResponse(
            $data,
            $data['events']->isEmpty()
            ? 'No hay eventos activos para la fecha especificada'
            : 'Eventos activos para la fecha obtenidos exitosamente'
        );
    }

    /**
     * Obtener eventos activos para hoy
     */
    public function eventosHoy(): JsonResponse
    {
        $data = $this->eventoService->todayEvents();

        return $this->successResponse(
            $data,
            empty($data['events'])
            ? 'No hay eventos activos para hoy'
            : 'Eventos activos para hoy obtenidos exitosamente'
        );
    }

    /**
     * Actualizar un evento existente
     */
    public function update(UpdateEventoRequest $request, $id): JsonResponse
    {

        return $this->successResponse(
            $this->eventoService->updateEvent($request, $id),
            'Evento updated successfully'
        );
    }


    /**
     * Eliminar un evento y sus imágenes
     */
    public function destroy($id): JsonResponse
    {


        return $this->successResponse(
            $this->eventoService->delete($id),
            'Evento deleted successfully'
        );
    }


    public function eventosDelMes($anio, $mes)
    {

        return $this->successResponse(
            $this->eventoService->monthlyEventsSummary($anio, $mes),
            'Monthly events summary retrieved successfully'
        );
    }
}
