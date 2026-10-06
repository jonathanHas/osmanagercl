<?php

namespace Tests\Feature;

use App\Models\DeliveryItem;
use App\Services\DeliveryParsingService;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * The per-line deposit (barrel) code travels from the parser output to
 * delivery_items.barrel_code (deposit cycle 2).
 */
class DeliveryImportBarrelCodeTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    public function test_barrel_code_is_stored_on_the_delivery_item(): void
    {
        $this->createDepositPosTables();
        Queue::fake();

        $juiceId = $this->posProduct('8711521947614', 'Luna e Terra Apple-mango juice 200ml');
        $this->supplierLink('8711521947614', '94761');

        $parsed = [
            'success' => true,
            'data' => [
                'items' => [
                    [
                        'code' => '94761',
                        'product' => '200millilitre Apple-mango-juice, Luna e Terra Bio-Dynamisch DE',
                        'case_size' => 12,
                        'total_ordered_units' => 12,
                        'total_delivered_units' => 12,
                        'unit_cost' => 0.89,
                        'rsp' => 1.79,
                        'line_total' => 10.68,
                        'price_valid' => true,
                        'barrel_code' => '313',
                    ],
                    [
                        'code' => '12047',
                        'product' => '125gram Blueberry, Biologisch CL',
                        'case_size' => 12,
                        'total_ordered_units' => 12,
                        'total_delivered_units' => 12,
                        'unit_cost' => 2.07,
                        'rsp' => 3.59,
                        'line_total' => 24.84,
                        'price_valid' => true,
                        'barrel_code' => null,
                    ],
                ],
            ],
        ];

        $items = app(DeliveryParsingService::class)->convertToDeliveryItems($parsed);
        $this->assertSame('313', $items[0]['barrel_code']);
        $this->assertNull($items[1]['barrel_code']);

        $delivery = app(DeliveryService::class)->importFromPdfData($items, 5, '2026-07-15', 'Order_1.pdf');

        $juice = DeliveryItem::where('delivery_id', $delivery->id)->where('supplier_code', '94761')->sole();
        $this->assertSame('313', $juice->barrel_code);
        $this->assertSame('200millilitre Apple-mango-juice, Luna e Terra Bio-Dynamisch DE', $juice->description);
        $this->assertSame($juiceId, $juice->product_id);

        $blueberry = DeliveryItem::where('delivery_id', $delivery->id)->where('supplier_code', '12047')->sole();
        $this->assertNull($blueberry->barrel_code);
        $this->assertSame('125gram Blueberry, Biologisch CL', $blueberry->description);
    }
}
