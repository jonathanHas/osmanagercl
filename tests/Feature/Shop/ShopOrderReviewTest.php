<?php

namespace Tests\Feature\Shop;

use App\Models\KitchenProduct;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

/**
 * Shop mode supplier order review (order_clean cycle 1, design screen 20).
 *
 * The employee here holds `orders.review` and nothing else: the screen must work
 * for shop-floor staff without the office's `orders.manage`.
 */
class ShopOrderReviewTest extends TestCase
{
    use AliasesMysqlConnection;
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    /** Stand-in for a till photo; the URL builder only hashes it and tests for null. */
    private const BLOB = "\xFF\xD8\xFFfake-jpeg";

    private User $reviewer;

    private User $bare;

    private OrderSession $draft;

    private OrderSession $completed;

    private OrderItem $p1Item;

    private OrderItem $p2Item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();
        $this->createProductSearchPosTables();

        $pos = DB::connection('pos');
        $pos->table('suppliers')->insert(['SupplierID' => 'S1', 'Supplier' => 'Sonett']);
        $pos->table('PRODUCTS')->insert([
            // P1 has a till photo (stand-in blob); P2 has none and no supplier picture.
            ['ID' => 'P1', 'CODE' => '4007547307025', 'NAME' => 'Sonett Dishwashing Liquid 1 L', 'IMAGE' => self::BLOB, 'CATEGORY' => null],
            ['ID' => 'P2', 'CODE' => '4007547309029', 'NAME' => 'Sonett Dishwasher Tablets 25 pcs', 'IMAGE' => null, 'CATEGORY' => null],
            // A chilled product: POS category 002 is Refrigerated.
            ['ID' => 'P3', 'CODE' => '5390000000003', 'NAME' => 'Glenisk Natural Yogurt 500g', 'IMAGE' => null, 'CATEGORY' => '002'],
        ]);
        $pos->table('supplier_link')->insert([
            ['Barcode' => '4007547307025', 'SupplierCode' => 'SON-1', 'SupplierID' => 'S1', 'CaseUnits' => 6],
            ['Barcode' => '4007547309029', 'SupplierCode' => 'SON-2', 'SupplierID' => 'S1', 'CaseUnits' => 1],
        ]);
        // P1 is stocked; P2 has no stocking row, so it is "Destocked".
        $pos->table('stocking')->insert(['Barcode' => '4007547307025']);

        KitchenProduct::create(['product_id' => 'P2']);

        $this->reviewer = $this->userWith('employee', ['orders.review']);
        $this->bare = $this->userWith('cashier', []);

        $this->draft = $this->orderSession('draft');
        $this->p1Item = $this->item($this->draft, 'P1', caseUnits: 6, suggestedCases: 2, unitCost: 1.50);
        $this->p2Item = $this->item($this->draft, 'P2', caseUnits: 1, suggestedCases: 3, unitCost: 4.00, addedViaSearch: true);
        $this->draft->updateTotals();

