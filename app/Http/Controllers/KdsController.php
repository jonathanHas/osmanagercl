<?php

namespace App\Http\Controllers;

use App\Models\KdsOrder;
use App\Jobs\MonitorCoffeeOrdersJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KdsController extends Controller
{
    public function index()
    {
        // Get active orders
        $orders = KdsOrder::with('items')
            ->active()
            ->today()
            ->orderBy('order_time', 'asc')
            ->get();

        return view('kds.index', compact('orders'));
    }

    public function getOrders(): JsonResponse
    {
        $orders = KdsOrder::with('items')
            ->active()
            ->today()
            ->orderBy('order_time', 'asc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'ticket_number' => $order->ticket_number,
                    'status' => $order->status,
                    'order_time' => $order->order_time->format('H:i:s'),
                    'waiting_time' => $order->waiting_time_formatted,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->display_name,
                            'quantity' => $item->formatted_quantity,
                            'modifiers' => $item->modifiers,
                            'notes' => $item->notes,
                        ];
                    }),
                    'customer_info' => $order->customer_info,
                ];
            });

        return response()->json($orders);
    }

    public function updateStatus(Request $request, KdsOrder $kdsOrder): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:viewed,preparing,ready,completed,cancelled'
        ]);

        $status = $request->input('status');

        switch ($status) {
            case 'viewed':
                $kdsOrder->markAsViewed();
                break;
            case 'preparing':
                $kdsOrder->startPreparing();
                break;
            case 'ready':
                $kdsOrder->markAsReady();
                break;
            case 'completed':
                $kdsOrder->complete();
                break;
            case 'cancelled':
                $kdsOrder->cancel();
                break;
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $kdsOrder->id,
                'status' => $kdsOrder->status,
                'waiting_time' => $kdsOrder->waiting_time_formatted,
            ]
        ]);
    }

    public function stream(): StreamedResponse
    {
        return response()->stream(function () {
            while (true) {
                // Check for new or updated orders
                $orders = KdsOrder::with('items')
                    ->active()
                    ->today()
                    ->orderBy('order_time', 'asc')
                    ->get();

                $data = json_encode([
                    'orders' => $orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'ticket_number' => $order->ticket_number,
                            'status' => $order->status,
                            'order_time' => $order->order_time->format('H:i:s'),
                            'waiting_time' => $order->waiting_time_formatted,
                            'items' => $order->items->map(function ($item) {
                                return [
                                    'id' => $item->id,
                                    'product_name' => $item->display_name,
                                    'quantity' => $item->formatted_quantity,
                                    'modifiers' => $item->modifiers,
                                    'notes' => $item->notes,
                                ];
                            })->toArray(),
                            'customer_info' => $order->customer_info,
                        ];
                    })->toArray()
                ]);

                echo "data: {$data}\n\n";
                ob_flush();
                flush();

                // Wait 5 seconds before next update
                sleep(5);

                // Also dispatch the monitoring job to check for new orders
                MonitorCoffeeOrdersJob::dispatch();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function pollNewOrders(): JsonResponse
    {
        // Manually trigger the monitoring job
        MonitorCoffeeOrdersJob::dispatch();

        return response()->json([
            'success' => true,
            'message' => 'Checking for new orders'
        ]);
    }
}