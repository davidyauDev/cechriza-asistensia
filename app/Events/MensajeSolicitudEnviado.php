<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeSolicitudEnviado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $idSolicitud,
        public array $mensaje
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('solicitud.mensajes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'solicitud.mensaje.enviado';
    }

    public function broadcastWith(): array
    {
        return [
            'id_solicitud' => $this->idSolicitud,
            'mensaje' => $this->mensaje,
        ];
    }
}
