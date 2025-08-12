<?php

namespace App\Events;

use App\Models\KdsOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CoffeeOrderReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public KdsOrder $order;

    public function __construct(KdsOrder $order)
    {
        $this->order = $order->load('items');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('kds-coffee'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.received';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'ticket_number' => $this->order->ticket_number,
            'status' => $this->order->status,
            'order_time' => $this->order->order_time->format('H:i:s'),
            'waiting_time' => $this->order->waiting_time_formatted,
            'items' => $this->order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->display_name,
                    'quantity' => $item->formatted_quantity,
                    'modifiers' => $item->modifiers,
                    'notes' => $item->notes,
                ];
            })->toArray(),
            'customer_info' => $this->order->customer_info,
        ];
    }
}
