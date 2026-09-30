<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductsCat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Admin changeover tool (vouchers cycle 4): take the three old fixed voucher
 * products ("Voucher 10/20/50 Euro", config('vouchers.legacy_product_codes'))
 * off the till now that vouchers are sold by scanning their own label.
 *
 * Retiring removes the till button, adds " (retired)" to the name and changes
 * the barcode to "RET" + the old code, so keying the old code finds nothing.
 * The product row stays: past sales refer to it. --restore puts all of it back.
 * Never touches a voucher product, a ticket table or any other product.
 */
class RetireFixedVoucherProducts extends Command
{
    private const SUFFIX = ' (retired)';

    private const PREFIX = 'RET';

    protected $signature = 'vouchers:retire-fixed-products
                            {--dry-run : Show what would change without writing}
                            {--restore : Put retired products back (code, name and till button)}';

    protected $description = 'Take the old fixed voucher products (Voucher 10/20/50 Euro) off the till, or put them back';

    public function handle(): int
    {
        if (! config('vouchers.admin_tools')) {
            $this->error('The voucher admin tools are switched off (VOUCHER_ADMIN_TOOLS=false). Nothing was changed.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $restore = (bool) $this->option('restore');
        $rows = [];

        foreach ((array) config('vouchers.legacy_product_codes') as $code) {
            $code = (string) $code;
            $rows[] = $restore ? $this->restoreOne($code, $dryRun) : $this->retireOne($code, $dryRun);
        }

        $this->table(['code', 'name', 'till button', 'action'], $rows);

        if ($dryRun) {
            $this->info('Dry run: nothing was written.');
        }
        $this->line('Tills load their buttons at start-up: restart uniCenta on each till to see the change.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function retireOne(string $code, bool $dryRun): array
    {
        $product = Product::where('CODE', $code)->first();

        if (! $product) {
            $retired = Product::where('CODE', self::PREFIX.$code)->first();

            return $retired
                ? [$code, $retired->NAME, $this->button($retired->ID), 'already retired']
                : [$code, '—', '—', 'not found'];
        }

        $hadButton = $this->button($product->ID);

        if ($dryRun) {
            return [$code, $product->NAME, $hadButton, 'would retire → '.self::PREFIX.$code];
        }

        DB::connection('pos')->transaction(function () use ($product, $code) {
            ProductsCat::removeProduct($product->ID);

            $name = str_ends_with($product->NAME, self::SUFFIX) ? $product->NAME : $product->NAME.self::SUFFIX;

            Product::whereKey($product->ID)->update([
                'NAME' => $name,
                'CODE' => self::PREFIX.$code,
                'REFERENCE' => self::PREFIX.$code,
            ]);
        });

        $product->refresh();

        return [$code, $product->NAME, $hadButton.' → '.$this->button($product->ID), 'retired → '.$product->CODE];
    }

    /**
     * @return array<int, string>
     */
    private function restoreOne(string $code, bool $dryRun): array
    {
        $product = Product::where('CODE', self::PREFIX.$code)->first();

        if (! $product) {
            $current = Product::where('CODE', $code)->first();

            return $current
                ? [$code, $current->NAME, $this->button($current->ID), 'not retired']
                : [$code, '—', '—', 'not found'];
        }

        $hadButton = $this->button($product->ID);

        if ($dryRun) {
            return [$product->CODE, $product->NAME, $hadButton, 'would restore → '.$code];
        }

        DB::connection('pos')->transaction(function () use ($product, $code) {
            $name = str_ends_with($product->NAME, self::SUFFIX)
                ? substr($product->NAME, 0, -strlen(self::SUFFIX))
                : $product->NAME;

            Product::whereKey($product->ID)->update([
                'NAME' => $name,
                'CODE' => $code,
                'REFERENCE' => $code,
            ]);

            if (! ProductsCat::isProductVisible($product->ID)) {
                ProductsCat::addProduct($product->ID);
            }
        });

        $product->refresh();

        return [$product->CODE, $product->NAME, $hadButton.' → '.$this->button($product->ID), 'restored'];
    }

    private function button(string $productId): string
    {
        return ProductsCat::isProductVisible($productId) ? 'yes' : 'no';
    }
}
