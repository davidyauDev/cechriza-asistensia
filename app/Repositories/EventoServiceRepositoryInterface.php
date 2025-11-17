<?php

namespace App\Repositories;

use App\Http\Requests\EventoRequest;
use App\Models\Evento;


interface EventoServiceRepositoryInterface
{
    public function createEvent(EventoRequest $evento): Evento;    
    public function updateEvent(EventoRequest $request, Evento $evento): Evento;


}