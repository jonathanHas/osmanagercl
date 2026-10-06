<?php

namespace Tests\Feature;

use App\Models\BarrelCode;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Services\Deposits\DepositPosService;
use App\Support\PosProductAttributes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * Deposit till products and the deposit.* product properties (deposit cycle 2).
 */
class DepositPosServiceTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
    }

    private function service(): DepositPosService
    {
        return app(DepositPosService::class);
    }

    private function tier(string $code = '313', float $price = 0.25, bool $charge = true): BarrelCode
    {
        return BarrelCode::create([
            'supplier_code' => $code,
            'supplier_id' => 5,
            'description' => "Statiegeld $code",
            'unit_price' => $price,
            'is_active' => true,
            'charge_customer' => $charge,
        ]);
    }

    private function row(string $productId, BarrelCode $tier, string $status = ProductDeposit::STATUS_CONFIRMED): ProductDeposit
    {
        return ProductDeposit::create([
            'product_id' => $productId,
            'barrel_code_id' => $tier->id,
            'status' => $status,
            'source' => ProductDeposit::SOURCE_INVOICE,
        ]);
    }

    private function attributes(string $productId): ?string
    {
        return DB::connection('pos')->table('PRODUCTS')->where('ID', $productId)->value('ATTRIBUTES');
    }

    public function test_ensure_tier_products_creates_then_adopts_and_corrects_the_price(): void
    {
        $tier = $this->service()->ensureTierProducts($this->tier());

        $pos = DB::connection('pos');
        $category = $pos->table('CATEGORIES')->where('NAME', 'Bottle Deposits')->first();
        $this->assertNotNull($category);
        $this->assertNull($category->PARENTID);

        $charge = Product::where('CODE', 'DEP-025')->sole();
        $refund = Product::where('CODE', 'DEP-025-RET')->sole();
        $this->assertSame('Bottle deposit 0.25', $charge->NAME);
        $this->assertSame('DEP-025', $charge->REFERENCE);
        $this->assertSame('000', $charge->TAXCAT);
        $this->assertSame(0.25, round((float) $charge->PRICESELL, 2));
        $this->assertTrue($charge->ISSERVICE);
        $this->assertSame($category->ID, $charge->CATEGORY);
        $this->assertSame('Bottle deposit refund 0.25', $refund->NAME);
        $this->assertSame(-0.25, round((float) $refund->PRICESELL, 2));
        $this->assertTrue($refund->ISSERVICE);
        $this->assertSame($charge->ID, $tier->fresh()->pos_product_id);
        $this->assertSame($refund->ID, $tier->fresh()->pos_refund_product_id);

        // Only the refund product is a catalogue button.
        $this->assertSame([$refund->ID], $pos->table('PRODUCTS_CAT')->pluck('PRODUCT')->all());

        // Second call adopts by CODE: no duplicates; a drifted price is corrected.
        Product::whereKey($charge->ID)->update(['PRICESELL' => 0.3]);
        $this->service()->ensureTierProducts($tier->fresh());

        $this->assertSame(2, Product::where('CODE', 'like', 'DEP-%')->count());
        $this->assertSame(1, $pos->table('CATEGORIES')->count());
        $this->assertSame(1, $pos->table('PRODUCTS_CAT')->count());
        $this->assertSame(0.25, round((float) Product::find($charge->ID)->PRICESELL, 2));

        // A second tier gets the next catalogue position.
        $this->service()->ensureTierProducts($this->tier('315', 0.70));
        $refund70 = Product::where('CODE', 'DEP-070-RET')->sole();
        $this->assertSame(2, (int) $pos->table('PRODUCTS_CAT')->where('PRODUCT', $refund70->ID)->value('CATORDER'));
        $this->assertSame('Bottle deposit refund 0.70', $refund70->NAME);
    }

    public function test_ensure_tier_products_adopts_spike_products_by_code(): void
    {
        $existing = $this->posProduct('DEP-025', 'Bottle deposit 0.25', ['TAXCAT' => '000', 'PRICESELL' => 0.25, 'ISSERVICE' => 1]);

        $tier = $this->service()->ensureTierProducts($this->tier());

        $this->assertSame($existing, $tier->pos_product_id);
        $this->assertSame(1, Product::where('CODE', 'DEP-025')->count());
    }

    public function test_sync_product_writes_the_exact_xml(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice');
        $row = $this->row($juice, $tier);

        $this->assertSame('written', $this->service()->syncProduct($row));

        $depositId = $tier->fresh()->pos_product_id;
        $this->assertSame(PosProductAttributes::serialize([
            'deposit.id' => $depositId,
            'deposit.name' => 'Bottle deposit 0.25',
            'deposit.price' => '0.25',
        ]), $this->attributes($juice));
        $this->assertNotNull($row->fresh()->pos_synced_at);
        $this->assertNull($row->fresh()->pos_sync_error);

        $this->assertSame('unchanged', $this->service()->syncProduct($row->fresh()));
    }

    public function test_sync_product_preserves_a_foreign_key(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice', [
            'ATTRIBUTES' => PosProductAttributes::serialize(['foo' => 'bar'], 'till'),
        ]);
        $row = $this->row($juice, $tier);

        $this->service()->syncProduct($row);
        $this->assertSame('bar', PosProductAttributes::parse($this->attributes($juice))['foo']);
        $this->assertSame('0.25', PosProductAttributes::parse($this->attributes($juice))['deposit.price']);

        $row->update(['status' => ProductDeposit::STATUS_REJECTED]);
        $this->assertSame('cleared', $this->service()->syncProduct($row->fresh()));
        $this->assertSame(['foo' => 'bar'], PosProductAttributes::parse($this->attributes($juice)));
    }

    public function test_sync_product_clears_on_rejected_and_on_a_tier_switched_off(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice');
        $row = $this->row($juice, $tier);
        $this->service()->syncProduct($row);

        $row->update(['status' => ProductDeposit::STATUS_REJECTED]);
        $this->assertSame('cleared', $this->service()->syncProduct($row->fresh()));
        $this->assertNull($this->attributes($juice));
        $this->assertSame('unchanged', $this->service()->syncProduct($row->fresh()));

        $row->update(['status' => ProductDeposit::STATUS_CONFIRMED]);
        $this->service()->syncProduct($row->fresh());
        $tier->update(['charge_customer' => false]);
        $this->assertSame('cleared', $this->service()->syncProduct($row->fresh()));
        $this->assertNull($this->attributes($juice));
    }

    public function test_sync_product_refuses_weighed_and_variable_price_products(): void
    {
        $tier = $this->tier();
        $cheese = $this->posProduct('2000001', 'Cheese', ['ISSCALE' => 1]);
        $loose = $this->posProduct('2000002', 'Loose', ['ISVPRICE' => 1]);

        foreach ([$cheese, $loose] as $id) {
            $row = $this->row($id, $tier);
            $this->assertSame('refused', $this->service()->syncProduct($row));
            $this->assertNull($this->attributes($id));
            $this->assertStringContainsString('weighed or variable-price', $row->fresh()->pos_sync_error);
            $this->assertNull($row->fresh()->pos_synced_at);
        }
    }

    public function test_sync_product_reports_a_product_missing_from_the_till(): void
    {
        $row = $this->row('no-such-id', $this->tier());

        $this->assertSame('missing', $this->service()->syncProduct($row));
        $this->assertSame('product not on till', $row->fresh()->pos_sync_error);
    }

    public function test_sync_all_clears_a_stray_and_dry_run_writes_nothing(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice');
        $this->row($juice, $tier);
        $stray = $this->posProduct('8714728001004', 'Buttermilk', [
            'ATTRIBUTES' => PosProductAttributes::serialize(['deposit.id' => 'x', 'deposit.name' => 'Bottle deposit 0.70', 'deposit.price' => '0.70']),
        ]);

        $dry = $this->service()->syncAll(dryRun: true);
        $this->assertSame(1, $dry['totals']['written']);
        $this->assertSame(1, $dry['totals']['stray_cleared']);
        $this->assertNull($this->attributes($juice));
        $this->assertNotNull($this->attributes($stray));
        $this->assertSame(0, Product::where('CODE', 'like', 'DEP-%')->count());

        $run = $this->service()->syncAll();
        $this->assertSame(1, $run['totals']['written']);
        $this->assertSame(1, $run['totals']['stray_cleared']);
        $this->assertTrue(PosProductAttributes::hasDeposit($this->attributes($juice)));
        $this->assertNull($this->attributes($stray));

        $again = $this->service()->syncAll();
        $this->assertSame(1, $again['totals']['unchanged']);
        $this->assertSame([], $again['changes']);
    }

    public function test_sync_all_gives_every_charged_tier_its_till_products(): void
    {
        $on = $this->tier('9936', 0.10);
        $off = $this->tier('10046', 0.15, charge: false);

        $this->service()->syncAll();

        $this->assertSame(Product::where('CODE', 'DEP-010')->value('ID'), $on->fresh()->pos_product_id);
        $this->assertSame(Product::where('CODE', 'DEP-010-RET')->value('ID'), $on->fresh()->pos_refund_product_id);
        $this->assertNull($off->fresh()->pos_product_id);
        $this->assertSame(0, Product::where('CODE', 'like', 'DEP-015%')->count());
    }

    public function test_check_reports_in_sync_and_a_drifted_price(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice');
        $this->row($juice, $tier);
        $this->service()->syncAll();

        $report = $this->service()->check();
        $this->assertSame(1, $report['in_sync']);
        $this->assertSame(0, $report['drifted']);
        $this->assertSame([], $report['strays']);
        $this->assertTrue($report['tiers'][0]['on_till']);

        // The till's copy no longer matches the tier price.
        DB::connection('pos')->table('PRODUCTS')->where('ID', $juice)->update([
            'ATTRIBUTES' => PosProductAttributes::withDeposit($this->attributes($juice), $tier->fresh()->pos_product_id, 'Bottle deposit 0.20', '0.20'),
        ]);

        $report = $this->service()->check();
        $this->assertSame(1, $report['drifted']);
        $this->assertFalse($report['rows'][0]['ok']);
        $this->assertSame('0.25', $report['rows'][0]['expected_price']);
        $this->assertSame('0.20', $report['rows'][0]['actual_price']);
    }
}
