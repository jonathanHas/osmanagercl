<?php

namespace App\Jobs;

use App\Models\KdsOrder;
use App\Models\KdsOrderItem;
use App\Models\POS\Ticket;
use App\Models\POS\TicketLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MonitorCoffeeOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            $startTime = microtime(true);
            
            // Get the last processed ticket time to avoid duplicates
            $lastProcessedTime = KdsOrder::max('order_time') ?? Carbon::now()->subDay();
            
            // Check if there's a "last clear time" setting
            $lastClearTime = \DB::table('kds_settings')
                ->where('key', 'last_clear_time')
                ->value('value');
            
            if ($lastClearTime) {
                $lastClearTime = Carbon::parse($lastClearTime);
                // Use the more recent of last processed time or last clear time
                if ($lastClearTime->gt($lastProcessedTime)) {
                    $lastProcessedTime = $lastClearTime;
                    Log::info('Using last clear time as cutoff', [
                        'clear_time' => $lastClearTime->toDateTimeString()
                    ]);
                }
            }
            
            // Find new coffee orders from POS - LIMIT to last 2 hours and max 50 tickets
            $newTickets = Ticket::with(['ticketLines.product', 'person'])
                ->where('TICKETTYPE', 0) // Normal sales only
                ->where(function ($query) use ($lastProcessedTime) {
                    // Get tickets created after our last check
                    $query->whereHas('receipt', function ($q) use ($lastProcessedTime) {
                        $q->where('DATENEW', '>', $lastProcessedTime)
                          ->where('DATENEW', '>', Carbon::now()->subHours(2)); // Only check last 2 hours
                    });
                })
                ->whereHas('ticketLines.product', function ($query) {
                    // Filter for Coffee Fresh products (category 081)
                    $query->where('CATEGORY', '081');
                })
                ->limit(50) // Process max 50 tickets at a time
                ->get();
            
            Log::info('MonitorCoffeeOrdersJob execution', [
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'tickets_found' => $newTickets->count(),
                'last_processed_time' => $lastProcessedTime->toDateTimeString()
            ]);

            // Batch check for existing orders to reduce queries
            $existingTicketIds = KdsOrder::whereIn('ticket_id', $newTickets->pluck('ID'))
                ->pluck('ticket_id')
                ->toArray();

            foreach ($newTickets as $ticket) {
                // Skip if we already have this order
                if (in_array($ticket->ID, $existingTicketIds)) {
                    continue;
                }

                // Get coffee items only
                $coffeeLines = $ticket->ticketLines->filter(function ($line) {
                    return $line->product && $line->product->CATEGORY === '081';
                });

                if ($coffeeLines->isEmpty()) {
                    continue;
                }

                // Extract customer info from ticket if available
                $customerInfo = null;
                if ($ticket->customer) {
                    $customerInfo = [
                        'name' => $ticket->customer->NAME,
                        'searchkey' => $ticket->customer->SEARCHKEY,
                    ];
                }

                // Create KDS order
                $kdsOrder = KdsOrder::create([
                    'ticket_id' => $ticket->ID,
                    'ticket_number' => $ticket->TICKETID ?? 0,
                    'person' => $ticket->PERSON,
                    'status' => 'new',
                    'order_time' => $ticket->receipt ? Carbon::parse($ticket->receipt->DATENEW) : now(),
                    'customer_info' => $customerInfo,
                ]);

                // Add order items
                foreach ($coffeeLines as $line) {
                    // Parse attributes for modifiers (size, milk type, extras)
                    $modifiers = null;
                    if ($line->ATTRIBUTES) {
                        try {
                            $attributes = simplexml_load_string($line->ATTRIBUTES);
                            if ($attributes) {
                                $modifiers = [];
                                foreach ($attributes->children() as $attr) {
                                    $modifiers[(string)$attr->getName()] = (string)$attr;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to parse ticket line attributes', [
                                'ticket_id' => $ticket->ID,
                                'line' => $line->LINE,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    KdsOrderItem::create([
                        'kds_order_id' => $kdsOrder->id,
                        'product_id' => $line->PRODUCT,
                        'product_name' => $line->product->NAME ?? 'Unknown Product',
                        'display_name' => $line->product->DISPLAY ?? null,
                        'quantity' => $line->UNITS,
                        'modifiers' => $modifiers,
                        'notes' => null, // Could extract from attributes if available
                    ]);
                }

                Log::info('New coffee order added to KDS', [
                    'kds_order_id' => $kdsOrder->id,
                    'ticket_id' => $ticket->ID,
                    'items_count' => $coffeeLines->count()
                ]);

                // Trigger event for SSE broadcasting
                event(new \App\Events\CoffeeOrderReceived($kdsOrder));
            }
            
            // Clean up old completed orders (older than 24 hours)
            KdsOrder::where('status', 'completed')
                ->where('completed_at', '<', now()->subDay())
                ->delete();
                
        } catch (\Exception $e) {
            Log::error('Failed to monitor coffee orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }
}