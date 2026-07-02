<?php

namespace App\Http\Controllers;

use App\Jobs\MonitorCoffeeOrdersJob;
use App\Models\KdsOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Get recently completed orders (last 30 minutes)
        $completedOrders = KdsOrder::with('items')
            ->whereIn('status', ['completed'])
            ->where('completed_at', '>=', now()->subMinutes(30))
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        // Check system status - simplified for real-time polling
        $lastProcessedOrder = KdsOrder::orderBy('created_at', 'desc')->first();

        // Test POS database connection
        $posConnected = false;
        try {
            \DB::connection('pos')->getPdo();
            $posConnected = true;
        } catch (\Exception $e) {
            $posConnected = false;
        }

        $systemStatus = [
            'pos_connected' => $posConnected,
            'last_order' => $lastProcessedOrder ? $lastProcessedOrder->created_at->diffForHumans() : 'No orders yet',
            'polling_active' => true, // Will be updated by JavaScript
            'active_orders' => $orders->count(),
        ];

        return view('kds.index', compact('orders', 'completedOrders', 'systemStatus'));
    }

    public function getOrders(): JsonResponse
    {
        $activeOrders = KdsOrder::with('items')
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
                    'items' => $order->card_items,
                    'compact_display' => $order->compact_display,
                    'should_use_compact' => $order->shouldUseCompactDisplay(),
                    'customer_info' => $order->customer_info,
                    'person_name' => $order->person_name,
                    'placed_at_ts' => $order->order_time->valueOf(),
                ];
            });

        $completedOrders = KdsOrder::with('items')
            ->whereIn('status', ['completed'])
            ->where('completed_at', '>=', now()->subMinutes(30))
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'ticket_number' => $order->ticket_number,
                    'status' => $order->status,
                    'order_time' => $order->order_time->format('H:i:s'),
                    'completed_time' => $order->completed_at ? $order->completed_at->format('H:i:s') : '',
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->display_name,
                            'quantity' => $item->formatted_quantity,
                            'kind' => $item->kind,
                        ];
                    }),
                    'person_name' => $order->person_name,
                ];
            });

        return response()->json([
            'active' => $activeOrders,
            'completed' => $completedOrders,
        ]);
    }

    public function updateStatus(Request $request, KdsOrder $kdsOrder): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:new,viewed,preparing,ready,completed,cancelled',
        ]);

        $status = $request->input('status');

        switch ($status) {
            case 'new':
                // Restore to new status
                $kdsOrder->update([
                    'status' => 'new',
                    'viewed_at' => null,
                    'started_at' => null,
                    'ready_at' => null,
                    'completed_at' => null,
                    'prep_time' => null,
                ]);
                break;
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
            ],
        ]);
    }

    public function stream(): StreamedResponse
    {
        return response()->stream(function () {
            $lastMonitorCheck = now();
            $lastHeartbeat = now();
            $lastSignature = null;

            while (true) {
                // Check for new or updated orders
                $orders = KdsOrder::with('items')
                    ->active()
                    ->today()
                    ->orderBy('order_time', 'asc')
                    ->get();

                // Also get completed orders for the table
                $completedOrders = KdsOrder::with('items')
                    ->whereIn('status', ['completed'])
                    ->where('completed_at', '>=', now()->subMinutes(30))
                    ->orderBy('completed_at', 'desc')
                    ->limit(10)
                    ->get();

                $activePayload = $orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'ticket_number' => $order->ticket_number,
                        'status' => $order->status,
                        'order_time' => $order->order_time->format('H:i:s'),
                        'waiting_time' => $order->waiting_time_formatted,
                        'items' => $order->card_items,
                        'compact_display' => $order->compact_display,
                        'should_use_compact' => $order->shouldUseCompactDisplay(),
                        'customer_info' => $order->customer_info,
                        'person_name' => $order->person_name,
                        'placed_at_ts' => $order->order_time->valueOf(),
                    ];
                })->toArray();

                $completedPayload = $completedOrders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'ticket_number' => $order->ticket_number,
                        'order_time' => $order->order_time->format('H:i:s'),
                        'completed_time' => $order->completed_at ? $order->completed_at->format('H:i:s') : '',
                        'items' => $order->items->map(function ($item) {
                            return [
                                'product_name' => $item->display_name,
                                'quantity' => $item->formatted_quantity,
                                'kind' => $item->kind,
                            ];
                        })->toArray(),
                        'person_name' => $order->person_name,
                    ];
                })->toArray();

                // Signature of state that affects which cards are shown.
                // Item id/quantity are captured so added/removed lines push; item
                // tick state is client-only. waiting_time is excluded (changes
                // every second; the client ticks its own timers) so we only push
                // on real changes.
                $signature = md5(json_encode([
                    'active' => $orders->map(fn ($o) => [
                        $o->id,
                        $o->status,
                        $o->items->map(fn ($i) => [$i->id, $i->formatted_quantity])->toArray(),
                    ])->toArray(),
                    'completed' => $completedOrders->pluck('id')->toArray(),
                ]));

                // Push when state changed, or on a periodic heartbeat to keep the
                // connection alive and the completed panel fresh.
                $heartbeatDue = $lastHeartbeat->diffInSeconds(now()) >= 15;
                if ($signature !== $lastSignature || $heartbeatDue) {
                    $data = json_encode([
                        'orders' => $activePayload,
                        'completed' => $completedPayload,
                    ]);

                    echo "data: {$data}\n\n";
                    ob_flush();
                    flush();

                    $lastSignature = $signature;
                    $lastHeartbeat = now();
                }

                // Short poll so completions propagate to all devices in ~1s.
                sleep(1);

                // Dispatch monitoring job every 3 seconds.
                // Job is now optimized to run in ~100ms.
                if ($lastMonitorCheck->diffInSeconds(now()) >= 3) {
                    MonitorCoffeeOrdersJob::dispatch();
                    $lastMonitorCheck = now();
                }
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
            'message' => 'Checking for new orders',
        ]);
    }

    public function clearCompleted(): JsonResponse
    {
        try {
            // Delete completed and cancelled orders older than 1 hour
            $deleted = KdsOrder::whereIn('status', ['completed', 'cancelled'])
                ->where(function ($query) {
                    $query->where('completed_at', '<', now()->subHour())
                        ->orWhere('updated_at', '<', now()->subHour());
                })
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$deleted} completed orders",
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to clear completed KDS orders', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear completed orders: '.$e->getMessage(),
            ], 500);
        }
    }

    public function clearAll(): JsonResponse
    {
        // Mark all active orders as completed instead of deleting them
        try {
            // Count active orders before marking as completed
            $count = KdsOrder::whereNotIn('status', ['completed', 'cancelled'])->count();

            // Mark all non-completed orders as completed
            $updated = KdsOrder::whereNotIn('status', ['completed', 'cancelled'])
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            \Log::info('KDS Clear All: Marked all orders as completed', [
                'count' => $updated,
                'completed_time' => now()->toDateTimeString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Completed {$updated} orders",
                'debug' => [
                    'completed_count' => $updated,
                    'completed_time' => now()->toDateTimeString(),
                    'info' => 'All active orders marked as completed',
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to clear KDS orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear orders: '.$e->getMessage(),
            ], 500);
        }
    }
}
