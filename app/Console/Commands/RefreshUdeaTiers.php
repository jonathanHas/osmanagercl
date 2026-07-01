<?php

namespace App\Console\Commands;

use App\Models\OrderSession;
use App\Models\SupplierLink;
use App\Models\UdeaProductCard;
use App\Services\UdeaScrapingService;
use Illuminate\Console\Command;

/**
 * Manually (re)populate the durable Udea tier cache (udea_product_cards).
 *
 * Since warming is lazy (no schedule), this is the on-demand way to update values in bulk:
 *   php artisan udea:refresh-tiers                 # all Udea single-unit products, missing/stale only
 *   php artisan udea:refresh-tiers --order=86      # just that order's single-unit products
 *   php artisan udea:refresh-tiers --force         # re-scrape even fresh records
 *   php artisan udea:refresh-tiers --limit=50      # cap how many are scraped
 */
class RefreshUdeaTiers extends Command
{
    protected $signature = 'udea:refresh-tiers
        {--order= : Restrict to a single order id (its case-units=1 line items)}
        {--force : Re-scrape even records that are still fresh}
        {--limit=0 : Maximum number of products to scrape (0 = no limit)}';

    protected $description = 'Populate/refresh the durable Udea case/single-unit tier cache';

    public function handle(UdeaScrapingService $udeaService): int
    {
        $codes = $this->resolveCodes();
        if ($codes->isEmpty()) {
            $this->warn('No Udea single-unit products found for the given scope.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        // Skip products already cached fresh unless --force.
        if (! $force) {
            $freshCutoff = now()->subDays(UdeaScrapingService::CACHE_STALE_DAYS);
            $freshCodes = UdeaProductCard::whereIn('supplier_code', $codes->all())
                ->where('scraped_at', '>', $freshCutoff)
                ->pluck('supplier_code')
                ->all();
            $codes = $codes->reject(fn ($c) => in_array($c, $freshCodes, true))->values();
        }

        if ($limit > 0) {
            $codes = $codes->take($limit)->values();
        }

        if ($codes->isEmpty()) {
            $this->info('Nothing to do — all in-scope products are already cached fresh.');

            return self::SUCCESS;
        }

        $this->info("Scraping {$codes->count()} Udea product(s)...");
        $bar = $this->output->createProgressBar($codes->count());
        $bar->start();

        $scraped = 0;
        $singleAvail = 0;
        $failed = 0;

        foreach ($codes as $code) {
            try {
                $res = $udeaService->debugProductCard((string) $code, true);
                if (! empty($res['data'])) {
                    $scraped++;
                    if (! empty($res['data']['single_unit_available'])) {
                        $singleAvail++;
                    }
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  {$code}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Scraped: {$scraped}, single-unit available: {$singleAvail}, no data/failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveCodes(): \Illuminate\Support\Collection
    {
        $udeaIds = array_map('intval', config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]));

        if ($orderId = $this->option('order')) {
            $order = OrderSession::with('items.product.supplierLink')->find($orderId);
            if (! $order) {
                $this->error("Order {$orderId} not found.");

                return collect();
            }

            return $order->items
                ->map(function ($item) {
                    $link = optional($item->product)->supplierLink;
                    $context = $item->context_data ?? [];
                    $case = $link->CaseUnits ?? ($context['case_units'] ?? null);
                    $code = $context['supplier_code'] ?? optional($link)->SupplierCode;

                    return ($case !== null && (int) $case === 1 && ! empty($code)) ? (string) $code : null;
                })
                ->filter()
                ->unique()
                ->values();
        }

        // All Udea single-unit products.
        return SupplierLink::whereIn('SupplierID', $udeaIds)
            ->where('CaseUnits', 1)
            ->whereNotNull('SupplierCode')
            ->pluck('SupplierCode')
            ->map(fn ($c) => (string) $c)
            ->filter(fn ($c) => $c !== '')
            ->unique()
            ->values();
    }
}
