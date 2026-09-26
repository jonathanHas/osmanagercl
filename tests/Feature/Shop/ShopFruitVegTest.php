<?php

namespace Tests\Feature\Shop;

use App\Models\Harvest;
use App\Models\HarvestProductUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteLog;
use App\Models\ZebraLabel;
use App\Services\ZebraPrintService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shop mode fruit & veg: the waste log and the harvest log.
 *
 * The office endpoints do the work, so what is tested here is the Shop's side of
 * the contract — who may open the screens, what the two new JSON reads return, and
 * the two opposite write semantics (waste replaces a day's row, harvest adds to
 * it). The employee in these tests holds `fruit_veg.operate` and nothing else,
 * because that is the user the owner reported could not reach these screens.
 */
class ShopFruitVegTest extends TestCase
{
    use RefreshDatabase;

    private const JON = 'J1';

    /** Stand-in for a product photo; the rows endpoints only hash it and test for null. */
    private const BLOB = "\xFF\xD8\xFFfake-jpeg";

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('suppliers.jon', self::JON);

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
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->string('TAXCAT')->nullable();
            $table->binary('IMAGE')->nullable();
        });

        // buildRows() eager-loads the category relation.
        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        // TillVisibilityService reads PRODUCTS.ID from here, not CODE.
        $pos->create('PRODUCTS_CAT', function (Blueprint $table) {
            $table->string('PRODUCT')->primary();
        });

        $pos->create('vegDetails', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('product')->nullable();
            $table->integer('countryCode')->nullable();
            $table->string('classId')->nullable();
            $table->string('unitId')->nullable();
        });

        $pos->create('TAXCATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 8, 6)->default(0);
        });

        $pos->create('supplier_link', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
        });

        // WasteLog, Harvest and HarvestProductUnit are pinned to 'mysql'.
        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('mysql');

        $main = DB::connection('mysql')->getSchemaBuilder();

        $main->create('fv_waste_logs', function (Blueprint $table) {
            $table->id();
            $table->date('waste_date');
            $table->string('product_code');
            $table->string('product_name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('kg');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('value', 10, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['waste_date', 'product_code']);
        });

        $main->create('harvests', function (Blueprint $table) {
            $table->id();
            $table->date('harvest_date');
            $table->string('product_code');
            $table->string('product_name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['harvest_date', 'product_code']);
        });

        $main->create('harvest_product_units', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('unit', 20)->default('kg');
            $table->timestamps();
        });
    }

    /** What the rows endpoints should build for a product that has a photo. */
    private function expectedImageUrl(string $code): string
    {
        return route('fruit-veg.product-image', [
            'code' => $code,
            'w' => 112,
            'v' => substr(md5(self::BLOB), 0, 8),
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions, string $name = 'Maya Jensen'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'module' => 'Fruit & Veg']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => $name]);
    }

    private function product(string $code, string $name, bool $onTill = false, string $category = 'SUB1', bool $withImage = false): void
    {
        $id = 'P-'.$code;

        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => $id,
            'NAME' => $name,
            'CODE' => $code,
            'CATEGORY' => $category,
            'PRICESELL' => 4.00,
            // Any bytes will do: the rows endpoints only ask whether the blob is
            // null, and the image route itself is not exercised here.
            'IMAGE' => $withImage ? self::BLOB : null,
        ]);

        if ($onTill) {
            DB::connection('pos')->table('PRODUCTS_CAT')->insert(['PRODUCT' => $id]);
        }
    }

    /**
     * An active Zebra label for a product. The ZPL carries its own ^PW/^LL so
     * labelPayload() reports real dimensions rather than the stored fallbacks.
     */
    private function label(string $code, string $name = 'Mossfield salad'): ZebraLabel
    {
        return ZebraLabel::create([
            'name' => $name,
            'product_code' => $code,
            'zpl_content' => "^XA^PW900^LL600^FT30,60^A0N,28,28^FD{$name}^FS^PQ1^XZ",
            'label_width_mm' => 112.6,
            'label_height_mm' => 75.1,
            'default_copies' => 1,
            'is_active' => true,
        ]);
    }

    private function jonsProduct(string $code, string $name, bool $withImage = false): void
    {
        $this->product($code, $name, withImage: $withImage);

        DB::connection('pos')->table('supplier_link')->insert([
            'Barcode' => $code,
            'SupplierCode' => 'JON-'.$code,
            'SupplierID' => self::JON,
        ]);
    }

    public function test_employee_can_open_both_screens(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);

        $this->actingAs($user)->get('/shop/fv/waste')
            ->assertOk()
            ->assertSee('data-shell="shop"', false)
            // The screen title carries an ampersand; a literal `&amp;` in the
            // title prop renders as `&amp;amp;` because Blade escapes it again.
            ->assertSee('<h1 class="shop-topbar__title">Fruit &amp; veg</h1>', false)
            ->assertDontSee('&amp;amp;', false)
            ->assertSee('data-rows-url="'.route('fruit-veg.waste.rows').'"', false)
            ->assertSee('data-entry-url="'.route('fruit-veg.waste.entry').'"', false)
            ->assertSee('class="shop-choice shop-choice--pic"', false)
            ->assertSee('class="shop-thumb"', false)
            ->assertSee('#carrot', false)
            ->assertSee('href="'.route('shop.fv.harvest').'"', false)
            // The nav marks the page you are on, not the other one.
            ->assertSee('href="'.route('shop.fv.waste').'" aria-current="page"', false);

        $this->actingAs($user)->get('/shop/fv/harvest')
            ->assertOk()
            ->assertSee('data-rows-url="'.route('fruit-veg.harvest.rows').'"', false)
            ->assertSee('data-save-url="'.route('fruit-veg.harvest.save-row').'"', false)
            ->assertSee('href="'.route('shop.fv.harvest').'" aria-current="page"', false)
            // Cycle 17b: the list is recent picks, not Jon's whole range, and the
            // screen has to say which it is — including when there is no history.
            ->assertSee('Recent picks · search to add anything else', false)
            ->assertSee('Nothing harvested recently', false)
            ->assertSee('No produce matches', false)
            ->assertSee('Search all your produce', false)
            // Cycle 17c: picture tiles, with a produce placeholder rather than the
            // shared component's default box.
            ->assertSee('class="shop-choice shop-choice--pic"', false)
            ->assertSee('class="shop-thumb"', false)
            ->assertSee('#carrot', false)
            ->assertDontSee('#package', false)
            // Cycle 17f: the unit switch follows the day's row, and says so.
            ->assertSee('lockedUnit', false)
            ->assertSee("remove today's entry on the office page", false)
            // Cycle 19: print after a log, and reprint from a Today row.
            ->assertSee('data-print-url-template="'.route('zebra-labels.print', ['zebraLabel' => '__ID__']).'"', false)
            ->assertSee('Print labels', false)
            ->assertSee('Check the printer has', false)
            ->assertSee('aria-label="Print labels"', false);
    }

    public function test_barista_is_forbidden(): void
    {
        $user = $this->userWith('barista', ['coffee.kds']);

        $this->actingAs($user)->get('/shop/fv/waste')->assertForbidden();
        $this->actingAs($user)->get('/shop/fv/harvest')->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/shop/fv/waste')->assertRedirect('/login');
        $this->get('/shop/fv/harvest')->assertRedirect('/login');
    }

    public function test_home_tile_opens_the_waste_log_for_operate_only_users(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertSee('href="'.route('shop.fv.waste').'"', false)
            ->assertDontSee('href="'.route('fruit-veg.availability').'"', false);

        // The permission the tile used to require is not the one it needs now.
        $other = $this->userWith('stocker', ['stocking.scan']);

        $this->actingAs($other)->get('/shop')
            ->assertOk()
            ->assertDontSee('href="'.route('shop.fv.waste').'"', false);
    }

    public function test_waste_rows_lists_on_till_products_with_todays_entry(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);
        $this->product('1001', 'Bananas', onTill: true, withImage: true);
        $this->product('1002', 'Kohlrabi', onTill: false);

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => now()->toDateString(), 'product_code' => '1001', 'quantity' => 2, 'unit' => 'kg',
        ])->assertOk();

        $rows = $this->actingAs($user)->getJson(route('fruit-veg.waste.rows'))
            ->assertOk()
            ->assertJsonPath('date', now()->toDateString())
            ->json('products');

        $this->assertSame(['1001'], collect($rows)->pluck('code')->all());
        $this->assertEquals(2, $rows[0]['quantity']);
        $this->assertSame('kg', $rows[0]['unit']);
        $this->assertSame('kg', $rows[0]['priced_unit']);
        $this->assertTrue($rows[0]['on_till']);
        $this->assertSame($this->expectedImageUrl('1001'), $rows[0]['image_url']);

        // An off-till product joins the list once it has been logged that day.
        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => now()->toDateString(), 'product_code' => '1002', 'quantity' => 1, 'unit' => 'kg',
        ])->assertOk();

        $rows = collect($this->actingAs($user)->getJson(route('fruit-veg.waste.rows'))->json('products'))
            ->keyBy('code');

        $this->assertCount(2, $rows);
        $this->assertFalse($rows['1002']['on_till']);
        // No blob, so no URL — the client shows its placeholder rather than
        // requesting the route's 1x1 transparent PNG.
        $this->assertNull($rows['1002']['image_url']);
    }

    public function test_waste_entry_replaces_and_zero_deletes(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);
        $this->product('1001', 'Bananas', onTill: true);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => $date, 'product_code' => '1001', 'quantity' => 2, 'unit' => 'kg',
        ])->assertOk();

        // The Shop screen adds client-side and sends the total; the row is replaced.
        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => $date, 'product_code' => '1001', 'quantity' => 3.5, 'unit' => 'kg',
        ])->assertOk()->assertJson(['saved' => true, 'quantity' => 3.5]);

        $this->assertSame(1, WasteLog::count());
        $this->assertEquals(3.5, (float) WasteLog::first()->quantity);

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => $date, 'product_code' => '1001', 'quantity' => 0, 'unit' => 'kg',
        ])->assertOk()->assertJson(['deleted' => true]);

        $this->assertSame(0, WasteLog::count());
    }

    public function test_harvest_rows_lists_recent_and_available_jon_products(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate'], 'Ben Doyle');
        $this->jonsProduct('2001', 'Spinach');
        $this->jonsProduct('2002', 'Salad mix', withImage: true);
        $this->jonsProduct('2003', 'Radish', withImage: true);
        $label = $this->label('2002');
        $this->product('9999', 'Somebody else\'s carrots', onTill: true);

        Harvest::create([
            'harvest_date' => now()->subDays(5)->toDateString(),
            'product_code' => '2001', 'product_name' => 'Spinach',
            'quantity' => 1, 'unit' => 'kg',
        ]);

        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => now()->toDateString(), 'code' => '2002', 'amount' => 3.2, 'unit' => 'kg',
        ])->assertOk();

        $payload = $this->actingAs($user)->getJson(route('fruit-veg.harvest.rows'))->assertOk()->json();

        $rows = collect($payload['rows'])->keyBy('code');
        $this->assertEqualsCanonicalizing(['2001', '2002'], $rows->keys()->all());
        $this->assertEquals(0, $rows['2001']['logged']);
        $this->assertEquals(3.2, $rows['2002']['logged']);
        // The unit today's row is in, as opposed to the product's preference.
        $this->assertNull($rows['2001']['logged_unit']);
        $this->assertSame('kg', $rows['2002']['logged_unit']);
        $this->assertSame('Ben Doyle', $rows['2002']['by']);
        $this->assertNotNull($rows['2002']['updated_at']);
        $this->assertNull($rows['2001']['by']);

        $this->assertSame($this->expectedImageUrl('2002'), $rows['2002']['image_url']);

        // Cycle 19: the Shop screen offers a print for a product that has a label.
        $this->assertSame($label->id, $rows['2002']['label']['id']);
        $this->assertSame('Mossfield salad', $rows['2002']['label']['name']);
        $this->assertNull($rows['2001']['label']);
        $this->assertNull($rows['2001']['image_url']);

        // Not keyBy('code'): the codes are numeric strings, and Collection turns
        // those into integer keys, so the identity assertion below would fail on
        // 2003 vs '2003' rather than on anything real.
        $available = collect($payload['available']);
        $this->assertSame(['2003'], $available->pluck('code')->all());
        $this->assertSame(
            $this->expectedImageUrl('2003'),
            $available->firstWhere('code', '2003')['image_url']
        );

        // An available product without a label carries null, so the screen offers
        // nothing rather than rendering a print card it cannot use.
        $this->assertNull($available->firstWhere('code', '2003')['label']);

        // A product that is not Jon's is neither a row nor available.
        $this->assertNotContains('9999', $rows->keys()->all());
        $this->assertNotContains('9999', $available->pluck('code')->all());
    }

    public function test_harvest_save_row_accumulates(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);
        $this->jonsProduct('2002', 'Salad mix');
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 4.2, 'unit' => 'kg',
        ])->assertOk()->assertJson(['success' => true, 'logged' => 4.2]);

        // Unlike waste, a second save adds rather than replaces.
        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 1, 'unit' => 'kg',
        ])->assertOk()->assertJson(['success' => true, 'logged' => 5.2]);

        $this->assertSame(1, Harvest::count());
        $this->assertEquals(5.2, (float) Harvest::first()->quantity);

        // The unit is remembered per product for next time.
        $this->assertSame('kg', HarvestProductUnit::where('product_code', '2002')->value('unit'));
    }

    public function test_harvest_refuses_a_second_unit_on_the_same_day(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);
        $this->jonsProduct('2002', 'Salad mix');
        $this->jonsProduct('2004', 'Radish');
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 5.2, 'unit' => 'kg',
        ])->assertOk();

        // There is one quantity column and saves accumulate, so adding a count to
        // a weight used to produce "7.2 unit". Refused now, and told why.
        $response = $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 2, 'unit' => 'unit',
        ])->assertStatus(422);

        $response->assertJson(['success' => false, 'logged' => 5.2, 'unit' => 'kg']);
        $this->assertStringContainsString('Already logged 5.2 kg of Salad mix today', $response->json('message'));
        $this->assertStringContainsString("remove today's entry on the office harvest page", $response->json('message'));

        // Nothing moved: not the row, and not the remembered preference.
        $this->assertSame(1, Harvest::count());
        $this->assertEquals(5.2, (float) Harvest::first()->quantity);
        $this->assertSame('kg', Harvest::first()->unit);
        $this->assertSame('kg', HarvestProductUnit::where('product_code', '2002')->value('unit'));

        // The same unit still accumulates.
        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 1, 'unit' => 'kg',
        ])->assertOk()->assertJson(['logged' => 6.2]);

        // A product with no row today takes either unit, and remembers it.
        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2004', 'amount' => 3, 'unit' => 'unit',
        ])->assertOk()->assertJson(['success' => true, 'logged' => 3]);

        $this->assertSame('unit', HarvestProductUnit::where('product_code', '2004')->value('unit'));

        $rows = collect($this->actingAs($user)->getJson(route('fruit-veg.harvest.rows'))->json('rows'));
        $this->assertSame('unit', $rows->firstWhere('code', '2004')['logged_unit']);
        $this->assertSame('kg', $rows->firstWhere('code', '2002')['logged_unit']);
    }

    public function test_harvest_refusal_message_reads_without_trailing_zeros(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);
        $this->jonsProduct('2002', 'Salad mix');
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 3, 'unit' => 'unit',
        ])->assertOk();

        $message = $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => $date, 'code' => '2002', 'amount' => 1, 'unit' => 'kg',
        ])->assertStatus(422)->json('message');

        // "3 units", not "3.00 unit" — the message is read on a shop floor.
        $this->assertStringContainsString('Already logged 3 units of Salad mix today', $message);
        $this->assertStringContainsString('Log in units', $message);
    }

    public function test_harvest_refuses_a_product_that_is_not_jons(): void
    {
        $user = $this->userWith('employee', ['fruit_veg.operate']);
        $this->product('9999', 'Not Jon\'s', onTill: true);

        $this->actingAs($user)->postJson(route('fruit-veg.harvest.save-row'), [
            'date' => now()->toDateString(), 'code' => '9999', 'amount' => 1, 'unit' => 'kg',
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, Harvest::count());
    }

    public function test_harvest_print_uses_the_zebra_endpoint(): void
    {
        $captured = [];

        // A printer that records the ZPL instead of shelling out to lp.
        $this->app->bind(ZebraPrintService::class, function () use (&$captured) {
            return (new ZebraPrintService('printer.test', '631', 'TEST-PRINTER', 1))
                ->usingRunner(function (string $command) use (&$captured) {
                    if (preg_match("/-o raw '([^']+)'/", $command, $m) && is_file($m[1])) {
                        $captured[] = file_get_contents($m[1]);
                    }

                    return 'request id is TEST-PRINTER-1 (1 file(s))';
                });
        });

        $user = $this->userWith('employee', ['fruit_veg.operate', 'labels.print']);
        $this->jonsProduct('2002', 'Salad mix');
        $label = $this->label('2002');

        $this->actingAs($user)
            ->postJson(route('zebra-labels.print', $label), ['copies' => 3])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Print job sent (3 copies)']);

        // The controller rewrites ^PQ rather than repeating the ZPL, so three
        // copies is one job asking the printer for three.
        $this->assertCount(1, $captured);
        $this->assertStringContainsString('^PQ3', $captured[0]);
        $this->assertStringContainsString('Mossfield salad', $captured[0]);
    }

    public function test_printing_needs_the_labels_permission(): void
    {
        $this->jonsProduct('2002', 'Salad mix');
        $label = $this->label('2002');

        $barista = $this->userWith('barista', ['coffee.kds']);

        $this->actingAs($barista)
            ->postJson(route('zebra-labels.print', $label), ['copies' => 1])
            ->assertForbidden();
    }

    public function test_office_pages_still_render(): void
    {
        $user = $this->userWith('manager', ['fruit_veg.operate', 'fruit_veg.manage']);
        $this->product('1001', 'Bananas', onTill: true);
        $this->jonsProduct('2001', 'Spinach');

        $this->actingAs($user)->get(route('fruit-veg.waste'))->assertOk();
        $this->actingAs($user)->get(route('fruit-veg.harvest'))->assertOk();
    }
}
