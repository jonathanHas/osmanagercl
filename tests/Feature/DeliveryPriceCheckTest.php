<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryParsingService;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * The parser's price check (Qty × Price × SKU = line total) and its warnings
 * are kept on the delivery and shown on the delivery page (deliveries side
 * plan price-check).
 */
class DeliveryPriceCheckTest extends TestCase
{
    use AliasesMysqlConnection, CreatesDepositPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
        // KitchenProduct (used by the delivery page) pins the 'mysql' connection.
        $this->aliasMysqlConnectionToTestDatabase();
    }

    private function delivery(array $importData = [], string $number = 'DEL-TEST-1'): Delivery
    {
        return Delivery::create([
            'delivery_number' => $number,
            'supplier_id' => 5,
            'delivery_date' => '2026-07-15',
            'status' => 'draft',
            'import_data' => $importData,
        ]);
    }

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'deliveries.manage'],
            ['display_name' => 'deliveries.manage', 'module' => 'Deliveries']
        ));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function parsedItem(string $code, array $extra = []): array
    {
        return array_merge([
            'code' => $code,
            'product' => "Product {$code}",
            'case_size' => 6,
            'total_ordered_units' => 6,
            'total_delivered_units' => 6,
            'unit_cost' => 2.00,
            'rsp' => 3.59,
            'line_total' => 12.00,
        ], $extra);
    }

    public function test_price_check_result_is_stored_on_the_item(): void
    {
        Queue::fake();

        $parsed = [
            'success' => true,
            'data' => [
                'items' => [
                    $this->parsedItem('5007341', ['price_valid' => false, 'price_note' => '1×3.99×6=23.94, line total 12.00']),
                    $this->parsedItem('5004102', ['price_valid' => true, 'price_note' => 'case size read as 401; 1×0.49×40=19.60 matches the line total, using 40']),
                    $this->parsedItem('12047'),
                    $this->parsedItem('12048'),
                ],
            ],
        ];

        $items = app(DeliveryParsingService::class)->convertToDeliveryItems($parsed);
        $this->assertFalse($items[0]['price_valid']);
        $this->assertTrue($items[2]['price_valid']);
        $this->assertNull($items[2]['price_note']);

        // A record without the key at all (CSV-style) stays unchecked.
        unset($items[3]['price_valid'], $items[3]['price_note']);

        $delivery = app(DeliveryService::class)->importFromPdfData($items, 5, '2026-07-15', 'Order_1.pdf');
        $row = fn (string $code) => DeliveryItem::where('delivery_id', $delivery->id)->where('supplier_code', $code)->sole();

        $this->assertFalse($row('5007341')->price_valid);
        $this->assertSame('1×3.99×6=23.94, line total 12.00', $row('5007341')->price_note);
        $this->assertTrue($row('5004102')->price_valid);
        $this->assertStringStartsWith('case size read as 401', $row('5004102')->price_note);
        $this->assertTrue($row('12047')->price_valid);
        $this->assertNull($row('12047')->price_note);
        $this->assertNull($row('12048')->price_valid);
        $this->assertNull($row('12048')->price_note);
    }

    public function test_record_parser_warnings_merges_into_import_data(): void
    {
        $delivery = $this->delivery(['filename' => 'Order_1.pdf', 'deposit_reconciliation' => ['x' => 1]]);
        $service = app(DeliveryService::class);

        $service->recordParserWarnings($delivery, [
            'Case size repaired for 5004102: case size read as 401',
            'Price mismatch for 5007341: 1×3.99×6.0=23.94, actual=12.00',
        ]);

        $data = $delivery->fresh()->import_data;
        $this->assertSame('Order_1.pdf', $data['filename']);
        $this->assertSame(['x' => 1], $data['deposit_reconciliation']);
        $this->assertSame([
            'Case size repaired for 5004102: case size read as 401',
            'Price mismatch for 5007341: 1×3.99×6.0=23.94, actual=12.00',
        ], $data['parser_warnings']);

        $other = $this->delivery(['filename' => 'Order_2.pdf'], 'DEL-TEST-2');
        $service->recordParserWarnings($other, []);
        $this->assertSame(['filename' => 'Order_2.pdf'], $other->fresh()->import_data);
    }

    public function test_delivery_page_lists_parser_warnings_and_marks_items(): void
    {
        $deposit = '[Order_1.pdf] Deposit code 313: product lines total 24 units, barrels section says 36';
        $delivery = $this->delivery([
            'filename' => 'Order_1.pdf',
            'parser_warnings' => [
                '[Order_1.pdf] Case size repaired for 5004102: case size read as 401',
                '[Order_1.pdf] Price mismatch for 5007341: 1×3.99×6.0=23.94, actual=12.00',
                $deposit,
            ],
        ]);

        foreach ([
            ['5007341', false, '1×3.99×6=23.94, line total 12.00'],
            ['5004102', true, 'case size read as 401; 1×0.49×40=19.60 matches the line total, using 40'],
        ] as [$code, $valid, $note]) {
            DeliveryItem::create([
                'delivery_id' => $delivery->id,
                'supplier_code' => $code,
                'description' => "Product {$code}",
                'units_per_case' => 6,
                'unit_cost' => 2.00,
                'ordered_quantity' => 6,
                'invoice_delivered_quantity' => 6,
                'total_cost' => 12.00,
                'status' => 'pending',
                'price_valid' => $valid,
                'price_note' => $note,
            ]);
        }

        $response = $this->actingAs($this->manager())
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Parser warnings')
            ->assertSee('[Order_1.pdf] Case size repaired for 5004102: case size read as 401')
            ->assertSee('[Order_1.pdf] Price mismatch for 5007341: 1×3.99×6.0=23.94, actual=12.00')
            ->assertSee('1 line failed the price check')
            ->assertSee('1 case size repaired')
            ->assertSee('Price check')
            ->assertSee('Case size repaired');

        // Without deposit_reconciliation the Barrels/Deposits block does not render,
        // so the deposit warning is listed once, in the Parser warnings block.
        $this->assertSame(1, substr_count($response->getContent(), e($deposit)));
    }

    public function test_delivery_page_labels_a_repaired_purchase_price(): void
    {
        $warning = 'Purchase price repaired for 36489: purchase price 3.17 ran into the description; 6.39 (the sale price) was read in its place, 19.02÷(1×6)=3.17';
        $delivery = $this->delivery([
            'filename' => 'Order_1.pdf',
            'parser_warnings' => [$warning],
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'supplier_code' => '36489',
            'description' => 'Product 36489',
            'units_per_case' => 6,
            'unit_cost' => 3.17,
            'ordered_quantity' => 6,
            'invoice_delivered_quantity' => 6,
            'total_cost' => 19.02,
            'status' => 'pending',
            'price_valid' => true,
            'price_note' => 'purchase price 3.17 ran into the description; 6.39 (the sale price) was read in its place, 19.02÷(1×6)=3.17',
        ]);

        $this->actingAs($this->manager())
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee($warning)
            ->assertSee('1 purchase price repaired')
            ->assertSee('Price repaired')
            ->assertDontSee('Case size repaired')
            ->assertDontSee('case size repaired');
    }

    public function test_deposit_warnings_stay_in_the_deposit_block_when_it_lists_them(): void
    {
        $deposit = 'Deposit code 313: product lines total 24 units, barrels section says 36';
        $delivery = $this->delivery([
            'filename' => 'Order_1.pdf',
            'deposit_reconciliation' => [
                'line_units' => ['313' => 24],
                'section' => ['313' => 36],
                'warnings' => [$deposit],
                'checked_at' => '2026-10-19 10:00:00',
            ],
            'parser_warnings' => ['Case size repaired for 5004102: case size read as 401', $deposit],
        ]);

        $response = $this->actingAs($this->manager())
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Parser warnings')
            ->assertSee('1 deposit mismatch');

        $this->assertSame(1, substr_count($response->getContent(), e($deposit)));
    }

    public function test_delivery_page_without_parser_warnings_has_no_block(): void
    {
        $delivery = $this->delivery(['filename' => 'Order_1.pdf']);

        $this->actingAs($this->manager())
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertDontSee('Parser warnings');
    }
}
