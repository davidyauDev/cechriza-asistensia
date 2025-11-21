<?php


namespace App\Services;


use App\Http\Requests\EventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Collection;

interface EventoServiceInterface
{
    public function index(): Collection;
    public function show($id): Evento;

    public function store(EventoRequest $request): Collection;

    public function getByDate(string $date): array;

    public function todayEvents(): array;

    public function updateEvent(UpdateEventoRequest $request, $id): Evento;

    public function delete($id): void;

    public function monthlyEventsSummary($anio, $mes);
}
