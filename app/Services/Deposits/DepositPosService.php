<?php

namespace App\Services\Deposits;

use App\Models\BarrelCode;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Support\PosProductAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only writer of customer-deposit data on the till (uniCenta).
 *
 * Owns the deposit and refund products of each tier (one "Bottle Deposits"
 * category, the refund products as catalogue buttons) and the deposit.*
 * properties in PRODUCTS.ATTRIBUTES of every mapped product. The till's
 * script.Deposit.AddLine reads those properties on each scan and adds the
 * deposit line (docs/deposit/). Writes nothing else on the POS.
 *
 * CATEGORIES and PRODUCTS_CAT have no Eloquent model; the query builder is
 * used for those two tables only.
 */
class DepositPosService
{
    public const CATEGORY_NAME = 'Bottle Deposits';

    /** Zero-rated, as Udea charges the deposit (owner decision 2026-10-03). */
    public const TAXCAT = '000';

    private ?string $categoryId = null;

    /** @var array<int, string> tier id => deposit product id, for tiers already ensured by this instance */
    private array $ensured = [];

    /**
     * Make sure the tier's deposit and refund products exist on the till and
     * remember their ids on the tier. Existing products are adopted by CODE.
     */
    public function ensureTierProducts(BarrelCode $tier): BarrelCode
    {
        $price = $this->price($tier);
        $code = $this->chargeCode($tier);

        $charge = $this->ensureProduct($code, 'Bottle deposit '.$price, (float) $price);
        $refund = $this->ensureProduct($code.'-RET', 'Bottle deposit refund '.$price, -(float) $price);
        $this->ensureCatalogueButton($refund->ID);

        if ($tier->pos_product_id !== $charge->ID || $tier->pos_refund_product_id !== $refund->ID) {
            $tier->forceFill([
                'pos_product_id' => $charge->ID,
                'pos_refund_product_id' => $refund->ID,
            ])->save();
        }

        return $tier;
    }

    /**
     * Bring one product's deposit properties on the till in line with its row.
     *
     * @return string written | unchanged | cleared | refused | missing
     */
    public function syncProduct(ProductDeposit $row, bool $dryRun = false): string
    {
        $product = Product::find($row->product_id);

        if (! $product) {
            return $this->finish($row, 'missing', 'product not on till', $dryRun);
        }

        $current = $product->ATTRIBUTES;
        $tier = $row->barrelCode;

        if ($row->status === ProductDeposit::STATUS_CONFIRMED && $tier?->charge_customer) {
            if ($product->ISSCALE || (int) $product->ISVPRICE !== 0) {
                return $this->finish($row, 'refused', 'weighed or variable-price product: no deposit line', $dryRun);
            }

            if ($dryRun) {
                $depositId = $this->expectedDepositId($tier);
            } else {
                $depositId = $this->ensured[$tier->id] ??= $this->ensureTierProducts($tier)->pos_product_id;
            }
            $price = $this->price($tier);
            $wanted = PosProductAttributes::withDeposit($current, (string) $depositId, 'Bottle deposit '.$price, $price);

            if ($this->sameDeposit($current, $wanted)) {
                return $this->finish($row, 'unchanged', null, $dryRun);
            }

            if (! $dryRun) {
                Product::whereKey($product->ID)->update(['ATTRIBUTES' => $wanted]);
            }

            return $this->finish($row, 'written', null, $dryRun);
        }

        if (! PosProductAttributes::hasDeposit($current)) {
            return $this->finish($row, 'unchanged', null, $dryRun);
        }

        if (! $dryRun) {
            Product::whereKey($product->ID)->update(['ATTRIBUTES' => PosProductAttributes::withoutDeposit($current)]);
        }

        return $this->finish($row, 'cleared', null, $dryRun);
    }

