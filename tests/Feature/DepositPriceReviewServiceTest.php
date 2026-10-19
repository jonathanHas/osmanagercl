<?php

namespace Tests\Feature;

use App\Models\BarrelCode;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Models\User;
use App\Services\Deposits\DepositPriceReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * Price facts and the lower-by-deposit write on /deposits (deposit cycle 4).
 * Price history comes from the till's full-price ticket lines (revision 2).
 */
class DepositPriceReviewServiceTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    private DepositPriceReviewService $service;

    private int $deliveries = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
        $this->posTaxRate('002', 0.23);
        $this->posTaxRate('000', 0.0);
        $this->service = app(DepositPriceReviewService::class);
    }

    private function tier(float $price = 0.25): BarrelCode
    {
        return BarrelCode::create([
            'supplier_code' => '313',
            'supplier_id' => 5,
            'description' => 'Statiegeld 313',
            'unit_price' => $price,
            'is_active' => true,
            'charge_customer' => true,
        ]);
    }

    private function row(string $productId, BarrelCode $tier, string $code = '8711521947614'): ProductDeposit
    {
        return ProductDeposit::create([
            'product_id' => $productId,
            'product_code' => $code,
            'barrel_code_id' => $tier->id,
            'status' => ProductDeposit::STATUS_SUGGESTED,
            'source' => ProductDeposit::SOURCE_INVOICE,
        ]);
    }

    private function udeaLine(string $productId, string $date, ?float $cost, ?float $salePrice = null, int $supplierId = 5): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-'.(++$this->deliveries),
            'supplier_id' => $supplierId,
            'delivery_date' => $date,
            'status' => 'draft',
            'import_data' => [],
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'supplier_code' => '92505',
            'description' => 'Lime juice 200ml DE 313',
            'unit_cost' => $cost,
            'sale_price' => $salePrice,
            'product_id' => $productId,
            'ordered_quantity' => 6,
            'total_cost' => 6 * (float) $cost,
        ]);
    }

    /** One full-price till sale per entry, consecutive days from $from. */
    private function sales(string $productId, string $from, array $prices): void
    {
        $day = Carbon::parse($from);
        foreach ($prices as $price) {
            $this->posSale($productId, $day->toDateString(), $price, 2);
            $day->addDay();
        }
    }

    private function facts(string $productId, BarrelCode $tier, float $deliveryPct = 0): array
    {
        $rows = collect([$this->row($productId, $tier)]);
        $products = Product::whereIn('ID', [$productId])->with('tax')
            ->get(['ID', 'NAME', 'CODE', 'PRICESELL', 'PRICEBUY', 'TAXCAT'])->keyBy('ID');

        return $this->service->forRows($rows, $products, $deliveryPct)[$productId];
    }

    private function priceSell(string $productId): float
    {
        return (float) DB::connection('pos')->table('PRODUCTS')->where('ID', $productId)->value('PRICESELL');
    }

    public function test_latest_docket_cost_ignoring_zero_cost_lines(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70, 'PRICEBUY' => 1.31]);
        $this->udeaLine($id, '2026-08-01', 1.31);
        $this->udeaLine($id, '2026-10-05', 1.36);
        $this->udeaLine($id, '2026-10-10', 0.0);
        $this->udeaLine($id, '2026-10-12', 9.99, supplierId: 99); // not Udea

        $facts = $this->facts($id, $this->tier());

        $this->assertSame(1.36, $facts['cost']);
        $this->assertSame('2026-10-05', $facts['cost_on']);
        $this->assertSame('docket', $facts['cost_source']);
        $this->assertSame(2.70, $facts['gross']);
        $this->assertEqualsWithDelta(98.53, $facts['markup_pct'], 0.01);
    }

    public function test_cost_falls_back_to_pricebuy(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70, 'PRICEBUY' => 1.31]);
        $this->udeaLine($id, '2026-10-05', 0.0);

        $facts = $this->facts($id, $this->tier());

        $this->assertSame(1.31, $facts['cost']);
        $this->assertNull($facts['cost_on']);
        $this->assertSame('till', $facts['cost_source']);
    }

    public function test_markup_is_null_without_a_cost(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70, 'PRICEBUY' => 0]);

        $facts = $this->facts($id, $this->tier());

        $this->assertNull($facts['cost']);
        $this->assertNull($facts['markup_pct']);
    }

    public function test_udea_advice_is_the_latest_positive_sale_price(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->udeaLine($id, '2026-05-01', 1.31, 2.49);
        $this->udeaLine($id, '2026-08-01', 1.36, 2.59);
        $this->udeaLine($id, '2026-09-01', 1.36, 0.0);
        $this->udeaLine($id, '2026-10-01', 1.36, null);

        $this->assertSame(2.59, $this->facts($id, $this->tier())['udea_rrp']);
    }

    public function test_a_price_that_holds_is_a_step_with_the_cost_before_it(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->sales($id, '2025-12-20', [1.99, 1.99, 1.99, 1.99, 1.99, 1.99, 1.99, 2.70, 2.70, 2.70, 2.70]);
        $this->udeaLine($id, '2025-11-15', 1.31);
        $this->udeaLine($id, '2026-10-05', 1.36);

        $facts = $this->facts($id, $this->tier());

        $this->assertSame(['on' => '2025-12-27', 'from' => 1.99, 'to' => 2.7], $facts['step']);
        $this->assertSame(1.31, $facts['cost_before_step']);
        $this->assertSame(1.36, $facts['cost']);
    }

    public function test_a_one_day_dip_is_not_a_step(): void
    {
        $id = $this->posProduct('8711521947614', 'Yogurt', ['TAXCAT' => '000', 'PRICESELL' => 2.89]);
        $this->sales($id, '2026-09-01', [2.89, 2.89, 2.82, 2.89, 2.89, 2.89, 2.89]);

        $this->assertNull($this->facts($id, $this->tier())['step']);
    }

    public function test_a_change_in_the_last_days_does_not_show_yet(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->sales($id, '2026-09-01', [1.99, 1.99, 1.99, 1.99, 1.99, 2.70, 2.70]);

        $this->assertNull($this->facts($id, $this->tier())['step']);
    }

    public function test_discounted_lines_are_ignored(): void
    {
        $id = $this->posProduct('8711521947614', 'Carbonated lemon', ['TAXCAT' => '000', 'PRICESELL' => 1.4228]);
        $day = Carbon::parse('2026-03-01');
        for ($i = 0; $i < 22; $i++) {
            $full = $i === 5 || $i === 15;
            $this->posSale($id, $day->toDateString(), $full ? 1.4228 : 1.3517, discounted: ! $full);
            $day->addDay();
        }

        $facts = $this->facts($id, $this->tier());

        $this->assertNull($facts['step']);
        $this->assertSame(['on' => '2026-03-16', 'net' => 1.42], $facts['last_sold']);
        $this->assertFalse($facts['changed_since_sale']);
    }

    public function test_a_day_with_only_discounted_lines_is_skipped(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 1.99]);
        $this->sales($id, '2026-09-01', [1.99, 1.99, 1.99]);
        $this->posSale($id, '2026-09-04', 1.89, 3, discounted: true);
        $this->posSale($id, '2026-09-05', 1.89, 3, discounted: true);
        $this->posSale($id, '2026-09-05', 1.89, 1, discounted: true);

        $facts = $this->facts($id, $this->tier());

        $this->assertSame(['on' => '2026-09-03', 'net' => 1.99], $facts['last_sold']);
        $this->assertNull($facts['step']);
    }

    public function test_the_full_price_of_a_day_is_the_most_common_full_price_line(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->posSale($id, '2026-09-01', 2.70);
        $this->posSale($id, '2026-09-01', 2.70);
        $this->posSale($id, '2026-09-01', 1.99);
        $this->posSale($id, '2026-09-01', 2.57, discounted: true);
        $this->posSale($id, '2026-09-01', 2.57, discounted: true);
        $this->posSale($id, '2026-09-01', 2.57, discounted: true);
        $this->posSale($id, '2026-09-02', 1.99);
        $this->posSale($id, '2026-09-02', 2.70);

        $this->assertSame(['on' => '2026-09-02', 'net' => 2.7], $this->facts($id, $this->tier())['last_sold']);
    }

    public function test_changed_since_the_last_sale(): void
    {
        $milk = $this->posProduct('8714728001004', 'Buttermilk', ['TAXCAT' => '000', 'PRICESELL' => 2.50]);
        $this->sales($milk, '2026-07-05', [2.80, 2.80, 2.80, 2.80, 2.80, 2.80, 2.80]);

        $facts = $this->facts($milk, $this->tier());
        $this->assertTrue($facts['changed_since_sale']);
        $this->assertSame(['on' => '2026-07-11', 'net' => 2.8], $facts['last_sold']);

        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->sales($juice, '2026-07-05', [2.70, 2.70, 2.70]);
        $rows = collect([$this->row($juice, BarrelCode::first(), '8711521947614')]);
        $products = Product::whereIn('ID', [$juice])->with('tax')
            ->get(['ID', 'NAME', 'CODE', 'PRICESELL', 'PRICEBUY', 'TAXCAT'])->keyBy('ID');

        $this->assertFalse($this->service->forRows($rows, $products, 0)[$juice]['changed_since_sale']);
    }

    public function test_no_sales_means_no_history(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);

        $facts = $this->facts($id, $this->tier());

        $this->assertNull($facts['step']);
        $this->assertNull($facts['last_sold']);
        $this->assertFalse($facts['changed_since_sale']);
    }

    public function test_lower_by_deposit_writes_the_till_like_the_product_page(): void
    {
        $user = User::factory()->create();
        $id = $this->posProduct('8711521947614', 'Pineapple juice', ['TAXCAT' => '002', 'PRICESELL' => 2.0325]);
        $row = $this->row($id, $this->tier(0.25));

        $result = $this->service->lowerByDeposit($row, $user);

        $this->assertTrue($result['ok']);
        $this->assertSame('Pineapple juice: €2.50 → €2.25 on the till (net 2.0325 → 1.8293); label queued.', $result['message']);
        $this->assertEqualsWithDelta(2.25 / 1.23, $this->priceSell($id), 0.00005);
        $this->assertDatabaseHas('label_logs', ['barcode' => '8711521947614', 'event_type' => 'price_update']);

        $row->refresh();
        $this->assertSame(ProductDeposit::PRICE_LOWERED, $row->price_review);
        $this->assertSame('2.5000', $row->price_review_from);
        $this->assertSame($user->id, $row->price_reviewed_by);
        $this->assertNotNull($row->price_reviewed_at);
    }

    public function test_lower_by_deposit_on_a_zero_rated_product(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->row($id, $this->tier(0.25));

        $this->assertTrue($this->service->lowerByDeposit($row, User::factory()->create())['ok']);
        $this->assertEqualsWithDelta(2.45, $this->priceSell($id), 0.00005);
    }

    public function test_lower_is_refused_when_the_deposit_eats_the_price(): void
    {
        $id = $this->posProduct('8711521947614', 'Cheap water', ['TAXCAT' => '000', 'PRICESELL' => 0.25]);
        $row = $this->row($id, $this->tier(0.25));

        $result = $this->service->lowerByDeposit($row, User::factory()->create());

        $this->assertFalse($result['ok']);
        $this->assertSame(0.25, $this->priceSell($id));
        $this->assertNull($row->fresh()->price_review);
        $this->assertDatabaseCount('label_logs', 0);
    }

    public function test_lower_is_refused_when_the_product_is_missing(): void
    {
        $row = $this->row('no-such-product', $this->tier(0.25));

        $result = $this->service->lowerByDeposit($row, User::factory()->create());

        $this->assertFalse($result['ok']);
        $this->assertNull($row->fresh()->price_review);
        $this->assertDatabaseCount('label_logs', 0);
    }

    public function test_markup_is_against_the_landed_cost(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->udeaLine($id, '2026-10-05', 1.36);

        $facts = $this->facts($id, $this->tier(), 15);

        $this->assertEqualsWithDelta(1.564, $facts['landed'], 0.00001);
        $this->assertEqualsWithDelta(72.63, $facts['markup_pct'], 0.01);
        $this->assertEqualsWithDelta(1.136, $facts['profit'], 0.00001);
        $this->assertSame(1.0, $facts['vat_ratio']);
    }

    public function test_vat_ratio_is_gross_over_net(): void
    {
        $id = $this->posProduct('8711521947614', 'Pineapple juice', ['TAXCAT' => '002', 'PRICESELL' => 2.0325]);

        $facts = $this->facts($id, $this->tier());

        $this->assertNull($facts['landed']);
        $this->assertNull($facts['profit']);
        $this->assertEqualsWithDelta(2.50 / 2.0325, $facts['vat_ratio'], 0.00001);
    }

    public function test_sold_in_twelve_months_counts_discounted_lines_and_skips_older_sales(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->posSale($id, now()->subDays(400)->toDateString(), 2.70, 5);
        $this->posSale($id, now()->subDays(100)->toDateString(), 2.70, 3);
        $this->posSale($id, now()->subDays(20)->toDateString(), 2.57, 2, discounted: true);
        $this->posSale($id, now()->subDays(20)->toDateString(), 2.70, 1);

        $facts = $this->facts($id, $this->tier());

        $this->assertSame(6.0, $facts['sold_12m']);
        $this->assertSame(now()->subDays(20)->toDateString(), $facts['last_sale_on']);
    }

    public function test_the_last_sale_may_be_a_discounted_one(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $this->posSale($id, '2026-09-01', 2.70);
        $this->posSale($id, '2026-09-03', 2.57, discounted: true);

        $facts = $this->facts($id, $this->tier());

        $this->assertSame('2026-09-03', $facts['last_sale_on']);
        $this->assertSame('2026-09-01', $facts['last_sold']['on']);
    }

    public function test_no_sales_means_no_sales_facts(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);

        $facts = $this->facts($id, $this->tier());

        $this->assertNull($facts['sold_12m']);
        $this->assertNull($facts['last_sale_on']);
    }

    public function test_set_gross_price_writes_the_typed_price(): void
    {
        $user = User::factory()->create();
        $id = $this->posProduct('8711521947614', 'Pineapple juice', ['TAXCAT' => '002', 'PRICESELL' => 2.0325]);
        $row = $this->row($id, $this->tier(0.25));

        $result = $this->service->setGrossPrice($row, $user, 2.49);

        $this->assertTrue($result['ok']);
        $this->assertSame('Pineapple juice: €2.50 → €2.49 on the till (net 2.0325 → 2.0244); label queued.', $result['message']);
        $this->assertEqualsWithDelta(2.49 / 1.23, $this->priceSell($id), 0.00005);
        $this->assertDatabaseHas('label_logs', ['barcode' => '8711521947614', 'event_type' => 'price_update']);
        $row->refresh();
        $this->assertSame(ProductDeposit::PRICE_SET, $row->price_review);
        $this->assertSame('2.5000', $row->price_review_from);
        $this->assertSame($user->id, $row->price_reviewed_by);
    }

    public function test_set_gross_price_refuses_zero_and_a_missing_product(): void
    {
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $tier = $this->tier();
        $row = $this->row($id, $tier);

        $this->assertFalse($this->service->setGrossPrice($row, User::factory()->create(), 0)['ok']);
        $this->assertSame(2.70, $this->priceSell($id));

        $missing = $this->row('no-such-product', $tier, '123');
        $this->assertFalse($this->service->setGrossPrice($missing, User::factory()->create(), 2.49)['ok']);
        $this->assertDatabaseCount('label_logs', 0);
    }

    public function test_undo_restores_the_price_and_clears_the_review(): void
    {
        $user = User::factory()->create();
        $id = $this->posProduct('8711521947614', 'Pineapple juice', ['TAXCAT' => '002', 'PRICESELL' => 2.0325]);
        $row = $this->row($id, $this->tier(0.25));
        $row->decide(ProductDeposit::STATUS_CONFIRMED, $user)->save();
        $this->service->lowerByDeposit($row, $user);

        $result = $this->service->undo($row->fresh('barrelCode'));

        $this->assertSame('8711521947614: back to suggested; price restored to €2.50.', $result['message']);
        $this->assertEqualsWithDelta(2.50 / 1.23, $this->priceSell($id), 0.00005);
        $row->refresh();
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $row->status);
        $this->assertNull($row->confirmed_by);
        $this->assertNull($row->price_review);
        $this->assertNull($row->price_review_from);
        $this->assertDatabaseCount('label_logs', 2);
    }

    public function test_undo_of_a_kept_row_leaves_the_price(): void
    {
        $user = User::factory()->create();
        $id = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->row($id, $this->tier());
        $row->decide(ProductDeposit::STATUS_CONFIRMED, $user)->reviewPrice(ProductDeposit::PRICE_KEPT, $user)->save();

        $result = $this->service->undo($row->fresh('barrelCode'));

        $this->assertSame('8711521947614: back to suggested; price unchanged.', $result['message']);
        $this->assertSame(2.70, $this->priceSell($id));
        $this->assertNull($row->fresh()->price_review);
        $this->assertDatabaseCount('label_logs', 0);
    }
}
