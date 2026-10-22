<?php

namespace Tests\Feature\Shop;

use App\Models\Country;
use App\Models\Permission;
use App\Models\ProductTranslation;
use App\Models\Role;
use App\Models\User;
use App\Models\VegDetails;
use App\Models\ZebraLabel;
use App\Services\ZebraPrintService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\TestCase;

/**
 * Shop mode print labels, Zebra labels tab: saved Zebra labels and translated
 * labels printed on the counter printer.
 *
 * The fixture follows the dev data: bagged produce on the till with an out of
 * date price on its label, one not on the till, a standalone label and one
 * translated label. The printer is faked so the ZPL that would be sent can be
 * read back. The country of origin tests add French Beans (addBeans()): a label
 * that says Spain for a product the till says is from Ireland.
 */
class ShopZebraTest extends TestCase
{
    use AliasesMysqlConnection, RefreshDatabase;

    private const KALE = '000000000102';

    private const ROCKET = '000000000329';

    private const HALLOUMI = '5201111222999';

    private const BEANS = '000000000285';

    private const KALE_ZPL = '^XA^FT30,60^A0N,28,28^FDCurly Kale 200g^FS^FT30,120^A0N,28,28^FD\\152.49^FS^PQ1^XZ';

    private const PARSNIP_ZPL = '^XA^FT30,60^A0N,28,28^FDParsnips^FS^FT30,120^A0N,28,28^FDFrance^FS^PQ1^XZ';

    /** @var array<int, string> ZPL payloads handed to the fake printer, newest last. */
    private array $printed = [];

    private ZebraLabel $kale;

    private ZebraLabel $rocket;

    private ZebraLabel $parsnip;

    private ProductTranslation $halloumi;

    private ?ZebraLabel $beans = null;

