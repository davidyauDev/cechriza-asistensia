<?php


namespace App\Services;


use App\Http\Requests\EventoRequest;
use App\Models\Evento;

use App\Repositories\EventoServiceRepositoryInterface;
use App\Services\EventoServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;




class EventoService implements EventoServiceInterface
{

    public function __construct(
        private EventoServiceRepositoryInterface $eventoRepository
    ) {
        //
    }
    public function index(): Collection
    {
        return Evento::with('imagenes')->get();

    }

    public function show($id): Evento
    {

        $evento = Evento::with('imagenes')->find($id);

        if (!$evento) {
            throw new NotFoundHttpException('Evento no encontrado');
        }

        return $evento;

    }

    public function store(EventoRequest $request): Evento
    {
        $evento = $this->eventoRepository->createEvent($request);
        return $evento;
    }

    public function getByDate(string $date): array
    {
        $eventos = Evento::with('imagenes')
            ->activoEnFecha($date)
            ->get();

        return [
            "events" => $eventos,
            "date" => $date
        ];

    }

    public function todayEvents(): array
    {
        $hoy = date('Y-m-d');

        $eventos = Evento::with('imagenes')
            ->activoEnFecha($hoy)
            ->get();

        return [
            'events' => $eventos,
            'date' => $hoy
        ];
    }

    public function updateEvent(EventoRequest $request, $id): Evento
    {
        $evento = Evento::find($id);
        if (!$evento) {
            throw new NotFoundHttpException('Evento no encontrado');
        }

        return $this->eventoRepository->updateEvent($request, $evento);
    }


    public function delete($id): void
    {
        $evento = Evento::with('imagenes')->find($id);

        if (!$evento) {
            throw new NotFoundHttpException('Evento no encontrado');
        }

        // Eliminar archivos físicos del storage antes de eliminar el evento
        foreach ($evento->imagenes as $imagen) {
            $url = $imagen->url_imagen;

            // Si la URL apunta a nuestro storage, eliminar el archivo
            if (str_contains($url, '/storage/eventos/')) {
                $rutaArchivo = str_replace([config('app.url') . '/storage/', '/storage/'], '', $url);

                if (\Storage::disk('public')->exists($rutaArchivo)) {
                    \Storage::disk('public')->delete($rutaArchivo);
                }
            }
        }

        $evento->delete(); // Las imágenes se eliminan automáticamente por cascade
    }


    public function monthlyEventsSummary($anio, $mes)
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin = Carbon::create($anio, $mes, 1)->endOfMonth();

        $eventos = Evento::with('imagenes')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha_inicio', [$inicio, $fin])
                    ->orWhereBetween('fecha_fin', [$inicio, $fin])
                    ->orWhere(function ($q2) use ($inicio, $fin) {
                        $q2->where('fecha_inicio', '<', $inicio)
                            ->where('fecha_fin', '>', $fin);
                    });
            })
            ->orderBy('fecha_inicio')
            ->get();

        $totalEventos = $eventos->count();

        return [
            'year' => $anio,
            'month' => $mes,
            'total_events' => $totalEventos,
            'events' => $eventos,
        ];
    }


    
}