    /**
     * Sync every row, then clear deposit properties from till products that no
     * confirmed row accounts for.
     *
     * @return array{totals: array<string, int>, changes: array<int, array{code: string, name: string, outcome: string}>}
     */
    public function syncAll(bool $dryRun = false): array
    {
        $totals = ['written' => 0, 'unchanged' => 0, 'cleared' => 0, 'refused' => 0, 'missing' => 0, 'stray_cleared' => 0];
        $changes = [];

        // Every charged tier has its deposit and refund products, so a refund
        // button exists even before the tier's first product is confirmed.
        if (! $dryRun) {
            foreach (BarrelCode::where('charge_customer', true)->get() as $tier) {
                $this->ensured[$tier->id] ??= $this->ensureTierProducts($tier)->pos_product_id;
            }
        }

        foreach (ProductDeposit::with('barrelCode')->orderBy('id')->get() as $row) {
            $outcome = $this->syncProduct($row, $dryRun);
            $totals[$outcome]++;
            if ($outcome !== 'unchanged') {
                $changes[] = ['code' => (string) $row->product_code, 'name' => (string) $row->product?->NAME, 'outcome' => $outcome];
            }
        }

        // Rows were handled above; a stray is a till product no row knows about.
        foreach ($this->strays(ProductDeposit::pluck('product_id')->all()) as $product) {
            if (! $dryRun) {
                Product::whereKey($product->ID)->update(['ATTRIBUTES' => PosProductAttributes::withoutDeposit($product->ATTRIBUTES)]);
            }
            $totals['stray_cleared']++;
            $changes[] = ['code' => (string) $product->CODE, 'name' => (string) $product->NAME, 'outcome' => 'stray_cleared'];
        }

        return ['totals' => $totals, 'changes' => $changes];
    }

    /**
     * Read-only drift report.
     *
     * @return array{rows: array<int, array<string, mixed>>, strays: array<int, array{code: string, name: string, deposit_id: string|null}>, tiers: array<int, array<string, mixed>>, in_sync: int, drifted: int}
     */
    public function check(): array
    {
        $rows = [];
        $inSync = 0;

        foreach (ProductDeposit::with('barrelCode')->confirmed()->orderBy('id')->get() as $row) {
            $product = Product::find($row->product_id);
            $tier = $row->barrelCode;
            $actual = PosProductAttributes::parse($product?->ATTRIBUTES);

            $charges = $tier?->charge_customer && $product && ! $product->ISSCALE && (int) $product->ISVPRICE === 0;
            $expectedId = $charges ? $tier->pos_product_id : null;
            $expectedPrice = $charges ? $this->price($tier) : null;
            $ok = $product !== null
                && ($actual['deposit.id'] ?? null) === $expectedId
                && ($actual['deposit.price'] ?? null) === $expectedPrice
                && ($expectedId !== null || ! $charges);

            if ($ok) {
                $inSync++;
            }
            $rows[] = [
                'code' => (string) ($product?->CODE ?? $row->product_code),
                'name' => (string) ($product?->NAME ?? 'not on till'),
                'expected_id' => $expectedId,
                'actual_id' => $actual['deposit.id'] ?? null,
                'expected_price' => $expectedPrice,
                'actual_price' => $actual['deposit.price'] ?? null,
                'ok' => $ok,
            ];
        }

        $strays = $this->strays(ProductDeposit::confirmed()->pluck('product_id')->all())->map(fn (Product $p) => [
            'code' => (string) $p->CODE,
            'name' => (string) $p->NAME,
            'deposit_id' => PosProductAttributes::depositId($p->ATTRIBUTES),
        ])->values()->all();

        $tiers = BarrelCode::where('charge_customer', true)->orderBy('supplier_code')->get()->map(fn (BarrelCode $tier) => [
            'code' => $tier->supplier_code,
            'price' => $this->price($tier),
            'pos_product_id' => $tier->pos_product_id,
            'pos_refund_product_id' => $tier->pos_refund_product_id,
            'on_till' => $tier->pos_product_id !== null
                && Product::whereKey($tier->pos_product_id)->exists()
                && $tier->pos_refund_product_id !== null
                && Product::whereKey($tier->pos_refund_product_id)->exists(),
        ])->all();

        return [
            'rows' => $rows,
            'strays' => $strays,
            'tiers' => $tiers,
            'in_sync' => $inSync,
            'drifted' => count($rows) - $inSync,
        ];
    }

