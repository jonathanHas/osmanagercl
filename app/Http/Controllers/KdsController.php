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

        // Check queue worker status
        $pendingJobs = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();
        
        // Check if any job was processed recently (within last 5 minutes)
        $lastProcessedOrder = KdsOrder::orderBy('created_at', 'desc')->first();
        
        // Also check the jobs table to see if monitoring is happening
        // Even if no orders exist, the monitoring job should be running
        $recentJobActivity = \DB::table('jobs')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();
        
        // Check failed jobs to see if recent failures
        $recentFailures = \DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subMinutes(5))
            ->exists();
        
        $lastJobTime = null;
        $queueWorkerActive = false;
        
        if ($lastProcessedOrder) {
            $lastJobTime = $lastProcessedOrder->created_at;
            // Consider worker active if processed something in last 5 minutes
            $queueWorkerActive = $lastProcessedOrder->created_at->diffInMinutes(now()) < 5;
        }
        
        // If there are no pending jobs and no recent failures, worker is likely active
        if ($pendingJobs == 0 && !$recentFailures) {
            $queueWorkerActive = true;
            // If we've never had an order, show "System Active" instead of "Never"
            if (!$lastJobTime) {
                $lastJobTime = now();
            }
        }
        
        // If there are many pending jobs, worker is definitely not active
        if ($pendingJobs > 10) {
            $queueWorkerActive = false;
        }

        $queueStatus = [
            'active' => $queueWorkerActive,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'last_check' => $lastJobTime ? $lastJobTime->diffForHumans() : 'Never',
            'last_check_time' => $lastJobTime
        ];

        return view('kds.index', compact('orders', 'queueStatus'));
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
            $lastMonitorCheck = now();
            
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

                // Only dispatch monitoring job every 30 seconds instead of every 5 seconds
                // This prevents job queue backlog since each job takes ~17 seconds
                if ($lastMonitorCheck->diffInSeconds(now()) >= 30) {
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
            KdsOrder::query()->delete(); // Use delete() instead of truncate() to get count
            
            return response()->json([
                'success' => true,
                'message' => "Cleared {$count} orders"
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to clear KDS orders', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear orders: ' . $e->getMessage()
            ], 500);
        }
    }
}