<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudCompletaCreada implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('solicitudes.completas')];
    }

    public function broadcastAs(): string
    {
        return 'solicitud.completa.creada';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
