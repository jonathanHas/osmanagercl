<?php

namespace App\Http\Controllers;

use App\Models\SupplierLink;
use App\Models\UdeaProductCard;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin tool for pulling Udea's per-product pallet volume figures into the app.
 *
 * The sync shells out to `udea:sync-pallet-volumes` and streams its output rather than
 * doing the work in-request: a basket holding the full range is a very large page, and
 * PHP-FPM here is capped at 30s / 128MB. Under CLI those limits are lifted, and on Unix
 * max_execution_time excludes time spent in external processes.
 */
class UdeaPalletVolumeController extends Controller
{
    public function index(): View
    {
        $withData = UdeaProductCard::whereNotNull('pallet_scraped_at')
            ->pluck('supplier_code')
            ->map(fn ($c) => trim((string) $c))
            ->unique();

        // The stocked-product figures come from the POS database. It is a separate
        // connection that may be unavailable, and the page is still useful without it,
        // so a failure here degrades to "unknown" rather than taking the page down.
        $stockedCount = null;
        $coveredCount = null;

        try {
            $stockedCodes = $this->stockedUdeaCodes();
            $stockedCount = $stockedCodes->count();
            $coveredCount = $stockedCodes->intersect($withData)->count();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('tools.udea-pallet-volumes', [
            'stockedCount' => $stockedCount,
            'coveredCount' => $coveredCount,
            'totalWithData' => $withData->count(),
            'lastSyncedAt' => UdeaProductCard::whereNotNull('pallet_scraped_at')->max('pallet_scraped_at'),
            'capacities' => config('suppliers.external_links.udea.pallet', ['euro' => 250, 'block' => 360]),
        ]);
    }

    /**
     * Udea supplier codes for products we stock, i.e. present in the POS `stocking`
     * whitelist - not necessarily with units on hand right now.
     *
     * supplier_link lives on the read-only POS connection while udea_product_cards is on
     * the app connection, so the two sets are correlated in PHP rather than joined in SQL.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function stockedUdeaCodes(): \Illuminate\Support\Collection
    {
        $udeaIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);

        return SupplierLink::whereIn('SupplierID', $udeaIds)
            ->whereNotNull('SupplierCode')
            ->where('SupplierCode', '!=', '')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('stocking')
                ->whereRaw('stocking.Barcode = supplier_link.Barcode'))
            ->pluck('SupplierCode')
            ->map(fn ($c) => trim((string) $c))
            ->unique();
    }

    public function sync(): StreamedResponse
    {
        // The command can run for minutes on a full basket; don't let FPM cut the stream.
        set_time_limit(0);

        return new StreamedResponse(function () {
            $this->send('start', 'Reading the Udea basket...');

            $command = 'php '.escapeshellarg(base_path('artisan')).' udea:sync-pallet-volumes 2>&1';
            $output = shell_exec($command);

            if (blank(trim((string) $output))) {
                $this->send('error', 'The sync command produced no output. Check the Laravel log.');

                return;
            }

            foreach (preg_split('/\r?\n/', trim($output)) as $line) {
                $this->send('output', $line);
            }

            $this->send('done', 'Finished.');
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function send(string $event, string $message): void
    {
        echo 'data: '.json_encode(['event' => $event, 'message' => $message])."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
