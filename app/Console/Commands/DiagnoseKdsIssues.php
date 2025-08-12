<?php

namespace App\Console\Commands;

use App\Models\KdsOrder;
use App\Models\POS\Ticket;
use App\Models\Product;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

class DiagnoseKdsIssues extends Command
{
    protected $signature = 'kds:diagnose {--minutes=30 : How many minutes back to check}';

    protected $description = 'Diagnose why KDS orders are not being detected';

    public function handle(): int
    {
        $minutes = $this->option('minutes');
        $since = Carbon::now()->subMinutes($minutes);

        $this->info('=== KDS Diagnosis Report ===');
        $this->info("Checking last {$minutes} minutes (since {$since->toDateTimeString()})");
        $this->newLine();

        // 1. Check last KDS order
        $lastKdsOrder = KdsOrder::latest('order_time')->first();
        if ($lastKdsOrder) {
            $this->info("Last KDS order: #{$lastKdsOrder->ticket_number} at {$lastKdsOrder->order_time}");
        } else {
            $this->warn('No KDS orders found');
        }
        $this->newLine();

        // 2. Check POS connection
        try {
            $posTicketCount = DB::connection('pos')->table('TICKETS')->count();
            $this->info("✓ POS database connected - Total tickets: {$posTicketCount}");
        } catch (\Exception $e) {
            $this->error('✗ POS database connection failed: '.$e->getMessage());

            return Command::FAILURE;
        }
        $this->newLine();

        // 3. Check recent tickets with receipts
        $recentTickets = Ticket::whereHas('receipt', function ($q) use ($since) {
            $q->where('DATENEW', '>', $since);
        })->count();
        $this->info("Tickets with receipts since {$since->format('H:i')}: {$recentTickets}");

        // 4. Check Coffee Fresh category
        $this->info("\n=== Coffee Category Check ===");

        // Find products in category 081
        $coffeeProducts = Product::where('CATEGORY', '081')->limit(5)->get();
        if ($coffeeProducts->count() > 0) {
            $this->info("✓ Found {$coffeeProducts->count()} products in category 081 (Coffee Fresh):");
            foreach ($coffeeProducts as $product) {
                $this->line("  - {$product->NAME}");
            }
        } else {
            $this->warn('✗ No products found in category 081');

            // Try to find coffee products by name
            $this->info("\nSearching for coffee products by name...");
            $possibleCoffee = Product::where('NAME', 'LIKE', '%coffee%')
                ->orWhere('NAME', 'LIKE', '%latte%')
                ->orWhere('NAME', 'LIKE', '%cappuccino%')
                ->orWhere('NAME', 'LIKE', '%espresso%')
                ->limit(5)
                ->get();

            if ($possibleCoffee->count() > 0) {
                $this->info('Found potential coffee products:');
                foreach ($possibleCoffee as $product) {
                    $this->line("  - {$product->NAME} (Category: {$product->CATEGORY})");
                }

                // Get unique categories
                $categories = $possibleCoffee->pluck('CATEGORY')->unique();
                $this->warn('These products are in categories: '.$categories->implode(', '));
            }
        }

        // 5. Check for recent coffee orders
        $this->info("\n=== Recent Coffee Orders Check ===");

        $coffeeTickets = Ticket::whereHas('receipt', function ($q) use ($since) {
            $q->where('DATENEW', '>', $since);
        })
            ->whereHas('ticketLines.product', function ($q) {
                $q->where('CATEGORY', '081');
            })
            ->with(['receipt', 'ticketLines.product'])
            ->limit(5)
            ->get();

        if ($coffeeTickets->count() > 0) {
            $this->info("✓ Found {$coffeeTickets->count()} coffee orders:");
            foreach ($coffeeTickets as $ticket) {
                $this->line("  Ticket #{$ticket->TICKETID} at {$ticket->receipt->DATENEW}");
                $coffeeItems = $ticket->ticketLines->filter(function ($line) {
                    return $line->product && $line->product->CATEGORY === '081';
                });
                foreach ($coffeeItems as $line) {
                    $this->line("    - {$line->UNITS}x {$line->product->NAME}");
                }
            }
        } else {
            $this->warn("✗ No coffee orders found in the last {$minutes} minutes");

            // Check if there are ANY recent tickets
            $anyRecentTickets = Ticket::whereHas('receipt', function ($q) use ($since) {
                $q->where('DATENEW', '>', $since);
            })->limit(3)->get();

            if ($anyRecentTickets->count() > 0) {
                $this->info("\nFound {$anyRecentTickets->count()} non-coffee tickets:");
                foreach ($anyRecentTickets as $ticket) {
                    $this->line("  Ticket #{$ticket->TICKETID} at ".($ticket->receipt ? $ticket->receipt->DATENEW : 'no receipt'));
                }
            }
        }

        // 6. Check KDS settings
        $this->info("\n=== KDS Settings ===");
        $clearTime = DB::table('kds_settings')->where('key', 'last_clear_time')->value('value');
        if ($clearTime) {
            $this->info("Last clear time: {$clearTime}");
            $this->warn('Orders before this time will not be imported');
        } else {
            $this->info('No clear time set - importing all orders');
        }

        // 7. Check queue status
        $this->info("\n=== Queue Status ===");
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $this->info("Pending jobs: {$pendingJobs}");
        $this->info("Failed jobs: {$failedJobs}");

        $this->newLine();
        $this->info('=== End Diagnosis ===');

        return Command::SUCCESS;
    }
}
