<?php

namespace Tests\Feature;

use App\Models\DeliveryLabelPrint;
use App\Models\ProductTranslation;
use App\Models\Role;
use App\Models\User;
use App\Services\ZebraPrintResult;
use App\Services\ZebraPrintService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\TestCase;

/**
 * Covers the fix for translated labels reprinting the whole delivery on every press.
 *
 * The print set used to be derived from the cumulative scanned quantities with nothing
 * recorded afterwards, so each press reprinted everything already printed.
 */
class DeliveryTranslatedLabelPrintingTest extends TestCase
{
    use AliasesMysqlConnection, RefreshDatabase;

    private const DELIVERY_ID = '7acd5a93-2e20-4bf0-a6a8-f5429765fd0b';

    private const SUPPLIER_ID = '5';

    /** @var array<int, string> ZPL payloads handed to the fake printer, newest last. */
    private array $printedPayloads = [];

    private ZebraPrintResult $printerResponse;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for delivery label printing tests.');
        }

        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('pos');

        $schema = DB::connection('pos')->getSchemaBuilder();

        $schema->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('CATEGORY')->nullable();
            $table->decimal('PRICESELL', 8, 2)->default(0);
            $table->string('TAXCAT')->nullable();
        });

        $schema->create('deliveriesScanItems', function (Blueprint $table) {
            $table->increments('id');
            $table->string('delID');
            $table->string('barcode');
            $table->integer('quantity')->default(0);
        });

        // Default: the printer accepts everything.
        $this->printerResponse = new ZebraPrintResult(
            success: true,
            jobId: 'ZTC-GX430t-1',
            output: 'request id is ZTC-GX430t-1 (1 file(s))',
        );

        $this->fakePrinter();

        // The admin role short-circuits the permission middleware, which guards undo.
        $this->actingAs(User::factory()->create([
            'role_id' => Role::create(['name' => 'admin', 'display_name' => 'Admin'])->id,
        ]));
    }

    /**
     * Bind a ZebraPrintService that records payloads instead of shelling out.
     */
    private function fakePrinter(): void
    {
        $this->app->bind(ZebraPrintService::class, function () {
            $service = new ZebraPrintService('printer.test', '631', 'TEST-PRINTER', 1);

            return $service->usingRunner(function (string $command) {
                // sendRaw() writes the ZPL to a temp file named in the command.
                if (preg_match("/-o raw '([^']+)'/", $command, $m) && is_file($m[1])) {
                    $this->printedPayloads[] = file_get_contents($m[1]);
                }

                return $this->printerResponse->success
                    ? 'request id is '.$this->printerResponse->jobId.' (1 file(s))'
                    : $this->printerResponse->output;
            });
        });
    }

    private function scan(string $barcode, int $quantity): void
    {
        DB::connection('pos')->table('deliveriesScanItems')->insert([
            'delID' => self::DELIVERY_ID,
            'barcode' => $barcode,
            'quantity' => $quantity,
        ]);
    }

    private function product(string $code, string $name): void
    {
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'p-'.$code,
            'CODE' => $code,
            'NAME' => $name,
            'CATEGORY' => 'SUB1',
            'PRICESELL' => 2.50,
            'TAXCAT' => 'VAT0',
        ]);
    }

    private function translation(string $code, bool $autoPrint = true, string $zpl = '^XA^FO50,50^FDTest^FS^XZ'): ProductTranslation
    {
        return ProductTranslation::create([
            'product_code' => $code,
            'label_data' => ['product_name' => 'Translated '.$code],
            'label_size' => 'large',
            'font_scale' => 1.0,
            'zpl_content' => $zpl,
            'auto_print' => $autoPrint,
        ]);
    }

    private function print(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('delivery-legacy.print-translations'), array_merge([
            'delID' => self::DELIVERY_ID,
            'supplierID' => self::SUPPLIER_ID,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], $overrides));
    }

    public function test_first_print_records_the_ledger_and_sends_the_job(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 3);

        $response = $this->print();

        $response->assertOk()
            ->assertJson(['success' => true, 'printed' => 1, 'labels' => 3]);

        $this->assertCount(1, $this->printedPayloads);
        $this->assertStringContainsString('^PQ3,0,0,Y', $this->printedPayloads[0]);

        $this->assertDatabaseHas('delivery_label_prints', [
            'delivery_id' => self::DELIVERY_ID,
            'barcode' => '111',
            'quantity' => 3,
            'cups_job_id' => 'ZTC-GX430t-1',
            'failed_at' => null,
        ]);
    }

    /** The reported bug: pressing Print again reprinted the whole delivery. */
    public function test_second_press_prints_nothing_and_never_reaches_the_printer(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 3);

        $this->print()->assertOk();
        $this->assertCount(1, $this->printedPayloads);

        $second = $this->print();

        $second->assertOk()->assertJson([
            'success' => true,
            'nothing_outstanding' => true,
            'printed' => 0,
            'labels' => 0,
            'translatable_count' => 0,
        ]);

        // No second job reached the printer.
        $this->assertCount(1, $this->printedPayloads);
    }

    public function test_scanning_more_units_prints_only_the_delta(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 3);

        $this->print()->assertOk();

        $this->scan('111', 3); // running total is now 6

        $response = $this->print();

        $response->assertOk()->assertJson(['success' => true, 'labels' => 3]);

        $this->assertCount(2, $this->printedPayloads);
        $this->assertStringContainsString('^PQ3,0,0,Y', $this->printedPayloads[1]);
        $this->assertSame(6, (int) DeliveryLabelPrint::where('barcode', '111')->sum('quantity'));
    }

    public function test_repeating_an_idempotency_key_prints_once(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 2);

        $key = 'fixed-key-123';

        $this->print(['idempotency_key' => $key])->assertOk();
        $repeat = $this->print(['idempotency_key' => $key]);

        $repeat->assertOk()->assertJson(['already_printed' => true, 'labels' => 0]);
        $this->assertCount(1, $this->printedPayloads);
        $this->assertSame(1, DeliveryLabelPrint::where('idempotency_key', $key)->count());
    }

    public function test_force_reprints_the_full_scanned_quantity(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 2);

        $this->print()->assertOk();

        $response = $this->print(['force' => true]);

        $response->assertOk()->assertJson(['success' => true, 'labels' => 2]);
        $this->assertCount(2, $this->printedPayloads);
        $this->assertDatabaseHas('delivery_label_prints', ['barcode' => '111', 'forced' => true]);
    }

    public function test_a_refused_job_is_rolled_back_and_reprints_next_time(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 2);

        // lp ran and definitively refused.
        $this->printerResponse = new ZebraPrintResult(
            success: false,
            jobId: null,
            output: 'lp: Destination "TEST-PRINTER" does not exist.',
            timedOut: false,
        );

        $this->print()->assertStatus(500)->assertJson(['success' => false, 'timed_out' => false]);

        // The batch is marked failed, so the labels are outstanding again.
        $this->assertNotNull(DeliveryLabelPrint::first()->failed_at);
        $this->assertSame(0, DeliveryLabelPrint::printedQuantitiesFor(self::DELIVERY_ID)->get('111', 0));

        $this->printerResponse = new ZebraPrintResult(true, 'ZTC-GX430t-9', 'request id is ZTC-GX430t-9');

        $this->print()->assertOk()->assertJson(['labels' => 2]);
    }

    /** A timeout must NOT roll back: CUPS may already hold the job. */
    public function test_a_timed_out_job_stays_recorded_so_the_next_press_prints_nothing(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 2);

        $this->printerResponse = new ZebraPrintResult(
            success: false,
            jobId: null,
            output: '',
            timedOut: true,
        );

        $this->print()->assertStatus(500)->assertJson(['timed_out' => true]);

        $this->assertNull(DeliveryLabelPrint::first()->failed_at);

        $this->printerResponse = new ZebraPrintResult(true, 'ZTC-GX430t-2', 'request id is ZTC-GX430t-2');

        $this->print()->assertOk()->assertJson(['nothing_outstanding' => true, 'labels' => 0]);
        $this->assertCount(1, $this->printedPayloads);
    }

    /**
     * Root cause C: auto_print was filtered before the newest-per-code grouping, so an
     * older enabled row resurrected a product whose current translation was switched off.
     */
    public function test_a_disabled_newest_translation_suppresses_an_older_enabled_one(): void
    {
        $this->product('111', 'Olives');
        $this->scan('111', 2);

        $older = $this->translation('111', true, '^XA^FDOLD^FS^XZ');
        $older->forceFill(['created_at' => now()->subDay()])->save();

        $newest = $this->translation('111', false, '^XA^FDNEW^FS^XZ');
        $newest->forceFill(['created_at' => now()])->save();

        $this->print()->assertStatus(422)->assertJson(['success' => false]);

        $this->assertCount(0, $this->printedPayloads);
    }

    public function test_outstanding_endpoint_reports_printed_and_outstanding(): void
    {
        $this->product('111', 'Olives');
        $this->product('222', 'Capers');
        $this->translation('111');
        $this->translation('222');
        $this->scan('111', 3);
        $this->scan('222', 1);

        $this->print(['barcodes' => ['111']])->assertOk();

        $response = $this->getJson(route('delivery-legacy.translatable-products', [
            'delID' => self::DELIVERY_ID,
            'supplierID' => self::SUPPLIER_ID,
        ]));

        $response->assertOk()->assertJson(['count' => 1]);

        $products = collect($response->json('products'))->keyBy('barcode');

        $this->assertSame(3, $products['111']['printed']);
        $this->assertSame(0, $products['111']['outstanding']);
        $this->assertSame(0, $products['222']['printed']);
        $this->assertSame(1, $products['222']['outstanding']);
    }

    public function test_undo_puts_the_last_batch_back_on_the_list(): void
    {
        $this->product('111', 'Olives');
        $this->translation('111');
        $this->scan('111', 2);

        $this->print()->assertOk();

        $this->postJson(route('delivery-legacy.undo-last-print'), [
            'delID' => self::DELIVERY_ID,
            'supplierID' => self::SUPPLIER_ID,
        ])->assertOk()->assertJson(['success' => true, 'translatable_count' => 1]);

        $this->assertSame(0, DeliveryLabelPrint::count());
    }
}
