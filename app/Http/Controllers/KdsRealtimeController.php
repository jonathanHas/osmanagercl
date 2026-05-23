<?php

namespace App\Http\Controllers;

use App\Models\KdsOrder;
use App\Models\KdsOrderItem;
use App\Models\KdsProduct;
use App\Models\POS\Ticket;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KdsRealtimeController extends Controller
{
    public function checkNewOrders(): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Get the last processed ticket time
            $lastProcessedTime = KdsOrder::max('order_time') ?? Carbon::now()->subHours(2);
            $lastProcessedTime = Carbon::parse($lastProcessedTime);

            // Never look back more than 2 hours
            $maxLookback = Carbon::now()->subHours(2);
            if ($lastProcessedTime->lt($maxLookback)) {
                $lastProcessedTime = $maxLookback;
            }

            // Full allow-list (primary + companion) and primary-only subset.
            // Eligibility uses primaries (a companion alone can't trigger a KDS entry);
            // line inclusion uses the full list so companions ride along when a primary is present.
            // Excluder products suppress the whole KDS entry when present on a ticket.
            $kdsProductIds = KdsProduct::active()->pluck('product_id')->all();
            $primaryIds = KdsProduct::active()->primary()->pluck('product_id')->all();
            $excluderIds = KdsProduct::active()->excluder()->pluck('product_id')->all();
            // product_id => trigger_mode for snapshotting 'kind' onto each item.
            $kindMap = KdsProduct::active()->pluck('trigger_mode', 'product_id');

            if (empty($primaryIds)) {
                return response()->json([
                    'success' => true,
                    'orders_created' => 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'checked_at' => now()->toDateTimeString(),
                    'note' => 'No active primary KDS products configured.',
                ]);
            }

            // Find new tickets containing at least one primary KDS product,
            // excluding tickets that also contain an excluder (e.g. "Served Already").
            $newOrders = DB::connection('pos')
                ->table('TICKETS as t')
                ->join('RECEIPTS as r', 't.ID', '=', 'r.ID')
                ->join('TICKETLINES as tl', 't.ID', '=', 'tl.TICKET')
                ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
                ->leftJoin('PEOPLE as pp', 't.PERSON', '=', 'pp.ID')
                ->where('r.DATENEW', '>', $lastProcessedTime)
                ->whereIn('p.ID', $primaryIds)
                ->where('t.TICKETTYPE', 0) // Normal sales
                ->when(! empty($excluderIds), fn ($q) => $q->whereNotIn('t.ID', function ($sub) use ($excluderIds) {
                    $sub->from('TICKETLINES')->select('TICKET')->whereIn('PRODUCT', $excluderIds);
                }))
                ->select('t.ID', 't.TICKETID', 'r.DATENEW', 't.PERSON', 'pp.NAME as person_name')
                ->distinct()
                ->limit(10)
                ->get();

            $ordersCreated = 0;

            foreach ($newOrders as $ticket) {
                // Check if already exists
                if (KdsOrder::where('ticket_id', $ticket->ID)->exists()) {
                    continue;
                }

                // Create KDS order
                $kdsOrder = KdsOrder::create([
                    'ticket_id' => $ticket->ID,
                    'ticket_number' => $ticket->TICKETID ?? 0,
                    'person' => $ticket->PERSON,
                    'person_name' => $ticket->person_name ?? null,
                    'status' => 'new',
                    'order_time' => Carbon::parse($ticket->DATENEW),
                ]);

                // Get ticket lines for this order (only KDS-enabled products)
                $lines = DB::connection('pos')
                    ->table('TICKETLINES as tl')
                    ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
                    ->where('tl.TICKET', $ticket->ID)
                    ->whereIn('p.ID', $kdsProductIds)
                    ->select('tl.*', 'p.NAME', 'p.DISPLAY')
                    ->get();

                foreach ($lines as $line) {
                    KdsOrderItem::create([
                        'kds_order_id' => $kdsOrder->id,
                        'product_id' => $line->PRODUCT,
                        'product_name' => $line->NAME ?? 'Unknown',
                        'display_name' => KdsOrderItem::cleanPosDisplay($line->DISPLAY) ?? $line->NAME,
                        'kind' => ($kindMap[$line->PRODUCT] ?? 'primary') === 'companion' ? 'bakery' : 'drink',
                        'quantity' => $line->UNITS ?? 1,
                    ]);
                }

                $ordersCreated++;
                Log::info('New coffee order added', ['ticket' => $ticket->TICKETID]);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'orders_created' => $ordersCreated,
                'duration_ms' => $duration,
                'checked_at' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Realtime check failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