        $this->completed = $this->orderSession('completed');
        $this->item($this->completed, 'P1', caseUnits: 6, suggestedCases: 1, unitCost: 1.50);
        $this->completed->updateTotals();
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }

    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Ordering']
            ));
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Jessika Lund']);
    }

    private function orderSession(string $status): OrderSession
    {
        return OrderSession::create([
            'user_id' => $this->reviewer->id,
            'supplier_id' => 'S1',
            'order_date' => '2026-10-13',
            'coverage_days' => 28,
            'coverage_ends_on' => '2026-11-10',
            'sales_history_weeks' => 8,
            'status' => $status,
        ]);
    }

    private function item(OrderSession $session, string $productId, int $caseUnits, float $suggestedCases, float $unitCost, bool $addedViaSearch = false): OrderItem
    {
        $weekly = [1, 1, 0, 1, 3, 1, 1, 0];
        $quantity = $suggestedCases * $caseUnits;

        return OrderItem::create([
            'order_session_id' => $session->id,
            'product_id' => $productId,
            'suggested_quantity' => $quantity,
            'final_quantity' => $quantity,
            'suggested_cases' => $suggestedCases,
            'final_cases' => $suggestedCases,
            'case_units' => $caseUnits,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'review_priority' => 'standard',
            'added_via_search' => $addedViaSearch,
            'context_data' => [
                // Real Mondays, a week apart, as generation stores them.
                'weekly_sales' => array_map(fn ($units, $i) => [
                    'week_start' => Carbon::parse('2026-08-03')->addWeeks($i)->toDateString(),
                    'week_end' => Carbon::parse('2026-08-03')->addWeeks($i)->addDays(6)->toDateString(),
                    'label' => Carbon::parse('2026-08-03')->addWeeks($i)->format('d M'),
                    'units' => $units,
                ], $weekly, array_keys($weekly)),
                'avg_weekly_sales' => 1.0,
                'peak_weekly_sales' => 3,
                'weekly_sales_total' => 8,
                'current_stock' => 2,
                'target_weeks' => 4.0,
                'sales_history_weeks' => 8,
                'is_case_product' => $caseUnits > 1,
            ],
        ]);
    }

    private function itemUrl(OrderSession $session, OrderItem $item): string
    {
        return route('shop.orders.item', [$session, $item]);
    }

    // --- permission ---------------------------------------------------------

    public function test_a_user_without_orders_review_is_forbidden_everywhere(): void
    {
        $this->actingAs($this->bare);

        $this->get(route('shop.orders'))->assertForbidden();
        $this->get(route('shop.orders.review', $this->draft))->assertForbidden();
        $this->getJson(route('shop.orders.items', $this->draft))->assertForbidden();
        $this->get(route('shop.orders.export', $this->draft))->assertForbidden();
        $this->patchJson($this->itemUrl($this->draft, $this->p1Item), ['cases' => 3])->assertForbidden();
    }

    public function test_an_employee_with_orders_review_opens_everything(): void
    {
        $this->actingAs($this->reviewer);

        $this->get(route('shop.orders'))->assertOk();
        $this->get(route('shop.orders.review', $this->draft))->assertOk()
            ->assertSee('Order · Sonett')
            ->assertSee('Delivery Tue 13 Oct · Draft by Jessika')
            ->assertSee('shopOrderReview()', false);
        $this->getJson(route('shop.orders.items', $this->draft))->assertOk();
        $this->get(route('shop.orders.export', $this->draft))->assertOk();

        $this->assertFalse($this->reviewer->hasPermission('orders.manage'));
    }

    // --- list ---------------------------------------------------------------

    public function test_list_shows_drafts_and_recently_completed(): void
    {
        $response = $this->actingAs($this->reviewer)->get(route('shop.orders'));

        $response->assertOk()
            ->assertSeeInOrder(['Draft orders', 'Sonett', 'Delivery Tue 13 Oct · Jessika', '2 products', 'Draft', 'Recently completed', 'Sonett', 'Completed'])
            ->assertSee(route('shop.orders.review', $this->draft), false)
            ->assertSee(route('shop.orders.review', $this->completed), false);
    }

    // --- items JSON ---------------------------------------------------------

    public function test_items_json_carries_the_header_and_one_row_per_item(): void
    {
        $response = $this->actingAs($this->reviewer)->getJson(route('shop.orders.items', $this->draft));

        $response->assertOk()
            ->assertJsonPath('order.supplier', 'Sonett')
            ->assertJsonPath('order.lasts_until', 'Tue 10 Nov')
            ->assertJsonPath('order.history_weeks', 8)
            ->assertJsonPath('order.editable', true)
            ->assertJsonPath('order.item_count', 2)
            ->assertJsonPath('order.ordered_count', 2)
            ->assertJsonCount(2, 'items');

        $this->assertEquals(4.0, $response->json('order.weeks_after_delivery'));

        $rows = collect($response->json('items'))->keyBy('id');

        $p1 = $rows[$this->p1Item->id];
        $this->assertSame('case', $p1['group']);
        $this->assertSame(6, $p1['case_units']);
        $this->assertSame([], $p1['tags']);
        $this->assertSame('standard', $p1['priority']);
        $this->assertCount(8, $p1['weekly_sales']);
        $this->assertEquals([1, 1, 0, 1, 3, 1, 1, 0], $p1['weekly_sales']);
        $this->assertSame(['3 Aug', '10 Aug', '17 Aug', '24 Aug', '31 Aug', '7 Sep', '14 Sep', '21 Sep'], $p1['weekly_labels']);
        // Decimal casts arrive as strings from Eloquent; the row must carry numbers.
        // (JSON drops the ".0", so "a number" is int or float, never a string.)
        $this->assertIsNotString($p1['suggested_cases']);
        $this->assertIsNotString($p1['final_cases']);
        $this->assertIsNumeric($p1['final_cases']);
        $this->assertEquals(2, $p1['final_cases']);
        $this->assertEquals(2, $p1['stock']);

        $p2 = $rows[$this->p2Item->id];
        $this->assertSame('unit', $p2['group']);
        $this->assertSame('added', $p2['priority']);
        $this->assertSame(['Destocked', 'Kitchen'], $p2['tags']);

        // Till photo → the Shop thumbnail route (with a ?v= cache key); nothing → null.
        $this->assertStringStartsWith(route('shop.product-photo', ['code' => '4007547307025']), $p1['image_url']);
        $this->assertNull($p2['image_url']);
        $this->assertNull($p1['category']);
    }

    public function test_items_json_carries_the_display_groups_and_each_rows_category(): void
    {
        $session = $this->orderSession('draft');
        $yogurt = $this->item($session, 'P3', caseUnits: 1, suggestedCases: 4, unitCost: 2.10);

        $response = $this->actingAs($this->reviewer)->getJson(route('shop.orders.items', $session));

        $response->assertOk()
            ->assertJsonPath('order.groups', [
                ['key' => 'cheese', 'title' => 'Cheese', 'codes' => ['032']],
                ['key' => 'refrigerated', 'title' => 'Refrigerated', 'codes' => ['002']],
                ['key' => 'case', 'title' => 'Case products', 'codes' => []],
                ['key' => 'unit', 'title' => 'Single units', 'codes' => []],
            ])
            ->assertJsonPath('items.0.id', $yogurt->id)
            ->assertJsonPath('items.0.category', '002')
            ->assertJsonPath('items.0.group', 'unit');
    }

    public function test_items_json_survives_a_product_gone_from_the_pos_and_old_context_data(): void
    {
        $session = $this->orderSession('draft');
        $item = OrderItem::create([
            'order_session_id' => $session->id,
            'product_id' => 'GONE',
            'suggested_quantity' => 0,
            'final_quantity' => 0,
            'suggested_cases' => 0,
            'final_cases' => 0,
            'case_units' => 1,
            'unit_cost' => 2,
            'total_cost' => 0,
            'review_priority' => 'review',
            'context_data' => ['current_stock' => 0],
        ]);

        $response = $this->actingAs($this->reviewer)->getJson(route('shop.orders.items', $session));

        $response->assertOk()
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.name', 'Unknown product')
            ->assertJsonPath('items.0.code', 'GONE')
            ->assertJsonPath('items.0.weekly_sales', [])
            ->assertJsonPath('items.0.weekly_labels', [])
            ->assertJsonPath('items.0.image_url', null)
            ->assertJsonPath('items.0.tags', []);
    }

    public function test_week_labels_fall_back_to_the_stored_label_and_keep_their_length(): void
    {
        $session = $this->orderSession('draft');
        OrderItem::create([
            'order_session_id' => $session->id,
            'product_id' => 'P1',
            'suggested_quantity' => 0,
            'final_quantity' => 0,
            'suggested_cases' => 0,
            'final_cases' => 0,
            'case_units' => 6,
            'unit_cost' => 1,
            'total_cost' => 0,
            'review_priority' => 'standard',
            'context_data' => [
                'weekly_sales' => [['week_start' => 'not-a-date', 'label' => 'W1', 'units' => 2], 5],
                'current_stock' => 0,
            ],
        ]);

        $this->actingAs($this->reviewer)->getJson(route('shop.orders.items', $session))
            ->assertOk()
            ->assertJsonPath('items.0.weekly_sales', [2, 5])
            ->assertJsonPath('items.0.weekly_labels', ['W1', '']);
    }

    // --- PATCH --------------------------------------------------------------

    public function test_patch_cases_on_a_case_product_goes_through_the_order_service(): void
    {
        $response = $this->actingAs($this->reviewer)
            ->patchJson($this->itemUrl($this->draft, $this->p1Item), ['cases' => 3]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('item.id', $this->p1Item->id);
        // The single-row lookup in update() keeps the row complete.
        $this->assertStringStartsWith(route('shop.product-photo', ['code' => '4007547307025']), $response->json('item.image_url'));
        $this->assertEquals(3, $response->json('item.final_cases'));

        $item = $this->p1Item->fresh();
        $this->assertEquals(3, (float) $item->final_cases);
        $this->assertEquals(18, (float) $item->final_quantity);
        $this->assertEquals(18 * 1.50, (float) $item->total_cost);

        // P1 now 27.00, P2 unchanged at 12.00.
        $this->assertEquals(39.00, (float) $this->draft->fresh()->total_value);
        $this->assertEquals(39.00, $response->json('order.total_value'));

        // Suggested was non-zero, so the learning log has an entry.
        $this->assertDatabaseHas('order_adjustments', [
            'product_id' => 'P1',
            'user_id' => $this->reviewer->id,
        ]);
    }

    public function test_patch_cases_on_a_unit_product_sets_units(): void
    {
        $this->actingAs($this->reviewer)
            ->patchJson($this->itemUrl($this->draft, $this->p2Item), ['cases' => 2])
            ->assertOk();

        $item = $this->p2Item->fresh();
        $this->assertEquals(2, (float) $item->final_quantity);
        $this->assertEquals(2, (float) $item->final_cases);
        $this->assertEquals(8.00, (float) $item->total_cost);
    }

    public function test_patch_validates_cases(): void
    {
        $this->actingAs($this->reviewer);
        $url = $this->itemUrl($this->draft, $this->p1Item);

        $this->patchJson($url, ['cases' => -1])->assertStatus(422);
        $this->patchJson($url, ['cases' => 'x'])->assertStatus(422);
        $this->patchJson($url, ['cases' => 10000])->assertStatus(422);
        $this->patchJson($url, [])->assertStatus(422);

        $this->assertEquals(2, (float) $this->p1Item->fresh()->final_cases);
    }

    public function test_patch_on_a_completed_order_is_refused_with_409(): void
    {
        $item = $this->completed->items()->first();

        $this->actingAs($this->reviewer)
            ->patchJson($this->itemUrl($this->completed, $item), ['cases' => 5])
            ->assertStatus(409)
            ->assertExactJson(['error' => 'Order is not editable']);

        $this->assertEquals(1, (float) $item->fresh()->final_cases);
        $this->assertSame(0, OrderAdjustment::count());
    }

    public function test_patch_with_an_item_from_another_order_is_404(): void
    {
        $other = $this->completed->items()->first();

        $this->actingAs($this->reviewer)
            ->patchJson($this->itemUrl($this->draft, $other), ['cases' => 5])
            ->assertNotFound();

        $this->assertEquals(1, (float) $other->fresh()->final_cases);
    }

    public function test_completed_order_header_is_not_editable(): void
    {
        $this->actingAs($this->reviewer)
            ->getJson(route('shop.orders.items', $this->completed))
            ->assertOk()
            ->assertJsonPath('order.editable', false)
            ->assertJsonPath('order.status', 'Completed');
    }

    // --- export -------------------------------------------------------------

    public function test_export_returns_the_office_csv(): void
    {
        $response = $this->actingAs($this->reviewer)->get(route('shop.orders.export', $this->draft));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertSame(
            'attachment; filename="order_Sonett_2026-10-13.csv"',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringStartsWith('Code,Cases,Units,', $response->getContent());
        $this->assertStringContainsString('SON-1', $response->getContent());
    }

    public function test_export_skips_an_ordered_product_gone_from_the_pos(): void
    {
        $session = $this->orderSession('draft');
        $this->item($session, 'P1', caseUnits: 6, suggestedCases: 1, unitCost: 1.50);
        OrderItem::create([
            'order_session_id' => $session->id,
            'product_id' => 'GONE',
            'suggested_quantity' => 2,
            'final_quantity' => 2,
            'suggested_cases' => 2,
            'final_cases' => 2,
            'case_units' => 1,
            'unit_cost' => 3,
            'total_cost' => 6,
            'review_priority' => 'standard',
            'context_data' => ['current_stock' => 0],
        ]);

        $response = $this->actingAs($this->reviewer)->get(route('shop.orders.export', $session));

        $response->assertOk();
        $this->assertStringContainsString('SON-1', $response->getContent());
        $this->assertStringNotContainsString('GONE', $response->getContent());
    }
}
