<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\OrderSession;
use App\Models\Product;
use App\Models\SalesDailySummary;
use App\Models\User;
use App\Repositories\SalesRepository;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

/**
 * A late delivery ticked "Include in Orders" is counted as incoming stock by the
 * next order for its supplier, then unticked so it does not carry over.
 */
class OrderIncomingDeliveriesTest extends TestCase
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

        // The bulk sales queries use MySQL date SQL the SQLite test database cannot run.
        $this->app->instance(SalesRepository::class, new class extends SalesRepository
        {
            public function __construct() {}

            public function getBulkProductSalesStatistics(array $productIds): Collection
            {
                return collect(['PA' => ['avg_monthly_sales' => 43.3, 'last_month_sales' => 43, 'trend' => 'stable']]);
            }

            public function getBulkProductWeeklySales(array $productIds, int $weeksBack = 8): Collection
            {
                return collect(['PA' => array_fill(0, $weeksBack, ['units' => 10])]);
            }

            public function getBulkInternalCustomerWeeklySales(array $productIds, int $weeksBack = 8): array
            {
                return ['coffee' => collect(), 'kitchen' => collect()];
            }

            public function getBulkRecentPurchasePrices(array $productIds): Collection
            {
                return collect();
            }

            public function getBulkProductSalesHistory(array $productIds, int $monthsBack = 6): Collection
            {
                return collect();
            }

            public function getBulkLastSaleDates(array $productIds): Collection
            {
                return collect();
            }
        });

        $pos = DB::connection('pos');
        $pos->table('suppliers')->insert([
            ['SupplierID' => '5', 'Supplier' => 'Udea'],
            ['SupplierID' => '37', 'Supplier' => 'Other'],
        ]);
        $pos->table('PRODUCTS')->insert([
            ['ID' => 'PA', 'CODE' => '8711521000001', 'NAME' => 'Udea Oat Drink 1 L', 'IMAGE' => null, 'CATEGORY' => null],
        ]);
        $pos->table('supplier_link')->insert([
            ['Barcode' => '8711521000001', 'SupplierCode' => '5004482', 'SupplierID' => '5', 'CaseUnits' => 1],
        ]);
        $pos->table('stocking')->insert([
            ['Barcode' => '8711521000001'],
        ]);

        // Order generation only considers products sold in the last six months.
        SalesDailySummary::create([
            'product_id' => 'PA',
            'product_code' => '8711521000001',
            'product_name' => 'Udea Oat Drink 1 L',
            'category_id' => '000',
            'sale_date' => now()->subWeek()->toDateString(),
            'total_units' => 10,
            'total_revenue' => 25,
            'transaction_count' => 10,
            'avg_price' => 2.5,
        ]);

        $this->user = User::factory()->withRole('admin')->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }

    private function delivery(int $supplierId = 5, string $status = 'draft', bool $included = true, int $units = 12): Delivery
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-LATE-'.$supplierId.'-'.$status,
            'supplier_id' => $supplierId,
            'delivery_date' => '2026-10-27',
            'status' => $status,
            'include_in_order_stock' => $included,
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'supplier_code' => '5004482',
            'description' => 'Udea Oat Drink 1 L',
            'units_per_case' => 1,
            'unit_cost' => 1.00,
            'ordered_quantity' => $units,
            'unit_ordered_quantity' => $units,
            'total_cost' => $units,
            'status' => 'pending',
            'product_id' => 'PA',
        ]);

        return $delivery;
    }

    private function generate(): OrderSession
    {
        return $this->orderService()->generateOrderSuggestions('5', Carbon::parse('2026-11-04'));
    }

    private function regenerate(OrderSession $order): OrderSession
    {
        return $this->orderService()->regenerateOrderSession($order->fresh());
    }

    /**
     * The suggestion maths reads STOCKDIARY with MySQL SQL, so stop at the point the
     * incoming quantity is handed to it and record what it was given.
     */
    private function orderService(): OrderService
    {
        $this->pendingSeen = null;
        $test = $this;

        return new class(app(SalesRepository::class), $test) extends OrderService
        {
            public function __construct(SalesRepository $salesRepository, private $test)
            {
                parent::__construct($salesRepository);
            }

            public function calculateProductSuggestion(Product $product, array $options = [], array $prefetchedData = []): array
            {
                $this->test->pendingSeen = (float) ($prefetchedData['pending_delivery_qty'] ?? 0);

                throw new \RuntimeException('Suggestion maths not needed here');
            }
        };
    }

    public ?float $pendingSeen = null;

    public function test_a_ticked_delivery_is_counted_once_then_unticked(): void
    {
        $delivery = $this->delivery();

        $order = $this->generate();

        $this->assertSame(12.0, $this->pendingSeen);
        $this->assertSame([[
            'id' => $delivery->id,
            'delivery_number' => 'DEL-LATE-5-draft',
            'delivery_date' => '2026-10-27',
            'units' => 12,
        ]], $order->fresh()->incoming_deliveries);

        $delivery->refresh();
        $this->assertFalse($delivery->include_in_order_stock);
        $this->assertSame($order->id, $delivery->counted_in_order_session_id);
        $this->assertNotNull($delivery->counted_in_order_at);

        // The next order does not count it again.
        $next = $this->generate();
        $this->assertSame(0.0, $this->pendingSeen);
        $this->assertSame([], $next->fresh()->incoming_deliveries);
        $this->assertSame($order->id, $delivery->fresh()->counted_in_order_session_id);
    }

    public function test_regenerating_the_order_still_counts_the_delivery(): void
    {
        $delivery = $this->delivery();
        $order = $this->generate();

        app(OrderService::class)->regenerateOrderSession($order->fresh());

        $this->assertSame(12.0, $this->pendingSeen);
        $this->assertCount(1, $order->fresh()->incoming_deliveries);
        $this->assertSame($order->id, $delivery->fresh()->counted_in_order_session_id);
    }

    public function test_other_suppliers_and_cancelled_deliveries_are_left_alone(): void
    {
        $other = $this->delivery(supplierId: 37);
        $cancelled = $this->delivery(status: 'cancelled');
        $unticked = $this->delivery(status: 'receiving', included: false);

        $order = $this->generate();

        $this->assertSame(0.0, $this->pendingSeen);
        $this->assertSame([], $order->fresh()->incoming_deliveries);

        foreach ([$other, $cancelled] as $delivery) {
            $this->assertTrue($delivery->fresh()->include_in_order_stock);
            $this->assertNull($delivery->fresh()->counted_in_order_session_id);
        }
        $this->assertNull($unticked->fresh()->counted_in_order_session_id);
    }

    public function test_pages_show_the_counted_delivery(): void
    {
        $delivery = $this->delivery();

        $this->get(route('orders.create'))
            ->assertOk()
            ->assertSee('1 delivery will be counted as incoming stock')
            ->assertSee('DEL-LATE-5-draft');

        $order = $this->generate();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Incoming stock counted in this order')
            ->assertSee('DEL-LATE-5-draft');

        $this->get(route('orders.create'))
            ->assertOk()
            ->assertDontSee('DEL-LATE-5-draft');
    }
}