    /** "0.25" */
    public function price(BarrelCode $tier): string
    {
        return number_format((float) $tier->unit_price, 2, '.', '');
    }

    /** "DEP-025" */
    public function chargeCode(BarrelCode $tier): string
    {
        return sprintf('DEP-%03d', (int) round((float) $tier->unit_price * 100));
    }

    /**
     * Till products carrying deposit properties, other than the given ones.
     *
     * @param  array<int, string>  $accountedFor  product ids
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function strays(array $accountedFor)
    {
        return Product::where('ATTRIBUTES', 'like', '%deposit.id%')
            ->get(['ID', 'CODE', 'NAME', 'ATTRIBUTES'])
            ->reject(fn (Product $p) => in_array($p->ID, $accountedFor, true))
            ->filter(fn (Product $p) => PosProductAttributes::hasDeposit($p->ATTRIBUTES))
            ->values();
    }

    private function expectedDepositId(BarrelCode $tier): string
    {
        return $tier->pos_product_id
            ?? Product::where('CODE', $this->chargeCode($tier))->value('ID')
            ?? '(new '.$this->chargeCode($tier).')';
    }

    /**
     * The deposit entries match (other keys and the comment are not compared).
     */
    private function sameDeposit(?string $current, string $wanted): bool
    {
        $have = PosProductAttributes::parse($current);
        $want = PosProductAttributes::parse($wanted);

        foreach (PosProductAttributes::DEPOSIT_KEYS as $key) {
            if (($have[$key] ?? null) !== ($want[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function finish(ProductDeposit $row, string $outcome, ?string $error, bool $dryRun): string
    {
        if (! $dryRun) {
            $row->forceFill([
                'pos_synced_at' => $error === null ? now() : $row->pos_synced_at,
                'pos_sync_error' => $error,
            ])->save();
        }

        return $outcome;
    }

    private function ensureProduct(string $code, string $name, float $price): Product
    {
        $product = Product::where('CODE', $code)->first();

        if (! $product) {
            $product = Product::create([
                'ID' => (string) Str::uuid(),
                'NAME' => $name,
                'CODE' => $code,
                'REFERENCE' => $code,
                'CATEGORY' => $this->categoryId(),
                'TAXCAT' => self::TAXCAT,
                'PRICESELL' => $price,
                'PRICEBUY' => 0,
            ]);
            // A service product: the till records no stock movement for it.
            $product->forceFill(['ISSERVICE' => 1])->save();

            return $product;
        }

        if ($product->NAME !== $name || round((float) $product->PRICESELL, 2) !== round($price, 2)) {
            Product::whereKey($product->ID)->update(['NAME' => $name, 'PRICESELL' => $price]);
            $product->refresh();
        }

        return $product;
    }

    private function ensureCatalogueButton(string $productId): void
    {
        $pos = DB::connection('pos');

        if ($pos->table('PRODUCTS_CAT')->where('PRODUCT', $productId)->exists()) {
            return;
        }

        $refundIds = Product::where('CATEGORY', $this->categoryId())->pluck('ID');
        $next = (int) $pos->table('PRODUCTS_CAT')->whereIn('PRODUCT', $refundIds)->max('CATORDER') + 1;

        $pos->table('PRODUCTS_CAT')->insert(['PRODUCT' => $productId, 'CATORDER' => $next]);
    }

    private function categoryId(): string
    {
        if ($this->categoryId !== null) {
            return $this->categoryId;
        }

        $pos = DB::connection('pos');
        $id = $pos->table('CATEGORIES')->where('NAME', self::CATEGORY_NAME)->value('ID');

        if ($id === null) {
            $id = (string) Str::uuid();
            $pos->table('CATEGORIES')->insert([
                'ID' => $id,
                'NAME' => self::CATEGORY_NAME,
                'PARENTID' => null,
                'CATSHOWNAME' => 1,
            ]);
        }

        return $this->categoryId = (string) $id;
    }
}
