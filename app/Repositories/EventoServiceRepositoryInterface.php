<?php

namespace App\Repositories;

use App\Http\Requests\EventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Models\Evento;

use Illuminate\Support\Collection;

interface EventoServiceRepositoryInterface
{
    public function createEvent(EventoRequest $evento): Collection;    
    public function updateEvent(UpdateEventoRequest $request, Evento $evento): Evento;


}