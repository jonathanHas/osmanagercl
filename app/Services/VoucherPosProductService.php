<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps one hidden, zero-price uniCenta product per gift voucher.
 *
 * The product's barcode (CODE) is the voucher code, so scanning the label at
 * the till adds a €0.00 line that identifies the voucher on the ticket; its
 * NAME carries the current balance so the cashier can read it before taking
 * the Voucher tender. uniCenta reads a scanned product from the database on
 * every scan, so a rename shows on the next scan.
 *
 * Writes are limited to PRODUCTS and one CATEGORIES row. Nothing goes into
 * PRODUCTS_CAT (that would put a button on the till), and no STOCKCURRENT or
 * metadata rows are created. Every failure is logged and swallowed: the POS
 * being down must never break voucher generation, activation or redemption.
 */
class VoucherPosProductService
{
    private ?string $categoryId = null;

    /** What the last sync() did: created | linked | renamed | unchanged | failed. */
    private ?string $lastAction = null;

    /**
     * The hidden voucher category's id, created on first use.
     */
    public function categoryId(): string
    {
        if ($this->categoryId !== null) {
            return $this->categoryId;
        }

        $name = config('vouchers.pos_category_name');
        $id = DB::connection('pos')->table('CATEGORIES')->where('NAME', $name)->value('ID');

        if ($id === null) {
            $id = (string) Str::uuid();
            DB::connection('pos')->table('CATEGORIES')->insert([
                'ID' => $id,
                'NAME' => $name,
                'PARENTID' => config('vouchers.pos_category_parent_id'),
                'CATSHOWNAME' => 0,
            ]);
        }

        return $this->categoryId = (string) $id;
    }

    /**
     * e.g. "Gift Voucher GV7KQFM2RA9T [bal €42.50]", or "[for sale €20.00]" for
     * an unsold voucher with a value.
     */
    public function productName(Voucher $voucher): string
    {
        $texts = config('vouchers.pos_balance_text');
        $text = $texts[$voucher->status] ?? $voucher->status;

        if ($voucher->isForSale()) {
            $text = sprintf($texts['for_sale'], number_format((float) $voucher->face_value, 2));
        } elseif ($voucher->status === Voucher::STATUS_ACTIVE) {
            $text = sprintf($text, number_format((float) $voucher->current_balance, 2));
        }

        return sprintf(config('vouchers.pos_name_format'), $voucher->code, $text);
    }

    /**
     * The till price: the face value while the voucher is for sale, so scanning
     * the label as an item charges it; €0.00 otherwise (a redemption line).
     * PRICESELL is ex-VAT, and the product is TAXCAT 000 (0%), so this is also
     * what the customer pays.
     */
    public function productPrice(Voucher $voucher): float
    {
        return $voucher->isForSale() ? round((float) $voucher->face_value, 2) : 0.0;
    }

    /**
     * Ensure the voucher's POS product exists and its NAME and price are current.
     *
     * @return string|null the POS product id, or null when the POS could not be written
     */
    public function sync(Voucher $voucher): ?string
    {
        $this->lastAction = null;

        try {
            $name = $this->productName($voucher);
            $price = $this->productPrice($voucher);
            $action = 'unchanged';

            $product = $voucher->pos_product_id ? Product::find($voucher->pos_product_id) : null;

            if (! $product) {
                // Self-heal after a half-failed create: the product exists but the
                // voucher never learned its id.
                $product = Product::where('CODE', $voucher->code)->first();
                $action = $product ? 'linked' : $action;
            }

            if (! $product) {
                $product = Product::create([
                    'ID' => (string) Str::uuid(),
                    'NAME' => $name,
                    'CODE' => $voucher->code,
                    'REFERENCE' => $voucher->code,
                    'CATEGORY' => $this->categoryId(),
                    'TAXCAT' => config('vouchers.pos_taxcat'),
                    'PRICESELL' => $price,
                    'PRICEBUY' => 0,
                ]);
                // A service product: the till records no stock movement for it.
                $product->forceFill(['ISSERVICE' => 1])->save();
                $action = 'created';
            }

            // A price change is reported as `renamed` too, so callers' totals keep working.
            if ($product->NAME !== $name || round((float) $product->PRICESELL, 2) !== $price) {
                Product::whereKey($product->ID)->update(['NAME' => $name, 'PRICESELL' => $price]);
                $action = $action === 'unchanged' ? 'renamed' : $action;
            }

            if ($voucher->pos_product_id !== $product->ID) {
                $voucher->forceFill(['pos_product_id' => $product->ID])->saveQuietly();
            }

            $this->lastAction = $action;

            return $product->ID;
        } catch (\Throwable $e) {
            Log::warning('Voucher POS product sync failed', [
                'code' => $voucher->code,
                'error' => $e->getMessage(),
            ]);
            $this->lastAction = 'failed';

            return null;
        }
    }

    public function lastAction(): ?string
    {
        return $this->lastAction;
    }

    /**
     * @param  iterable<Voucher>  $vouchers
     * @return array{synced:int, created:int, failed:int}
     */
    public function syncMany(iterable $vouchers): array
    {
        $summary = ['synced' => 0, 'created' => 0, 'failed' => 0];

        foreach ($vouchers as $voucher) {
            if ($this->sync($voucher) === null) {
                $summary['failed']++;

                continue;
            }

            $summary['synced']++;
            if ($this->lastAction === 'created') {
                $summary['created']++;
            }
        }

        return $summary;
    }
}
