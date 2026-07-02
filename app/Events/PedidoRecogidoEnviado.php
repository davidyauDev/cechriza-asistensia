<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoRecogidoEnviado implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public array $pedido)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('pedidos.recogidos'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pedido.recogido.enviado';
    }

    public function broadcastWith(): array
    {
        return $this->pedido;
    }
}
