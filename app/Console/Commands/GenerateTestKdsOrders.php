<?php

namespace App\Console\Commands;

use App\Models\KdsOrder;
use App\Models\KdsOrderItem;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateTestKdsOrders extends Command
{
    protected $signature = 'kds:test-orders {count=5 : Number of orders to generate}';
    protected $description = 'Generate test coffee orders for KDS development';

    private $coffeeProducts = [
        ['name' => 'Cappuccino', 'modifiers' => ['size' => 'Regular', 'milk' => 'Whole']],
        ['name' => 'Latte', 'modifiers' => ['size' => 'Large', 'milk' => 'Oat']],
        ['name' => 'Flat White', 'modifiers' => ['size' => 'Regular', 'milk' => 'Skim']],
        ['name' => 'Americano', 'modifiers' => ['size' => 'Large']],
        ['name' => 'Espresso', 'modifiers' => ['shots' => 'Double']],
        ['name' => 'Macchiato', 'modifiers' => ['size' => 'Regular', 'milk' => 'Almond']],
        ['name' => 'Mocha', 'modifiers' => ['size' => 'Large', 'milk' => 'Whole', 'cream' => 'Yes']],
        ['name' => 'Cortado', 'modifiers' => ['milk' => 'Whole']],
        ['name' => 'Tea', 'modifiers' => ['type' => 'Earl Grey']],
        ['name' => 'Hot Chocolate', 'modifiers' => ['size' => 'Large', 'cream' => 'Yes']],
    ];

    private $customerNames = [
        'John', 'Sarah', 'Mike', 'Emma', 'David', 'Lisa', 'Tom', 'Amy', 'Chris', 'Kate',
        'Table 1', 'Table 2', 'Table 3', 'Takeaway', 'Drive-thru'
    ];

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        
        $this->info("Generating {$count} test coffee orders...");
        
        for ($i = 0; $i < $count; $i++) {
            $this->createTestOrder($i);
        }
        
        $this->info("✓ Generated {$count} test orders successfully!");
        $this->info("Visit /kds to see them in the display");
        
        // Show summary
        $this->table(
            ['Status', 'Count'],
            [
                ['New', KdsOrder::where('status', 'new')->count()],
                ['Active (all)', KdsOrder::active()->count()],
                ['Completed', KdsOrder::where('status', 'completed')->count()],
                ['Total Today', KdsOrder::today()->count()],
            ]
        );
        
        return Command::SUCCESS;
    }
    
    private function createTestOrder($index): void
    {
        // Generate a ticket number
        $ticketNumber = 344400 + KdsOrder::count() + $index + 1;
        
        // Random order time in last 30 minutes
        $minutesAgo = rand(0, 30);
        $orderTime = Carbon::now()->subMinutes($minutesAgo);
        
        // Random customer
        $customerName = $this->customerNames[array_rand($this->customerNames)];
        $customerInfo = ['name' => $customerName];
        
        // Create the order
        $order = KdsOrder::create([
            'ticket_id' => 'TEST_' . time() . '_' . $index,
            'ticket_number' => $ticketNumber,
            'person' => 'TEST_USER',
            'status' => 'new',
            'order_time' => $orderTime,
            'customer_info' => $customerInfo,
        ]);
        
        // Add 1-3 items to the order
        $itemCount = rand(1, 3);
        for ($j = 0; $j < $itemCount; $j++) {
            $product = $this->coffeeProducts[array_rand($this->coffeeProducts)];
            
            KdsOrderItem::create([
                'kds_order_id' => $order->id,
                'product_id' => 'TEST_PRODUCT_' . $j,
                'product_name' => $product['name'],
                'display_name' => $product['name'],
                'quantity' => rand(1, 2),
                'modifiers' => $product['modifiers'] ?? null,
                'notes' => rand(0, 10) > 8 ? 'Extra hot' : null, // 20% chance of note
            ]);
        }
        
        $this->line("  Created order #{$ticketNumber} for {$customerName} with {$itemCount} items");
    }
}