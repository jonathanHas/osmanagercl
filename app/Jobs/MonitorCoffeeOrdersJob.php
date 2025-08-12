<?php

namespace App\Jobs;

use App\Models\KdsOrder;
use App\Models\KdsOrderItem;
use App\Models\POS\Ticket;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorCoffeeOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            $startTime = microtime(true);

            Log::info('=== MonitorCoffeeOrdersJob START ===', [
                'time' => now()->toDateTimeString(),
            ]);

            // Get the last processed ticket time to avoid duplicates
            $lastProcessedTimeStr = KdsOrder::max('order_time');
            $lastProcessedTime = $lastProcessedTimeStr ? Carbon::parse($lastProcessedTimeStr) : Carbon::now()->subDay();

            // IMPORTANT: Never look back more than 2 hours to prevent getting stuck
            // This handles cases where the system was down or orders were cleared
            $maxLookback = Carbon::now()->subHours(2);
            if ($lastProcessedTime->lt($maxLookback)) {
                Log::warning('Last processed time too old, using max lookback', [
                    'original_cutoff' => $lastProcessedTime->toDateTimeString(),
                    'new_cutoff' => $maxLookback->toDateTimeString(),
                ]);
                $lastProcessedTime = $maxLookback;
            }

            Log::info('Last processed time check', [
                'last_processed_time' => $lastProcessedTime->toDateTimeString(),
            ]);

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
                        'clear_time' => $lastClearTime->toDateTimeString(),
                    ]);
                }
            }

            Log::info('Final cutoff time determined', [
                'cutoff' => $lastProcessedTime->toDateTimeString(),
                'now' => now()->toDateTimeString(),
                'time_window' => 'Last 2 hours max',
            ]);

            // Find new coffee orders from POS - LIMIT to last 2 hours and max 50 tickets
            $ticketQuery = Ticket::with(['ticketLines.product', 'person'])
                ->where('TICKETTYPE', 0); // Normal sales only

            // Log the base query count
            $totalTickets = (clone $ticketQuery)->count();
            Log::info('Total tickets in POS', ['count' => $totalTickets]);

            // Add receipt date filter
            $ticketQuery->where(function ($query) use ($lastProcessedTime) {
                // Get tickets created after our last check
                $query->whereHas('receipt', function ($q) use ($lastProcessedTime) {
                    $q->where('DATENEW', '>', $lastProcessedTime)
                        ->where('DATENEW', '>', Carbon::now()->subHours(2)); // Only check last 2 hours
                });
            });

            $ticketsAfterTime = (clone $ticketQuery)->count();
            Log::info('Tickets after cutoff time', [
                'count' => $ticketsAfterTime,
                'cutoff' => $lastProcessedTime->toDateTimeString(),
            ]);

            // Add coffee category filter
            $ticketQuery->whereHas('ticketLines.product', function ($query) {
                // Filter for Coffee Fresh products (category 081)
                $query->where('CATEGORY', '081');
            });

            $coffeeTickets = (clone $ticketQuery)->count();
            Log::info('Coffee tickets found', ['count' => $coffeeTickets]);

            $newTickets = $ticketQuery->limit(50)->get(); // Process max 50 tickets at a time

            // Batch check for existing orders to reduce queries
            $ticketIds = $newTickets->pluck('ID');
            $existingTicketIds = KdsOrder::whereIn('ticket_id', $ticketIds)
                ->pluck('ticket_id')
                ->toArray();

            Log::info('Existing ticket check', [
                'new_ticket_ids' => $ticketIds->toArray(),
                'existing_in_kds' => $existingTicketIds,
                'will_process' => count($ticketIds) - count($existingTicketIds),
            ]);

            foreach ($newTickets as $ticket) {
                // Skip if we already have this order
                if (in_array($ticket->ID, $existingTicketIds)) {
                    Log::debug('Skipping existing ticket', ['ticket_id' => $ticket->ID]);

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
                                    $modifiers[(string) $attr->getName()] = (string) $attr;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to parse ticket line attributes', [
                                'ticket_id' => $ticket->ID,
                                'line' => $line->LINE,
                                'error' => $e->getMessage(),
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
                    'items_count' => $coffeeLines->count(),
                ]);

                // Trigger event for SSE broadcasting
                event(new \App\Events\CoffeeOrderReceived($kdsOrder));
            }

            // Clean up old completed orders (older than 24 hours)
            $deletedCount = KdsOrder::where('status', 'completed')
                ->where('completed_at', '<', now()->subDay())
                ->delete();

            Log::info('=== MonitorCoffeeOrdersJob END ===', [
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'orders_created' => $newTickets->count() - count($existingTicketIds),
                'old_orders_deleted' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to monitor coffee orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