    /** @var array<string, int> countries.id by name */
    private array $countries = [];

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required.');
        }

        parent::setUp();

        // ProductTranslation and the vegDetails -> countries hop pin 'mysql'.
        $this->aliasMysqlConnectionToTestDatabase();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        $pos = DB::connection('pos')->getSchemaBuilder();

        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('CATEGORY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });
        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 8, 6)->default(0);
        });
        $pos->create('TAXCATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });
        // On the till = a row here (TillVisibilityService).
        $pos->create('PRODUCTS_CAT', function (Blueprint $table) {
            $table->string('PRODUCT')->primary();
            $table->integer('CATORDER')->nullable();
        });
        $pos->create('vegDetails', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('product');
            $table->integer('countryCode')->nullable();
            $table->integer('classId')->nullable();
            $table->integer('unitId')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            // 2.2683 × 1.23 = 2.79 gross; the label still says 2.49.
            ['ID' => 'p1', 'NAME' => 'Curly Kale 200g', 'CODE' => self::KALE, 'TAXCAT' => 'tc1', 'PRICESELL' => 2.2683],
            // 1.6179 × 1.23 = 1.99 gross, as on its label.
            ['ID' => 'p2', 'NAME' => 'Rocket Mossfield 100g', 'CODE' => self::ROCKET, 'TAXCAT' => 'tc1', 'PRICESELL' => 1.6179],
            ['ID' => 'p3', 'NAME' => 'Halloumi', 'CODE' => self::HALLOUMI, 'TAXCAT' => 'tc1', 'PRICESELL' => 3.2439],
        ]);
        DB::connection('pos')->table('TAXCATEGORIES')->insert(['ID' => 'tc1', 'NAME' => 'Standard']);
        DB::connection('pos')->table('TAXES')->insert(['ID' => 't1', 'CATEGORY' => 'tc1', 'RATE' => 0.23]);
        DB::connection('pos')->table('PRODUCTS_CAT')->insert(['PRODUCT' => 'p1', 'CATORDER' => 1]);

        $owner = User::factory()->create();

        $this->kale = ZebraLabel::create([
            'name' => 'Kale Curly 300g',
            'product_code' => self::KALE,
            'zpl_content' => self::KALE_ZPL,
            'label_width_mm' => 112.6,
            'label_height_mm' => 75.1,
            'default_copies' => 2,
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $this->rocket = ZebraLabel::create([
            'name' => 'Rocket_Mossfield',
            'product_code' => self::ROCKET,
            'zpl_content' => '^XA^FT30,60^A0N,28,28^FDRocket^FS^FT30,120^A0N,28,28^FD\\151.99^FS^PQ1^XZ',
            'label_width_mm' => 115.2,
            'label_height_mm' => 78.3,
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $this->parsnip = ZebraLabel::create([
            'name' => 'parsnip',
            'zpl_content' => self::PARSNIP_ZPL,
            'label_width_mm' => 112.6,
            'label_height_mm' => 75.1,
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $this->halloumi = ProductTranslation::create([
            'product_id' => 'p3',
            'product_code' => self::HALLOUMI,
            'label_data' => ['product_name' => 'Dynamis Halloumi 250 g'],
            'label_size' => 'large',
            'zpl_content' => '^XA^FO50,50^A0N,28,28^FDHalloumi^FS^PQ1^XZ',
            'auto_print' => true,
            'created_by' => $owner->id,
        ]);

        // The countries migration seeds these 37 rows; firstOrCreate keeps the
        // test independent of that.
        foreach (['Ireland', 'Spain', 'France', 'Netherlands'] as $i => $name) {
            $this->countries[$name] = Country::firstOrCreate(['name' => $name], ['sort_order' => $i])->id;
        }

        $this->fakePrinter();
    }

    /**
     * Green Beans on the till, from Ireland on the till; its saved label says
     * Spain. The price matches (2.3984 × 1.23 = 2.95), so only the country differs.
     */
    private function addBeans(): ZebraLabel
    {
        DB::connection('pos')->table('PRODUCTS')->insert(['ID' => 'p4', 'NAME' => 'Green Beans 200g', 'CODE' => self::BEANS, 'TAXCAT' => 'tc1', 'PRICESELL' => 2.3984]);
        DB::connection('pos')->table('PRODUCTS_CAT')->insert(['PRODUCT' => 'p4', 'CATORDER' => 2]);
        DB::connection('pos')->table('vegDetails')->insert(['ID' => 'v4', 'product' => self::BEANS, 'countryCode' => $this->countries['Ireland'], 'classId' => 1, 'unitId' => 1]);

        return $this->beans = ZebraLabel::create([
            'name' => 'French Beans 200g',
            'product_code' => self::BEANS,
            'zpl_content' => '^XA^FT30,40^A0N,28,28^FDOrganic Store^FS^FT30,80^A0N,28,28^FDGreen Beans^FS'
                .'^FT30,120^A0N,28,28^FD\\152.95^FS^FT200,160^A0N,28,28^FDSpain^FS^FT30,160^A0N,28,28^FDOrigin :^FS^PQ1^XZ',
            'label_width_mm' => 112.6,
            'label_height_mm' => 75.1,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    /**
     * The Shop list's items keyed by kind:id.
     *
     * @return array<string, array>
     */
    private function listByKey(User $user): array
    {
        return collect($this->actingAs($user)->getJson(route('labels.zebra-list'))->assertOk()->json('labels'))
            ->keyBy(fn ($l) => $l['kind'].':'.$l['id'])
            ->all();
    }

    /**
     * Bind a ZebraPrintService that records payloads instead of shelling out
     * (as DeliveryTranslatedLabelPrintingTest does).
     */
    private function fakePrinter(): void
    {
        $this->app->bind(ZebraPrintService::class, function () {
            return (new ZebraPrintService('printer.test', '631', 'TEST-PRINTER', 1))
                ->usingRunner(function (string $command) {
                    // sendRaw() writes the ZPL to a temp file named in the command.
                    if (preg_match("/-o raw '([^']+)'/", $command, $m) && is_file($m[1])) {
                        $this->printed[] = file_get_contents($m[1]);
                    }

                    return 'request id is TEST-PRINTER-1 (1 file(s))';
                });
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Labels']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    private function employee(): User
    {
        return $this->userWith('employee', ['labels.print']);
    }

    private function manager(): User
    {
        return $this->userWith('manager', ['labels.print', 'labels.manage']);
    }

    public function test_employee_can_open_the_zebra_screen(): void
    {
        $response = $this->actingAs($this->employee())
            ->get(route('shop.zebra'))
            ->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('href="'.e(route('shop.zebra')).'" aria-current="page"', false);
        $response->assertSee('href="'.e(route('shop.labels')).'"', false);
        $response->assertDontSee('href="'.e(route('shop.labels')).'" aria-current="page"', false);
        $response->assertSee('data-list-url="'.e(route('labels.zebra-list')).'"', false);
        $response->assertSee('data-queue-url="'.e(route('labels.printer-queue')).'"', false);
        $response->assertSee('shop-scan__input', false);
        $response->assertSee('Copies');
        $response->assertDontSee('Cancel all');
        $response->assertSee('data-cancel-url=""', false);
        $response->assertSee('id="zebra-country"', false);
        foreach (['Ireland', 'Spain', 'France', 'Netherlands'] as $name) {
            $response->assertSee('<option value="'.$this->countries[$name].'">'.$name.'</option>', false);
        }
        $response->assertDontSee('Print with till country');
    }

    public function test_manager_sees_cancel_all(): void
    {
        $this->actingAs($this->manager())
            ->get(route('shop.zebra'))
            ->assertOk()
            ->assertSee('Cancel all')
            ->assertSee('data-cancel-url="'.e(route('labels.printer-cancel')).'"', false);
    }

    public function test_barista_is_forbidden(): void
    {
        $barista = $this->userWith('barista', ['kds.access']);

        $this->actingAs($barista)->get(route('shop.zebra'))->assertForbidden();
        $this->actingAs($barista)->getJson(route('labels.zebra-list'))->assertForbidden();
    }

    public function test_list_groups_labels_reports_mismatches_and_includes_translations(): void
    {
        $labels = $this->actingAs($this->employee())
            ->getJson(route('labels.zebra-list'))
            ->assertOk()
            ->json('labels');

        $this->assertCount(4, $labels);
        [$kale, $rocket, $parsnip, $halloumi] = $labels;

        $this->assertSame('zebra', $kale['kind']);
        $this->assertSame('till', $kale['group']);
        $this->assertSame($this->kale->id, $kale['id']);
        $this->assertSame('Curly Kale 200g', $kale['name']);
        $this->assertSame('Kale Curly 300g', $kale['label']);
        $this->assertSame(self::KALE, $kale['product_code']);
        $this->assertSame(2, $kale['default_copies']);
        $this->assertSame(1, $kale['mismatches']['price']['field_index']);
        $this->assertSame('2.49', $kale['mismatches']['price']['label_value']);
        $this->assertSame('2.79', $kale['mismatches']['price']['db_value']);
        $this->assertSame('\\152.79', $kale['mismatches']['price']['new_field']);
        $this->assertSame(route('zebra-labels.print', $this->kale), $kale['print_url']);

        $this->assertSame('other', $rocket['group']);
        $this->assertSame('Rocket Mossfield 100g', $rocket['name']);
        $this->assertNull($rocket['mismatches']);
        $this->assertSame(1, $rocket['default_copies']);

        $this->assertSame('standalone', $parsnip['group']);
        $this->assertSame('parsnip', $parsnip['name']);
        $this->assertNull($parsnip['label']);
        $this->assertNull($parsnip['product_code']);

        $this->assertSame('translated', $halloumi['kind']);
        $this->assertSame('translated', $halloumi['group']);
        $this->assertSame('Dynamis Halloumi 250 g', $halloumi['name']);
        $this->assertSame(self::HALLOUMI, $halloumi['product_code']);
        $this->assertSame(75, $halloumi['width_mm']);
        $this->assertSame(50, $halloumi['height_mm']);
        $this->assertTrue($halloumi['auto_print']);
        $this->assertNull($halloumi['mismatches']);
        $this->assertSame(route('labels.translate.print', $this->halloumi), $halloumi['print_url']);

        // About 25 kB of ZPL per label: it must never travel in the list.
        foreach ($labels as $label) {
            $this->assertArrayNotHasKey('zpl_content', $label);
            $this->assertArrayNotHasKey('fields', $label);
        }
    }

    public function test_list_carries_the_country_of_origin(): void
    {
        $this->addBeans();
        $labels = $this->listByKey($this->employee());

        $beans = $labels['zebra:'.$this->beans->id];
        $this->assertSame('till', $beans['group']);
        $this->assertSame([
            'label' => 'Spain',
            'label_id' => $this->countries['Spain'],
            'field_index' => 3,
            'till' => 'Ireland',
            'till_id' => $this->countries['Ireland'],
        ], $beans['country']);
        $this->assertArrayNotHasKey('price', $beans['mismatches']);
        $this->assertSame('Spain', $beans['mismatches']['country']['label_value']);
        $this->assertSame(route('zebra-labels.set-country', $this->beans), $beans['country_url']);

        // No country text, no till record.
        $this->assertSame(
            ['label' => null, 'label_id' => null, 'field_index' => null, 'till' => null, 'till_id' => null],
            $labels['zebra:'.$this->kale->id]['country']
        );

        // Standalone: the text is read, there is no till record.
        $parsnip = $labels['zebra:'.$this->parsnip->id]['country'];
        $this->assertSame('France', $parsnip['label']);
        $this->assertSame(1, $parsnip['field_index']);
        $this->assertNull($parsnip['till']);

        $halloumi = $labels['translated:'.$this->halloumi->id];
        $this->assertNull($halloumi['country']);
        $this->assertNull($halloumi['country_url']);
    }

    public function test_set_country_rewrites_the_label_and_the_till_record(): void
    {
        $this->addBeans();
        $employee = $this->employee();

        $label = $this->actingAs($employee)
            ->patchJson(route('zebra-labels.set-country', $this->beans), ['country_id' => $this->countries['Netherlands']])
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('label');

        $this->assertSame('zebra', $label['kind']);
        $this->assertSame('till', $label['group']);
        $this->assertSame($this->beans->id, $label['id']);
        $this->assertSame('Netherlands', $label['country']['label']);
        $this->assertSame('Netherlands', $label['country']['till']);
        $this->assertSame(3, $label['country']['field_index']);
        $this->assertNull($label['mismatches']);
        $this->assertSame(route('zebra-labels.print', $this->beans), $label['print_url']);
        $this->assertSame(route('zebra-labels.set-country', $this->beans), $label['country_url']);

        $zpl = $this->beans->fresh()->zpl_content;
        $this->assertStringContainsString('^FDNetherlands^FS', $zpl);
        $this->assertStringNotContainsString('^FDSpain^FS', $zpl);
        $this->assertSame(
            $this->countries['Netherlands'],
            (int) VegDetails::where('product', self::BEANS)->value('countryCode')
        );

        // The list now matches the till for beans.
        $listed = $this->listByKey($employee)['zebra:'.$this->beans->id];
        $this->assertNull($listed['mismatches']);
        $this->assertSame('Netherlands', $listed['country']['label']);
    }

    public function test_set_country_on_a_label_without_country_text_saves_the_till_only(): void
    {
        $this->assertSame(0, VegDetails::where('product', self::KALE)->count());

        $label = $this->actingAs($this->employee())
            ->patchJson(route('zebra-labels.set-country', $this->kale), ['country_id' => $this->countries['Spain']])
            ->assertOk()
            ->json('label');

        $this->assertSame(self::KALE_ZPL, $this->kale->fresh()->zpl_content);
        $this->assertSame(
            $this->countries['Spain'],
            (int) VegDetails::where('product', self::KALE)->value('countryCode')
        );
        $this->assertNull($label['country']['label']);
        $this->assertNull($label['country']['field_index']);
        $this->assertSame('Spain', $label['country']['till']);
    }

    public function test_set_country_on_a_standalone_label_changes_only_the_label(): void
    {
        $before = VegDetails::count();

        $label = $this->actingAs($this->employee())
            ->patchJson(route('zebra-labels.set-country', $this->parsnip), ['country_id' => $this->countries['Spain']])
            ->assertOk()
            ->json('label');

        $zpl = $this->parsnip->fresh()->zpl_content;
        $this->assertStringContainsString('^FDSpain^FS', $zpl);
        $this->assertStringNotContainsString('^FDFrance^FS', $zpl);
        $this->assertSame($before, VegDetails::count());
        $this->assertSame('standalone', $label['group']);
        $this->assertSame('Spain', $label['country']['label']);
        $this->assertNull($label['country']['till']);
    }

    public function test_set_country_validates_and_is_for_printers(): void
    {
        $this->addBeans();

        $this->actingAs($this->employee())
            ->patchJson(route('zebra-labels.set-country', $this->beans), ['country_id' => 9999])
            ->assertStatus(422);

        $barista = $this->userWith('barista', ['kds.access']);
        $this->actingAs($barista)
            ->patchJson(route('zebra-labels.set-country', $this->beans), ['country_id' => $this->countries['Spain']])
            ->assertForbidden();

        // Nothing changed.
        $this->assertStringContainsString('^FDSpain^FS', $this->beans->fresh()->zpl_content);
        $this->assertSame($this->countries['Ireland'], (int) VegDetails::where('product', self::BEANS)->value('countryCode'));
    }

    /**
     * Only the newest translation per product prints, so only it is listed; a
     * newest row with no ZPL is skipped rather than replaced by an older one.
     */
    public function test_list_takes_the_newest_translation_per_product(): void
    {
        $this->travel(1)->minutes();
        $newer = ProductTranslation::create([
            'product_id' => 'p3',
            'product_code' => self::HALLOUMI,
            'label_data' => ['product_name' => 'Halloumi, new wording'],
            'label_size' => 'small',
            'zpl_content' => '^XA^FDNew^FS^PQ1^XZ',
            'auto_print' => false,
        ]);

        $translated = collect($this->actingAs($this->employee())->getJson(route('labels.zebra-list'))->json('labels'))
            ->where('kind', 'translated')
            ->values();

        $this->assertCount(1, $translated);
        $this->assertSame($newer->id, $translated[0]['id']);
        $this->assertSame(56, $translated[0]['width_mm']);

        $this->travel(1)->minutes();
        ProductTranslation::create([
            'product_id' => 'p3',
            'product_code' => self::HALLOUMI,
            'label_data' => ['product_name' => 'Halloumi, draft'],
            'label_size' => 'small',
            'zpl_content' => null,
        ]);

        $translated = collect($this->actingAs($this->employee())->getJson(route('labels.zebra-list'))->json('labels'))
            ->where('kind', 'translated');

        $this->assertCount(0, $translated);
    }

    /**
     * The office page and the Shop list are fed by one service.
     */
    public function test_office_page_and_list_agree(): void
    {
        $this->actingAs($this->manager())
            ->get(route('labels.zebra'))
            ->assertOk()
            ->assertSee('Kale Curly 300g')
            ->assertSee('Rocket_Mossfield')
            ->assertSee('parsnip');
    }

    public function test_print_with_till_price_overrides_this_print_only(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('zebra-labels.print', $this->kale), ['copies' => 2, 'fields' => [1 => '\\152.79']])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(1, $this->printed);
        $this->assertStringContainsString('\\152.79', $this->printed[0]);
        $this->assertStringContainsString('^PQ2', $this->printed[0]);
        $this->assertStringNotContainsString('\\152.49', $this->printed[0]);

        // The saved label is fixed in the office, never from the shop floor.
        $this->assertSame(self::KALE_ZPL, $this->kale->fresh()->zpl_content);
    }

    public function test_print_without_override_sends_the_saved_label(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('zebra-labels.print', $this->kale), ['copies' => 1])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(1, $this->printed);
        $this->assertStringContainsString('\\152.49', $this->printed[0]);
        $this->assertStringContainsString('^PQ1', $this->printed[0]);
    }

    public function test_translated_print_sends_the_translation_with_copies(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('labels.translate.print', $this->halloumi), ['copies' => 3])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(1, $this->printed);
        $this->assertStringContainsString('^FDHalloumi^FS', $this->printed[0]);
        $this->assertStringContainsString('^PQ3', $this->printed[0]);
    }

    /**
     * shelfLabels() now reads its history through LabelQueueService.
     */
    public function test_office_shelf_page_still_renders(): void
    {
        \App\Models\LabelLog::logLabelPrint(self::ROCKET);

        $this->actingAs($this->manager())
            ->get(route('labels.shelf-labels'))
            ->assertOk()
            ->assertSee('Rocket Mossfield 100g');
    }
}
