<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\UdeaProductCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

/**
 * Per-line pallet share on the office order review (/orders/{order}).
 *
 * The placeholders are rendered on Udea orders only; the pallet bar's recalc() fills
 * them in the browser, so the markup is all a feature test can see.
 */
class OrderReviewPalletLineTest extends TestCase
{
    use AliasesMysqlConnection;
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();
        $this->createProductSearchPosTables();

        $pos = DB::connection('pos');
        $pos->table('suppliers')->insert([
            ['SupplierID' => '5', 'Supplier' => 'Udea'],
            ['SupplierID' => 'S1', 'Supplier' => 'Sonett'],
        ]);
        $pos->table('PRODUCTS')->insert([
            ['ID' => 'PA', 'CODE' => '8711521000001', 'NAME' => 'Udea Oat Drink 1 L', 'IMAGE' => null, 'CATEGORY' => null],
            ['ID' => 'PB', 'CODE' => '8711521000002', 'NAME' => 'Udea Rice Cakes 100g', 'IMAGE' => null, 'CATEGORY' => null],
        ]);
        $pos->table('supplier_link')->insert([
            ['Barcode' => '8711521000001', 'SupplierCode' => '5004482', 'SupplierID' => '5', 'CaseUnits' => 6],
            ['Barcode' => '8711521000002', 'SupplierCode' => '5009999', 'SupplierID' => '5', 'CaseUnits' => 1],
        ]);
        $pos->table('stocking')->insert([
            ['Barcode' => '8711521000001'],
            ['Barcode' => '8711521000002'],
        ]);

        // Pallet data for item A only: 10 SVE x 0.65 = 6.5 per order unit.
        UdeaProductCard::create([
            'supplier_code' => '5004482',
            'pallet_sve' => 10,
            'pallet_unit_volume' => 0.65,
            'pallet_scraped_at' => now(),
        ]);

        $this->user = User::factory()->withRole('admin')->create();
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }

    /**
     * @return array{0: OrderSession, 1: OrderItem, 2: OrderItem}
     */
    private function order(string $supplierId): array
    {
        $session = OrderSession::create([
            'user_id' => $this->user->id,
            'supplier_id' => $supplierId,
            'order_date' => '2026-10-21',
            'coverage_days' => 28,
            'sales_history_weeks' => 8,
            'status' => 'draft',
        ]);

        $a = $this->item($session, 'PA', '5004482', caseUnits: 6);
        $b = $this->item($session, 'PB', '5009999', caseUnits: 1);
        $session->updateTotals();

        return [$session, $a, $b];
    }

    private function item(OrderSession $session, string $productId, string $supplierCode, int $caseUnits): OrderItem
    {
        return OrderItem::create([
            'order_session_id' => $session->id,
            'product_id' => $productId,
            'suggested_quantity' => 6,
            'final_quantity' => 6,
            'suggested_cases' => 6 / $caseUnits,
            'final_cases' => 6 / $caseUnits,
            'case_units' => $caseUnits,
            'unit_cost' => 1.00,
            'total_cost' => 6.00,
            'review_priority' => 'standard',
            'context_data' => [
                'supplier_code' => $supplierCode,
                'case_units' => $caseUnits,
                'is_case_product' => $caseUnits > 1,
                'current_stock' => 0,
                'avg_weekly_sales' => 1.0,
                'peak_weekly_sales' => 2,
            ],
        ]);
    }

    public function test_udea_order_renders_a_pallet_line_per_quantity_cell(): void
    {
        [$session, $a, $b] = $this->order('5');

        $response = $this->actingAs($this->user)->get(route('orders.show', $session));

        $response->assertOk();
        $response->assertSee('data-pallet-volume="6.5"', false);
        $response->assertSee('id="pallet-line-pct-'.$a->id.'"', false);
        $response->assertSee('id="pallet-unit-pct-'.$a->id.'"', false);
        $response->assertSeeInOrder(['id="pallet-line-'.$b->id.'"', 'No pallet data'], false);
        $response->assertDontSee('id="pallet-line-pct-'.$b->id.'"', false);
    }

    public function test_non_udea_order_renders_no_pallet_line(): void
    {
        [$session] = $this->order('S1');

        $this->actingAs($this->user)
            ->get(route('orders.show', $session))
            ->assertOk()
            ->assertDontSee('pallet-line-', false);
    }
}
