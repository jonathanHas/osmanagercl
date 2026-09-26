<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shop mode stock scan: the screen, who may open it, and the two office
 * endpoints it depends on (which had no coverage before this cycle).
 */
class ShopStockScanTest extends TestCase
{
    use RefreshDatabase;

    private const BARCODE = '5000000000017';

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Shop']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    /**
     * In-memory POS tables with one stocked product, enough for lookup and update.
     */
    private function createPosProduct(float $units = 24): void
    {
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
        });

        $pos->create('STOCKCURRENT', function (Blueprint $table) {
            $table->string('PRODUCT');
            $table->decimal('UNITS', 10, 2)->default(0);
            $table->string('LOCATION')->nullable();
            $table->string('ATTRIBUTESETINSTANCE_ID')->nullable();
        });

        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        DB::connection('pos')->table('CATEGORIES')->insert([
            ['ID' => 'c1', 'NAME' => 'Dairy alternatives'],
        ]);

        DB::connection('pos')->table('PRODUCTS')->insert([
            ['ID' => 'p1', 'NAME' => 'Oat drink, barista 1 L', 'CODE' => self::BARCODE, 'CATEGORY' => 'c1', 'PRICESELL' => 2.10, 'TAXCAT' => '001'],
        ]);

        DB::connection('pos')->table('STOCKCURRENT')->insert([
            ['PRODUCT' => 'p1', 'UNITS' => $units, 'LOCATION' => '0', 'ATTRIBUTESETINSTANCE_ID' => null],
        ]);
    }

    public function test_employee_can_open_the_stock_scan_screen(): void
    {
        $user = $this->userWith('employee', ['stocking.scan']);

        $response = $this->actingAs($user)->get('/shop/stock-scan');

        $response->assertOk()
            ->assertSee('data-shell="shop"', false)
            ->assertSee('shop-scan__input', false)
            ->assertSee('data-lookup-url="'.route('stocking.lookup').'"', false)
            ->assertSee('data-update-url="'.route('stocking.update-stock').'"', false)
            ->assertSee('href="'.route('shop.home').'"', false);
    }

    /**
     * The camera fault of 2026-09-26: html5-qrcode sizes its <video> from the
     * element it mounts on, as an inline px width, and the mount was an empty
     * centred grid child, so it measured 0 px and no video ever appeared. The
     * markup half of the fix is the mount class; the half that actually gives the
     * mount a size is a stylesheet rule, so this test asserts on the stylesheet
     * too. That is unusual in a feature test, and it is here because no markup
     * assertion could have caught a fault that was entirely geometry.
     */
    public function test_scan_input_mounts_the_camera_in_a_sized_element(): void
    {
        $user = $this->userWith('employee', ['stocking.scan']);

        $this->actingAs($user)->get('/shop/stock-scan')
            ->assertOk()
            ->assertSee('<div class="shop-scan__mount" :id="cameraId"></div>', false);

        $css = file_get_contents(resource_path('css/shop.css'));
        $additions = substr($css, strpos($css, 'APP ADDITIONS START'));

        $this->assertStringContainsString('.shop-scan__mount { position: absolute !important;', $additions);
    }

    public function test_barista_is_forbidden(): void
    {
        $user = $this->userWith('barista', ['kds.access']);

        $this->actingAs($user)->get('/shop/stock-scan')->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/shop/stock-scan')->assertRedirect('/login');
    }

    public function test_home_tile_links_to_the_stock_scan_screen(): void
    {
        $user = $this->userWith('employee', ['stocking.scan']);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertSee('Stock scan')
            ->assertSee('href="'.route('shop.stock-scan').'"', false);
    }

    public function test_home_tile_is_hidden_without_the_stocking_permission(): void
    {
        $user = $this->userWith('employee', ['products.view']);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertDontSee('Stock scan');
    }

    public function test_lookup_returns_the_product_and_whole_stock(): void
    {
        $this->createPosProduct(24);
        $user = $this->userWith('employee', ['stocking.scan']);

        $response = $this->actingAs($user)->postJson(route('stocking.lookup'), ['barcode' => self::BARCODE]);

        $response->assertOk()
            ->assertJson([
                'found' => true,
                'product' => [
                    'name' => 'Oat drink, barista 1 L',
                    'code' => self::BARCODE,
                    'category' => 'Dairy alternatives',
                ],
                'stock' => 24,
            ]);
    }

    public function test_lookup_reports_an_unknown_barcode(): void
    {
        $this->createPosProduct();
        $user = $this->userWith('employee', ['stocking.scan']);

        $this->actingAs($user)->postJson(route('stocking.lookup'), ['barcode' => '9999999999999'])
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_update_stock_writes_the_pos_row_and_logs_the_adjustment(): void
    {
        $this->createPosProduct(24);
        $user = $this->userWith('employee', ['stocking.scan']);

        $response = $this->actingAs($user)->postJson(route('stocking.update-stock'), [
            'barcode' => self::BARCODE,
            'new_stock' => 30,
        ]);

        $response->assertOk()->assertJson(['success' => true, 'stock' => 30]);

        $this->assertEquals(30, DB::connection('pos')->table('STOCKCURRENT')->where('PRODUCT', 'p1')->value('UNITS'));

        $this->assertDatabaseHas('stock_adjustments', [
            'barcode' => self::BARCODE,
            'product_id' => 'p1',
            'old_stock' => 24,
            'new_stock' => 30,
            'adjustment' => 6,
            'user_id' => $user->id,
            'source' => 'stocking',
        ]);
    }
}
