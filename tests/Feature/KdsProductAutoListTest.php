<?php

namespace Tests\Feature;

use App\Models\CoffeeProductMetadata;
use App\Models\KdsProduct;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Creating coffee metadata must also put the product on the kds_products
 * allow-list, because the KDS importers drop any ticket line whose product
 * is not listed there. This is what left almond milk off the KDS in
 * production: metadata existed, the allow-list row did not.
 */
class KdsProductAutoListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('DISPLAY')->nullable();
            $table->string('CATEGORY')->nullable();
        });
        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        DB::connection('pos')->table('CATEGORIES')->insert(['ID' => '081', 'NAME' => 'Coffee Fresh']);
        DB::connection('pos')->table('PRODUCTS')->insert([
            ['ID' => 'ALMOND', 'NAME' => 'Milk Alternative Almond', 'DISPLAY' => '<html>Milk<br>Alternative<br>Almond', 'CATEGORY' => '081'],
            ['ID' => 'COCONUT', 'NAME' => 'Milk Alternative Coconut', 'DISPLAY' => null, 'CATEGORY' => '081'],
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_storing_metadata_adds_the_product_to_the_kds_allow_list(): void
    {
        $this->assertDatabaseMissing('kds_products', ['product_id' => 'ALMOND']);

        $this->actingAs($this->admin())
            ->postJson('/coffee/metadata', [
                'product_id' => 'ALMOND',
                'product_name' => 'Milk Alternative Almond',
                'short_name' => 'Almond',
                'type' => 'option',
                'group_name' => 'Milk',
                'display_order' => 999,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('kds_products', [
            'product_id' => 'ALMOND',
            'product_name' => 'Milk Alternative Almond',
            'category_id' => '081',
            'category_name' => 'Coffee Fresh',
            'is_active' => 1,
            'trigger_mode' => 'primary',
        ]);
    }

    public function test_creating_metadata_reactivates_a_switched_off_allow_list_row(): void
    {
        KdsProduct::create([
            'product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut',
            'category_id' => '081', 'category_name' => 'Coffee Fresh',
            'is_active' => false, 'trigger_mode' => 'companion', 'notes' => 'kept',
        ]);

        CoffeeProductMetadata::create([
            'product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut',
            'type' => 'option', 'short_name' => 'Coconut', 'group_name' => 'Milk', 'display_order' => 1,
        ]);

        $row = KdsProduct::where('product_id', 'COCONUT')->sole();
        $this->assertTrue($row->is_active);
        // Only the switch is flipped; the operator's other choices survive.
        $this->assertSame('companion', $row->trigger_mode);
        $this->assertSame('kept', $row->notes);
        $this->assertSame(1, KdsProduct::count());
    }

    public function test_metadata_for_a_product_missing_from_pos_does_not_create_an_allow_list_row(): void
    {
        // addSpecificSyrups() creates metadata with synthetic ids that no ticket line can match.
        CoffeeProductMetadata::create([
            'product_id' => 'SYRUP_VANILLA', 'product_name' => 'Vanilla Syrup',
            'type' => 'option', 'short_name' => 'Van', 'group_name' => 'Syrups', 'display_order' => 1,
        ]);

        $this->assertDatabaseCount('kds_products', 0);
        $this->assertDatabaseHas('coffee_product_metadata', ['product_id' => 'SYRUP_VANILLA']);
    }

    public function test_metadata_page_flags_products_missing_from_the_kds_list(): void
    {
        CoffeeProductMetadata::withoutEvents(function () {
            CoffeeProductMetadata::create([
                'product_id' => 'ALMOND', 'product_name' => 'Milk Alternative Almond',
                'type' => 'option', 'short_name' => 'Almond', 'group_name' => 'Milk', 'display_order' => 1,
            ]);
            CoffeeProductMetadata::create([
                'product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut',
                'type' => 'option', 'short_name' => 'Coconut', 'group_name' => 'Milk', 'display_order' => 2,
            ]);
            CoffeeProductMetadata::create([
                'product_id' => 'SYRUP_VANILLA', 'product_name' => 'Vanilla Syrup',
                'type' => 'option', 'short_name' => 'Van', 'group_name' => 'Syrups', 'display_order' => 3,
            ]);
        });
        KdsProduct::create(['product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut', 'is_active' => true, 'trigger_mode' => 'primary']);

        $response = $this->actingAs($this->admin())->get('/coffee/metadata')->assertOk();

        $response->assertSee('1 product will not appear on the KDS');
        $response->assertSee('id="kds-unlisted-banner"', false);
        // Almond is a real POS product with no allow-list row: flagged with a fix button.
        $response->assertSee('Not on KDS');
        $response->assertSee('listOnKds('.CoffeeProductMetadata::where('product_id', 'ALMOND')->value('id').')', false);
        // Synthetic ids are labelled, not counted as fixable.
        $response->assertSee('Not in POS');
    }

    public function test_metadata_page_has_no_banner_when_everything_is_listed(): void
    {
        CoffeeProductMetadata::create([
            'product_id' => 'ALMOND', 'product_name' => 'Milk Alternative Almond',
            'type' => 'option', 'short_name' => 'Almond', 'group_name' => 'Milk', 'display_order' => 1,
        ]);

        $this->actingAs($this->admin())->get('/coffee/metadata')
            ->assertOk()
            ->assertDontSee('id="kds-unlisted-banner"', false)
            ->assertDontSee('Not on KDS');
    }

    public function test_list_on_kds_endpoint_adds_one_product(): void
    {
        $almond = CoffeeProductMetadata::withoutEvents(fn () => CoffeeProductMetadata::create([
            'product_id' => 'ALMOND', 'product_name' => 'Milk Alternative Almond',
            'type' => 'option', 'short_name' => 'Almond', 'group_name' => 'Milk', 'display_order' => 1,
        ]));
        $syrup = CoffeeProductMetadata::withoutEvents(fn () => CoffeeProductMetadata::create([
            'product_id' => 'SYRUP_VANILLA', 'product_name' => 'Vanilla Syrup',
            'type' => 'option', 'short_name' => 'Van', 'group_name' => 'Syrups', 'display_order' => 2,
        ]));

        $this->actingAs($this->admin())
            ->postJson("/coffee/metadata/{$almond->id}/list-on-kds")
            ->assertOk()
            ->assertJson(['success' => true, 'action' => 'created']);
        $this->assertDatabaseHas('kds_products', ['product_id' => 'ALMOND', 'is_active' => 1]);

        $this->actingAs($this->admin())
            ->postJson("/coffee/metadata/{$syrup->id}/list-on-kds")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertDatabaseMissing('kds_products', ['product_id' => 'SYRUP_VANILLA']);
    }

    public function test_sync_kds_endpoint_adds_every_missing_product(): void
    {
        CoffeeProductMetadata::withoutEvents(function () {
            CoffeeProductMetadata::create([
                'product_id' => 'ALMOND', 'product_name' => 'Milk Alternative Almond',
                'type' => 'option', 'short_name' => 'Almond', 'group_name' => 'Milk', 'display_order' => 1,
            ]);
            CoffeeProductMetadata::create([
                'product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut',
                'type' => 'option', 'short_name' => 'Coconut', 'group_name' => 'Milk', 'display_order' => 2,
            ]);
        });
        KdsProduct::create(['product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut', 'is_active' => false, 'trigger_mode' => 'primary']);

        $this->actingAs($this->admin())
            ->postJson('/coffee/metadata/sync-kds')
            ->assertOk()
            ->assertJson(['success' => true, 'counts' => ['created' => 1, 'reactivated' => 1]]);

        $this->assertSame(2, KdsProduct::active()->count());
    }

    public function test_sync_command_backfills_existing_metadata(): void
    {
        // Seed metadata without triggering the hook, as production rows predate it.
        CoffeeProductMetadata::withoutEvents(function () {
            CoffeeProductMetadata::create([
                'product_id' => 'ALMOND', 'product_name' => 'Milk Alternative Almond',
                'type' => 'option', 'short_name' => 'Almond', 'group_name' => 'Milk', 'display_order' => 1,
            ]);
            CoffeeProductMetadata::create([
                'product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut',
                'type' => 'option', 'short_name' => 'Coconut', 'group_name' => 'Milk', 'display_order' => 2,
            ]);
            CoffeeProductMetadata::create([
                'product_id' => 'SYRUP_VANILLA', 'product_name' => 'Vanilla Syrup',
                'type' => 'option', 'short_name' => 'Van', 'group_name' => 'Syrups', 'display_order' => 3,
            ]);
        });
        KdsProduct::create([
            'product_id' => 'COCONUT', 'product_name' => 'Milk Alternative Coconut',
            'is_active' => false, 'trigger_mode' => 'primary',
        ]);

        $this->artisan('kds:sync-products', ['--dry-run' => true])
            ->expectsOutputToContain('created')
            ->assertSuccessful();
        $this->assertDatabaseMissing('kds_products', ['product_id' => 'ALMOND']);
        $this->assertFalse(KdsProduct::where('product_id', 'COCONUT')->sole()->is_active);

        $this->artisan('kds:sync-products')->assertSuccessful();

        $this->assertDatabaseHas('kds_products', ['product_id' => 'ALMOND', 'is_active' => 1]);
        $this->assertTrue(KdsProduct::where('product_id', 'COCONUT')->sole()->is_active);
        $this->assertDatabaseMissing('kds_products', ['product_id' => 'SYRUP_VANILLA']);
    }
}
