<?php

namespace Tests\Feature;

use App\Models\KitchenOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CSV is built from the snapshotted order items alone, so no POS tables
 * are needed.
 */
class KitchenOrderCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        $this->actingAs(User::factory()->withRole('admin')->create());
    }

    private function makeOrder(): KitchenOrder
    {
        $order = KitchenOrder::create([
            'supplier_id' => '5',
            'supplier_name' => 'Udea',
            'total_cases' => 5,
            'line_count' => 2,
        ]);

        $order->items()->create([
            'product_id' => 'prod-z',
            'supplier_code' => 'Z-1',
            'product_name' => 'Zucchini, sliced, 500g',
            'case_units' => 6,
            'quantity' => 2,
        ]);
        $order->items()->create([
            'product_id' => 'prod-a',
            'supplier_code' => 'A-1',
            'product_name' => 'Apple Juice 1L',
            'case_units' => 1,
            'quantity' => 3,
        ]);
        // A zero line should never reach the CSV even if it somehow got stored.
        $order->items()->create([
            'product_id' => 'prod-m',
            'supplier_code' => 'M-1',
            'product_name' => 'Millet',
            'case_units' => 1,
            'quantity' => 0,
        ]);

        return $order;
    }

    public function test_the_csv_has_the_exact_header_and_one_row_per_ordered_line(): void
    {
        $order = $this->makeOrder();

        $response = $this->get(route('kitchen.orders.csv', $order));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename="kitchen-order-udea-',
            $response->headers->get('Content-Disposition')
        );

        $lines = explode("\n", trim($response->getContent()));

        $this->assertSame('Quantity,Supplier Code,Product Name,Case Size', $lines[0]);
        $this->assertSame('3,A-1,Apple Juice 1L,1', $lines[1]);
        $this->assertSame('2,Z-1,"Zucchini, sliced, 500g",6', $lines[2]);
        $this->assertCount(3, $lines, 'The zero-quantity line must be absent');
        $this->assertStringNotContainsString('Millet', $response->getContent());
    }

    public function test_a_missing_supplier_code_is_an_empty_field(): void
    {
        $order = KitchenOrder::create(['supplier_id' => '5', 'supplier_name' => 'Udea']);
        $order->items()->create([
            'product_id' => 'prod-b',
            'supplier_code' => null,
            'product_name' => 'Barley Flakes',
            'case_units' => 1,
            'quantity' => 1,
        ]);

        $lines = explode("\n", trim($this->get(route('kitchen.orders.csv', $order))->getContent()));

        $this->assertSame('1,,Barley Flakes,1', $lines[1]);
    }

    public function test_the_filename_slugs_the_supplier_name(): void
    {
        $order = KitchenOrder::create(['supplier_id' => '37', 'supplier_name' => 'Independent Health Foods']);

        $this->assertSame(
            'kitchen-order-independent-health-foods-'.$order->created_at->format('Y-m-d').'-'.$order->id.'.csv',
            $order->csvFilename()
        );
    }
}
