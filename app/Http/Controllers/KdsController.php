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
            
        // Get recently completed orders (last 30 minutes)
        $completedOrders = KdsOrder::with('items')
            ->whereIn('status', ['completed'])
            ->where('completed_at', '>=', now()->subMinutes(30))
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        // Check queue worker status
        $pendingJobs = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();
        
        // Check if any job was processed recently (within last 5 minutes)
        $lastProcessedOrder = KdsOrder::orderBy('created_at', 'desc')->first();
        
        // Check for recent job table activity (jobs being added/removed)
        $recentJobActivity = \DB::table('jobs')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();
        
        // Check failed jobs to see if recent failures
        $recentFailures = \DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subMinutes(5))
            ->exists();
        
        // Check if MonitorCoffeeOrdersJob was dispatched recently
        $lastMonitorJobTime = \DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%MonitorCoffeeOrdersJob%')
            ->value('created_at');
            
        $lastJobTime = null;
        $queueWorkerActive = false;
        
        // Determine last activity time
        if ($lastProcessedOrder) {
            $lastJobTime = $lastProcessedOrder->created_at;
        }
        
        // Worker is considered active if:
        // 1. Very few pending jobs (< 3) - means they're being processed
        // 2. OR recent order was created (within 5 minutes)
        // 3. OR recent job activity with no failures
        // 4. AND NOT many pending jobs
        
        if ($pendingJobs <= 3 && !$recentFailures) {
            // Few pending jobs and no failures = worker is processing them
            $queueWorkerActive = true;
        } elseif ($lastProcessedOrder && $lastProcessedOrder->created_at->diffInMinutes(now()) < 5) {
            // Recent order processed
            $queueWorkerActive = true;
        } elseif ($recentJobActivity && !$recentFailures && $pendingJobs < 50) {
            // Jobs being added but not piling up
            $queueWorkerActive = true;
        }
        
        // Override: If too many pending jobs, worker is definitely not active
        if ($pendingJobs > 100) {
            $queueWorkerActive = false;
        }
        
        // If we've never had activity, but no pending jobs, show as active
        if (!$lastJobTime && $pendingJobs == 0) {
            $queueWorkerActive = true;
            $lastJobTime = now();
        }

        $queueStatus = [
            'active' => $queueWorkerActive,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'last_check' => $lastJobTime ? $lastJobTime->diffForHumans() : 'Never',
            'last_check_time' => $lastJobTime
        ];

        return view('kds.index', compact('orders', 'completedOrders', 'queueStatus'));
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
                        ];
                    }),
                ];
            });

        return response()->json([
            'active' => $activeOrders,
            'completed' => $completedOrders
        ]);
    }

    public function updateStatus(Request $request, KdsOrder $kdsOrder): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:new,viewed,preparing,ready,completed,cancelled'
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
            ]
        ]);
    }

    public function stream(): StreamedResponse
    {
        return response()->stream(function () {
            $lastMonitorCheck = now();
            
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
                    })->toArray(),
                    'completed' => $completedOrders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'ticket_number' => $order->ticket_number,
                            'order_time' => $order->order_time->format('H:i:s'),
                            'completed_time' => $order->completed_at ? $order->completed_at->format('H:i:s') : '',
                            'items' => $order->items->map(function ($item) {
                                return [
                                    'product_name' => $item->display_name,
                                    'quantity' => $item->formatted_quantity,
                                ];
                            })->toArray(),
                        ];
                    })->toArray()
                ]);

                echo "data: {$data}\n\n";
                ob_flush();
                flush();

                // Wait 5 seconds before next update
                sleep(5);

                // Dispatch monitoring job every 10 seconds (was 30)
                // Job is now optimized to run in ~10ms
                if ($lastMonitorCheck->diffInSeconds(now()) >= 10) {
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
            'message' => 'Checking for new orders'
        ]);
    }

    public function clearCompleted(): JsonResponse
    {
        try {
            // Delete completed and cancelled orders older than 1 hour
            $deleted = KdsOrder::whereIn('status', ['completed', 'cancelled'])
                ->where(function($query) {
                    $query->where('completed_at', '<', now()->subHour())
                          ->orWhere('updated_at', '<', now()->subHour());
                })
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$deleted} completed orders"
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to clear completed KDS orders', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear completed orders: ' . $e->getMessage()
            ], 500);
        }
    }

    public function clearAll(): JsonResponse
    {
        // Clear ALL orders (useful for testing/reset)
        try {
            $count = KdsOrder::count();
            
            // Store the current time as the "last clear time" to prevent re-importing old orders
            \DB::table('kds_settings')
                ->updateOrInsert(
                    ['key' => 'last_clear_time'],
                    ['value' => now()->toDateTimeString(), 'updated_at' => now()]
                );
            
            // Clear all orders
            KdsOrder::query()->delete();
            
            \Log::info('KDS Clear All: Successfully cleared orders', [
                'count' => $count,
                'clear_time' => now()->toDateTimeString()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Cleared {$count} orders",
                'debug' => [
                    'cleared_count' => $count,
                    'clear_time' => now()->toDateTimeString(),
                    'info' => 'Orders before this time will not be re-imported'
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to clear KDS orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear orders: ' . $e->getMessage()
            ], 500);
        }
    }
}