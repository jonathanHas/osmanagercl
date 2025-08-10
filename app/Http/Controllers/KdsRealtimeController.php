<?php

namespace App\Http\Controllers;

use App\Models\POS\Ticket;
use App\Models\KdsOrder;
use App\Models\KdsOrderItem;
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
            
            // Find new coffee orders - SIMPLIFIED QUERY
            $newOrders = DB::connection('pos')
                ->table('TICKETS as t')
                ->join('RECEIPTS as r', 't.ID', '=', 'r.ID')
                ->join('TICKETLINES as tl', 't.ID', '=', 'tl.TICKET')
                ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
                ->where('r.DATENEW', '>', $lastProcessedTime)
                ->where('p.CATEGORY', '081') // Coffee category
                ->where('t.TICKETTYPE', 0) // Normal sales
                ->select('t.ID', 't.TICKETID', 'r.DATENEW', 't.PERSON')
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
                    'status' => 'new',
                    'order_time' => Carbon::parse($ticket->DATENEW),
                ]);
                
                // Get ticket lines for this order
                $lines = DB::connection('pos')
                    ->table('TICKETLINES as tl')
                    ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
                    ->where('tl.TICKET', $ticket->ID)
                    ->where('p.CATEGORY', '081')
                    ->select('tl.*', 'p.NAME', 'p.DISPLAY')
                    ->get();
                
                foreach ($lines as $line) {
                    KdsOrderItem::create([
                        'kds_order_id' => $kdsOrder->id,
                        'product_id' => $line->PRODUCT,
                        'product_name' => $line->NAME ?? 'Unknown',
                        'display_name' => $line->DISPLAY ?? $line->NAME,
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
                'checked_at' => now()->toDateTimeString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Realtime check failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}