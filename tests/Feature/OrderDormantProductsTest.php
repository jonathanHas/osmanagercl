<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\SalesDailySummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

/**
 * "Not sold in 6 months" on the office order review (/orders/{order}).
 *
 * Generation only takes stocked supplier products with a sale in the last six
 * months; this list is the remainder that is not on the order yet.
 */
class OrderDormantProductsTest extends TestCase
{
    use AliasesMysqlConnection;
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    private User $user;

    private OrderSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();
        $this->createProductSearchPosTables();

        $pos = DB::connection('pos');
        $pos->table('suppliers')->insert([
            ['SupplierID' => 'S1', 'Supplier' => 'Sonett'],
            ['SupplierID' => 'S2', 'Supplier' => 'Other'],
        ]);
        $pos->table('PRODUCTS')->insert([
            ['ID' => 'RECENT', 'CODE' => '1001', 'NAME' => 'Sold last month', 'IMAGE' => null, 'CATEGORY' => null],
            ['ID' => 'OLD', 'CODE' => '1002', 'NAME' => 'B sold a year ago', 'IMAGE' => null, 'CATEGORY' => null],
            ['ID' => 'NEVER', 'CODE' => '1003', 'NAME' => 'A never sold', 'IMAGE' => null, 'CATEGORY' => null],
            ['ID' => 'ONORDER', 'CODE' => '1004', 'NAME' => 'Unsold but already on the order', 'IMAGE' => null, 'CATEGORY' => null],
            ['ID' => 'UNSTOCKED', 'CODE' => '1005', 'NAME' => 'Unsold and not stocked', 'IMAGE' => null, 'CATEGORY' => null],
            ['ID' => 'OTHERSUP', 'CODE' => '1006', 'NAME' => 'Unsold, other supplier', 'IMAGE' => null, 'CATEGORY' => null],
        ]);
        $pos->table('supplier_link')->insert([
            ['Barcode' => '1001', 'SupplierCode' => 'A1', 'SupplierID' => 'S1', 'CaseUnits' => 1, 'Cost' => 1.00],
            ['Barcode' => '1002', 'SupplierCode' => 'A2', 'SupplierID' => 'S1', 'CaseUnits' => 6, 'Cost' => 2.50],
            ['Barcode' => '1003', 'SupplierCode' => 'A3', 'SupplierID' => 'S1', 'CaseUnits' => 1, 'Cost' => 3.00],
            ['Barcode' => '1004', 'SupplierCode' => 'A4', 'SupplierID' => 'S1', 'CaseUnits' => 1, 'Cost' => 4.00],
            ['Barcode' => '1005', 'SupplierCode' => 'A5', 'SupplierID' => 'S1', 'CaseUnits' => 1, 'Cost' => 5.00],
            ['Barcode' => '1006', 'SupplierCode' => 'B6', 'SupplierID' => 'S2', 'CaseUnits' => 1, 'Cost' => 6.00],
        ]);
        $pos->table('stocking')->insert([
            ['Barcode' => '1001'], ['Barcode' => '1002'], ['Barcode' => '1003'], ['Barcode' => '1004'], ['Barcode' => '1006'],
        ]);
        $pos->table('STOCKCURRENT')->insert([
            ['LOCATION' => '0', 'PRODUCT' => 'OLD', 'UNITS' => 4],
        ]);

        $this->sale('RECENT', now()->subMonth()->toDateString());
        $this->sale('OLD', now()->subYear()->toDateString());

        $this->user = User::factory()->withRole('admin')->create();

        $this->session = OrderSession::create([
            'user_id' => $this->user->id,
            'supplier_id' => 'S1',
            'order_date' => now()->toDateString(),
            'coverage_days' => 28,
            'sales_history_weeks' => 8,
            'status' => 'draft',
        ]);

        OrderItem::create([
            'order_session_id' => $this->session->id,
            'product_id' => 'ONORDER',
            'suggested_quantity' => 0,
            'final_quantity' => 2,
            'suggested_cases' => 0,
            'final_cases' => 2,
            'case_units' => 1,
            'unit_cost' => 4.00,
            'total_cost' => 8.00,
            'review_priority' => 'standard',
            'added_via_search' => true,
            'context_data' => ['supplier_code' => 'A4', 'case_units' => 1, 'current_stock' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }

    private function sale(string $productId, string $date): void
    {
        SalesDailySummary::create([
            'product_id' => $productId,
            'product_code' => $productId,
            'product_name' => $productId,
            'category_id' => 'X',
            'sale_date' => $date,
            'total_units' => 1,
            'total_revenue' => 1,
            'transaction_count' => 1,
            'avg_price' => 1,
        ]);
    }

    public function test_lists_stocked_supplier_products_with_no_sale_in_six_months(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('orders.dormant-products', $this->session))
            ->assertOk();

        // Sorted by name; recent sellers, items on the order, unstocked and other suppliers' products are left out.
        $this->assertSame(['NEVER', 'OLD'], array_column($response->json('results'), 'product_id'));

        $response->assertJsonPath('results.0.last_sold', null);
        $response->assertJsonPath('results.1.last_sold', now()->subYear()->toDateString());
        $response->assertJsonPath('results.1.case_units', 6);
        $response->assertJsonPath('results.1.is_case_product', true);
        $response->assertJsonPath('results.1.supplier_code', 'A2');
        $this->assertEquals(2.5, $response->json('results.1.unit_cost'));
        $this->assertEquals(4, $response->json('results.1.current_stock'));
    }

    public function test_review_page_shows_the_button(): void
    {
        $this->actingAs($this->user)
            ->get(route('orders.show', $this->session))
            ->assertOk()
            ->assertSee('Not sold in 6 months')
            ->assertSee(route('orders.dormant-products', $this->session), false);
    }

    public function test_guests_cannot_read_the_list(): void
    {
        $this->getJson(route('orders.dormant-products', $this->session))->assertUnauthorized();
    }
}
