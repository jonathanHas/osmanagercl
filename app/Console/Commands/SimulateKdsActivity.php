<?php

namespace App\Console\Commands;

use App\Models\KdsOrder;
use Illuminate\Console\Command;

class SimulateKdsActivity extends Command
{
    protected $signature = 'kds:simulate {action=status : Action to perform (status, complete, clear)}';
    protected $description = 'Simulate KDS activity for testing';

    public function handle(): int
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'status':
                $this->showStatus();
                break;
                
            case 'complete':
                $this->completeRandomOrder();
                break;
                
            case 'clear':
                $this->clearAll();
                break;
                
            case 'demo':
                $this->runDemo();
                break;
                
            default:
                $this->error("Unknown action: {$action}");
                $this->info("Available actions: status, complete, clear, demo");
                return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function showStatus(): void
    {
        $this->info("KDS Order Status:");
        $this->table(
            ['Status', 'Count', 'Oldest Order'],
            [
                ['New', 
                 KdsOrder::where('status', 'new')->count(),
                 KdsOrder::where('status', 'new')->oldest('order_time')->first()?->order_time?->diffForHumans() ?? 'N/A'
                ],
                ['All Active', 
                 KdsOrder::active()->count(),
                 KdsOrder::active()->oldest('order_time')->first()?->order_time?->diffForHumans() ?? 'N/A'
                ],
                ['Completed (30min)', 
                 KdsOrder::where('status', 'completed')->where('completed_at', '>=', now()->subMinutes(30))->count(),
                 'Last 30 minutes'
                ],
                ['Total Today', 
                 KdsOrder::today()->count(),
                 'Since midnight'
                ],
            ]
        );
        
        // Show recent orders
        $recentOrders = KdsOrder::with('items')
            ->active()
            ->orderBy('order_time', 'desc')
            ->limit(5)
            ->get();
            
        if ($recentOrders->count() > 0) {
            $this->info("\nRecent Active Orders:");
            foreach ($recentOrders as $order) {
                $items = $order->items->pluck('product_name')->join(', ');
                $this->line("  #{$order->ticket_number} - {$order->status} - {$items}");
            }
        }
    }
    
    private function completeRandomOrder(): void
    {
        $order = KdsOrder::where('status', 'new')->oldest('order_time')->first();
        
        if (!$order) {
            $this->warn("No active orders to complete");
            return;
        }
        
        $order->complete();
        
        $items = $order->items->pluck('product_name')->join(', ');
        $this->info("✓ Completed order #{$order->ticket_number} with items: {$items}");
        
        $remaining = KdsOrder::active()->count();
        $this->line("  Remaining active orders: {$remaining}");
    }
    
    private function clearAll(): void
    {
        if ($this->confirm('Clear all KDS orders?')) {
            $count = KdsOrder::count();
            KdsOrder::query()->delete();
            $this->info("Cleared {$count} orders");
        }
    }
    
    private function runDemo(): void
    {
        $this->info("Running KDS Demo Mode...");
        $this->info("This will generate orders and complete them randomly.");
        $this->info("Press Ctrl+C to stop.\n");
        
        while (true) {
            // 70% chance to add new order
            if (rand(1, 10) <= 7) {
                $this->call('kds:test-orders', ['count' => 1]);
            }
            
            // 50% chance to complete an order if any exist
            if (rand(1, 10) <= 5) {
                $activeCount = KdsOrder::active()->count();
                if ($activeCount > 0) {
                    $this->completeRandomOrder();
                }
            }
            
            // Show status
            $active = KdsOrder::active()->count();
            $completed = KdsOrder::where('status', 'completed')
                ->where('completed_at', '>=', now()->subMinutes(30))
                ->count();
            
            $this->line("Status: {$active} active, {$completed} recently completed");
            
            // Wait 3-8 seconds
            sleep(rand(3, 8));
        }
    }
}