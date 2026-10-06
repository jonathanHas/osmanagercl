<?php

namespace Tests\Feature;

use App\Models\BarrelCode;
use App\Models\Delivery;
use App\Models\DeliveryBarrel;
use App\Models\DeliveryItem;
use App\Models\DepositSighting;
use App\Models\ProductDeposit;
use App\Services\Deposits\DepositEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * Deposit evidence from Udea delivery lines and the suggestions built from it
 * (deposit cycle 2).
 */
class DepositEvidenceServiceTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    private int $deliverySeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
    }

    private function service(): DepositEvidenceService
    {
        return app(DepositEvidenceService::class);
    }

    private function tier(string $code, float $price, bool $charge = true): BarrelCode
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

    private function delivery(string $date = '2026-07-15', int $supplierId = 5): Delivery
    {
        return Delivery::create([
            'delivery_number' => 'DEL-TEST-'.(++$this->deliverySeq),
            'supplier_id' => $supplierId,
            'delivery_date' => $date,
            'status' => 'draft',
        ]);
    }

    private function line(Delivery $delivery, string $code, string $description, int $units = 12, ?string $barrelCode = null, ?string $productId = null): DeliveryItem
    {
        return DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'supplier_code' => $code,
            'description' => $description,
            'barrel_code' => $barrelCode,
            'unit_cost' => 1,
            'ordered_quantity' => $units,
            'invoice_delivered_quantity' => $units,
            'total_cost' => $units,
            'product_id' => $productId,
        ]);
    }

    public function test_split_barrel_code_twin_of_the_python_pattern(): void
    {
        $this->assertSame(['Juice, Luna e Terra Bio-Dynamisch DE', '313'], DepositEvidenceService::splitBarrelCode('Juice, Luna e Terra Bio-Dynamisch DE 313'));
        $this->assertSame(['Water kefir Lgoisucther NL', '10046'], DepositEvidenceService::splitBarrelCode('Water kefir Lgoisucther NL10046'));
        $this->assertSame(['Muesli Biologisch NL', null], DepositEvidenceService::splitBarrelCode('Muesli Biologisch NL'));
        $this->assertSame(['Vitamin D3 1000 IU', null], DepositEvidenceService::splitBarrelCode('Vitamin D3 1000 IU'));
        $this->assertSame(['Omega 3', null], DepositEvidenceService::splitBarrelCode('Omega 3'));
    }

    public function test_backfill_splits_codes_creates_sightings_and_is_idempotent(): void
    {
        $delivery = $this->delivery();
        $juice = $this->line($delivery, '94761', '200 millilitre Apple-mango-juice, Luna e Terra DE 313', 12);
        $kefir = $this->line($delivery, '6000800', '330 millilitre Water kefir Lgoisucther NL10046', 12);
        $muesli = $this->line($delivery, '11111', '750 gram Muesli Biologisch NL', 6);

        $other = $this->delivery('2026-07-15', 99);
        $notUdea = $this->line($other, '555', 'Something DE 313', 3);

        $dry = $this->service()->backfillDeliveryItems(dryRun: true);
        $this->assertSame(['rows' => 2, 'deliveries' => 1, 'codes' => ['313' => 1, '10046' => 1], 'sightings' => 0], $dry);
        $this->assertNull($juice->fresh()->barrel_code);
        $this->assertSame(0, DepositSighting::count());

        $run = $this->service()->backfillDeliveryItems();
        $this->assertSame(2, $run['rows']);
        $this->assertSame(2, $run['sightings']);

        $this->assertSame('313', $juice->fresh()->barrel_code);
        $this->assertSame('200 millilitre Apple-mango-juice, Luna e Terra DE', $juice->fresh()->description);
        $this->assertSame('10046', $kefir->fresh()->barrel_code);
        $this->assertSame('330 millilitre Water kefir Lgoisucther NL', $kefir->fresh()->description);
        $this->assertNull($muesli->fresh()->barrel_code);
        $this->assertSame('750 gram Muesli Biologisch NL', $muesli->fresh()->description);
        $this->assertNull($notUdea->fresh()->barrel_code);

        $sighting = DepositSighting::where('supplier_code', '94761')->sole();
        $this->assertSame('313', $sighting->barrel_code);
        $this->assertSame(12, $sighting->units);
        $this->assertSame($juice->id, $sighting->source_id);
        $this->assertSame('2026-07-15', $sighting->seen_on->toDateString());

        $again = $this->service()->backfillDeliveryItems();
        $this->assertSame(['rows' => 0, 'deliveries' => 0, 'codes' => [], 'sightings' => 0], $again);
        $this->assertSame(2, DepositSighting::count());
        $this->assertSame(0, $this->service()->recordDelivery($delivery));
    }

    public function test_split_barrel_code_accepts_a_garbled_country_code_only_for_known_codes(): void
    {
        $garbled = '750 millilitre Mineral-water carbonic acid, lemoBnio,l oLgaisncdhpark Bio-quNeLlle 313';
        $trimmed = '750 millilitre Mineral-water carbonic acid, lemoBnio,l oLgaisncdhpark Bio-quNeLlle';

        $this->assertSame([$trimmed, '313'], DepositEvidenceService::splitBarrelCode($garbled, ['313', '9936']));
        $this->assertSame([$garbled, null], DepositEvidenceService::splitBarrelCode($garbled));
        $this->assertSame([$garbled, null], DepositEvidenceService::splitBarrelCode($garbled, ['315']));
        // The country form wins over the known codes.
        $this->assertSame(['Pasta Biologisch NL', '315'], DepositEvidenceService::splitBarrelCode('Pasta Biologisch NL 315', ['313']));
        // A pack size is never a code.
        $waffles = "315 gram Wheat-waffles, Billy's farm Biologisch NL";
        $this->assertSame([$waffles, null], DepositEvidenceService::splitBarrelCode($waffles, ['315']));
    }

    public function test_backfill_uses_only_the_deliverys_own_barrel_codes_for_garbled_lines(): void
    {
        $garbled = '700 millilitre Fruit-juice pink grapefruit sweetieB, iYolooguirs cOhrganic NatuDreE 313';
        $waffles = "315 gram Wheat-waffles, Billy's farm Biologisch NL";

        $with = $this->delivery();
        DeliveryBarrel::create(['delivery_id' => $with->id, 'supplier_code' => '313', 'description' => 'Statiegeld glas', 'quantity' => 6, 'unit_price' => 0.25, 'total' => 1.50]);
        DeliveryBarrel::create(['delivery_id' => $with->id, 'supplier_code' => '315', 'description' => 'Statiegeld glas', 'quantity' => 1, 'unit_price' => 0.70, 'total' => 0.70]);
        $hit = $this->line($with, '97433', $garbled, 6);
        $wafflesWith = $this->line($with, '36214', $waffles, 3);

        $without = $this->delivery('2026-07-22');
        DeliveryBarrel::create(['delivery_id' => $without->id, 'supplier_code' => '69', 'description' => 'Krat', 'quantity' => 1, 'unit_price' => 1.50, 'total' => 1.50]);
        $miss = $this->line($without, '97433', $garbled, 6);
        $wafflesWithout = $this->line($without, '36214', $waffles, 3);

        $result = $this->service()->backfillDeliveryItems();

        $this->assertSame(1, $result['rows']);
        $this->assertSame('313', $hit->fresh()->barrel_code);
        $this->assertSame('700 millilitre Fruit-juice pink grapefruit sweetieB, iYolooguirs cOhrganic NatuDreE', $hit->fresh()->description);
        $this->assertNull($miss->fresh()->barrel_code);
        $this->assertSame($garbled, $miss->fresh()->description);
        $this->assertNull($wafflesWith->fresh()->barrel_code);
        $this->assertNull($wafflesWithout->fresh()->barrel_code);

        $this->assertSame(0, $this->service()->backfillDeliveryItems()['rows']);
    }

    public function test_suggestions_only_for_charge_customer_tiers(): void
    {
        $this->tier('313', 0.25);
        $this->tier('10046', 0.15, charge: false);
        $juiceId = $this->posProduct('8711521947614', 'Juice');
        $kefirId = $this->posProduct('8700000000001', 'Kefir');
        $this->supplierLink('8711521947614', '94761');
        $this->supplierLink('8700000000001', '6000800');

        $delivery = $this->delivery();
        $this->line($delivery, '94761', 'Juice DE', 12, '313');
        $this->line($delivery, '6000800', 'Kefir NL', 12, '10046');
        $this->service()->recordDelivery($delivery);

        $result = $this->service()->refreshSuggestions();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['tier_off']);
        $row = ProductDeposit::sole();
        $this->assertSame($juiceId, $row->product_id);
        $this->assertSame('8711521947614', $row->product_code);
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $row->status);
        $this->assertSame(ProductDeposit::SOURCE_INVOICE, $row->source);
        $this->assertSame(12, $row->sightings_units);
        $this->assertSame(1, $row->sightings_count);
        $this->assertSame(0, $row->conflicting_units);
        $this->assertSame('2026-07-15', $row->last_seen_on->toDateString());
        $this->assertFalse(ProductDeposit::where('product_id', $kefirId)->exists());

        // A re-run changes nothing.
        $again = $this->service()->refreshSuggestions();
        $this->assertSame(0, $again['created']);
        $this->assertSame(0, $again['updated']);
        $this->assertSame(1, $again['unchanged']);
    }

    public function test_dominant_code_wins_and_conflicting_units_are_counted(): void
    {
        $t313 = $this->tier('313', 0.25);
        $t315 = $this->tier('315', 0.70);
        $wafflesId = $this->posProduct('5000000000036', 'Waffles');
        $this->supplierLink('5000000000036', '36214');

        $this->line($a = $this->delivery('2026-06-01'), '36214', 'Waffles NL', 6, '315');
        $this->line($b = $this->delivery('2026-06-08'), '36214', 'Waffles NL', 6, '315');
        $this->line($c = $this->delivery('2026-06-15'), '36214', 'Waffles NL', 2, '313');
        foreach ([$a, $b, $c] as $delivery) {
            $this->service()->recordDelivery($delivery);
        }

        $this->service()->refreshSuggestions();

        $row = ProductDeposit::where('product_id', $wafflesId)->sole();
        $this->assertSame($t315->id, $row->barrel_code_id);
        $this->assertSame(12, $row->sightings_units);
        $this->assertSame(2, $row->sightings_count);
        $this->assertSame(2, $row->conflicting_units);
        $this->assertSame('2026-06-15', $row->last_seen_on->toDateString());

        // A suggestion follows the evidence when it shifts.
        $this->line($d = $this->delivery('2026-06-22'), '36214', 'Waffles NL', 24, '313');
        $this->service()->recordDelivery($d);
        $this->service()->refreshSuggestions();

        $row->refresh();
        $this->assertSame($t313->id, $row->barrel_code_id);
        $this->assertSame(26, $row->sightings_units);
        $this->assertSame(12, $row->conflicting_units);
    }

    public function test_confirmed_row_keeps_its_tier_when_the_evidence_changes(): void
    {
        $t313 = $this->tier('313', 0.25);
        $this->tier('315', 0.70);
        $id = $this->posProduct('5000000000001', 'Yogurt');
        $this->supplierLink('5000000000001', '33649');

        ProductDeposit::create([
            'product_id' => $id,
            'barrel_code_id' => $t313->id,
            'status' => ProductDeposit::STATUS_CONFIRMED,
            'source' => ProductDeposit::SOURCE_MANUAL,
        ]);

        $this->line($delivery = $this->delivery(), '33649', 'Yogurt NL', 6, '315');
        $this->service()->recordDelivery($delivery);
        $this->service()->refreshSuggestions();

        $row = ProductDeposit::sole();
        $this->assertSame($t313->id, $row->barrel_code_id);
        $this->assertSame(ProductDeposit::STATUS_CONFIRMED, $row->status);
        $this->assertSame(0, $row->sightings_units);
        $this->assertSame(6, $row->conflicting_units);
    }

    public function test_product_resolves_through_the_delivery_line_when_there_is_no_supplier_link(): void
    {
        $this->tier('313', 0.25);
        $id = $this->posProduct('5000000000002', 'Cream');

        $this->line($delivery = $this->delivery(), '45762', 'Cream NL', 7, '313', $id);
        $this->service()->recordDelivery($delivery);
        $this->service()->refreshSuggestions();

        $this->assertSame($id, ProductDeposit::sole()->product_id);
    }

    public function test_units_fall_back_to_ordered_when_the_delivered_quantity_is_zero(): void
    {
        // Lines imported before invoice_delivered_quantity existed hold 0 there.
        $delivery = $this->delivery();
        $item = $this->line($delivery, '45751', 'Buttermilk NL', 2, '315');
        $item->update(['invoice_delivered_quantity' => 0]);

        $this->service()->recordDelivery($delivery);

        $this->assertSame(2, DepositSighting::sole()->units);
    }

    public function test_after_delivery_import_never_throws(): void
    {
        Log::spy();
        $this->tier('313', 0.25);
        $this->line($delivery = $this->delivery(), '94761', 'Juice DE', 12, '313');
        DB::connection('pos')->getSchemaBuilder()->drop('supplier_link');

        $this->service()->afterDeliveryImport($delivery);

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Deposit evidence hook failed')->once();
        $this->assertSame(1, DepositSighting::count());
    }

    public function test_unmatched_supplier_code_is_counted_not_created(): void
    {
        $this->tier('313', 0.25);

        $this->line($delivery = $this->delivery(), '99999', 'Unknown DE', 4, '313');
        $this->service()->recordDelivery($delivery);

        $result = $this->service()->refreshSuggestions();

        $this->assertSame(['99999'], $result['unmatched']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, ProductDeposit::count());
    }
}
