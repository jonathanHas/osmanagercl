<?php

namespace Tests\Feature;

use App\Models\CoffeeProductMetadata;
use App\Models\KdsOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modifier badges: the badge_kind field on coffee_product_metadata, how it
 * reaches the /kds card through KdsOrder::card_items, and the two render paths
 * (the Blade component and the JS renderer's shared fallback contract).
 */
class KdsModifierBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CoffeeMetadataController::index() lists POS products that have no
        // metadata yet, so /coffee/metadata needs a PRODUCTS table.
        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        DB::connection('pos')->getSchemaBuilder()->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('DISPLAY')->nullable();
            $table->string('CATEGORY')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            // Category 081 is the coffee category the controller filters on.
            ['ID' => 'POS_NEW', 'NAME' => 'Flat White', 'DISPLAY' => '<html>Flat<br>White', 'CATEGORY' => '081'],
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Role::hasPermission() has no admin bypass, so kds.access is granted
     * explicitly.
     */
    private function barista(): User
    {
        $role = Role::firstOrCreate(['name' => 'barista'], ['display_name' => 'Barista']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'kds.access'],
            ['display_name' => 'Access KDS', 'module' => 'KDS']
        ));

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A Latte with a native POS modifier, a badged option (Oat) and an
     * unbadged one (Takeaway). kds_order_items.kind defaults to 'drink', which
     * is what lets the option lines fold into the Latte.
     *
     * @return array{order: KdsOrder, oat: CoffeeProductMetadata}
     */
    private function seedOrder(): array
    {
        $latte = CoffeeProductMetadata::create([
            'product_id' => 'L', 'product_name' => 'Latte', 'type' => 'coffee',
            'short_name' => 'Latte', 'display_order' => 1,
        ]);
        $oat = CoffeeProductMetadata::create([
            'product_id' => 'O', 'product_name' => 'Milk Alternative OAT', 'type' => 'option',
            'short_name' => 'Oat', 'group_name' => 'Milk', 'badge_kind' => 'oat', 'display_order' => 1,
        ]);
        $takeaway = CoffeeProductMetadata::create([
            'product_id' => 'T', 'product_name' => 'Take Away', 'type' => 'option',
            'short_name' => 'Takeaway', 'group_name' => 'Service', 'badge_kind' => null, 'display_order' => 2,
        ]);

        $order = KdsOrder::create([
            'ticket_id' => 'T1', 'ticket_number' => 9001, 'person' => 'TEST',
            'status' => 'new', 'order_time' => now(),
        ]);

        $order->items()->create([
            'product_id' => $latte->product_id, 'product_name' => 'Latte',
            'display_name' => 'Latte', 'quantity' => 1, 'modifiers' => ['milk' => 'Whole'],
        ]);
        foreach ([$oat, $takeaway] as $option) {
            $order->items()->create([
                'product_id' => $option->product_id, 'product_name' => $option->product_name,
                'display_name' => $option->product_name, 'quantity' => 1,
            ]);
        }

        return ['order' => $order->fresh('items'), 'oat' => $oat];
    }

    public function test_badge_kind_is_validated_on_store_and_update(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/coffee/metadata', [
            'product_id' => 'POS_NEW', 'product_name' => 'Flat White', 'short_name' => 'Oat',
            'type' => 'option', 'group_name' => 'Milk', 'badge_kind' => 'oat', 'display_order' => 1,
        ])->assertOk();

        $row = CoffeeProductMetadata::where('product_id', 'POS_NEW')->firstOrFail();
        $this->assertSame('oat', $row->badge_kind);

        $this->postJson('/coffee/metadata', [
            'product_id' => 'POS_OTHER', 'product_name' => 'Mocha', 'short_name' => 'Mocha',
            'type' => 'option', 'group_name' => 'Milk', 'badge_kind' => 'purple', 'display_order' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('badge_kind');

        $this->putJson("/coffee/metadata/{$row->id}", [
            'short_name' => 'Oat', 'type' => 'option', 'group_name' => 'Milk',
            'badge_kind' => null, 'display_order' => 1, 'is_active' => true,
        ])->assertOk();

        $this->assertNull($row->fresh()->badge_kind);
    }

    public function test_card_items_carry_badge_kind_for_folded_options(): void
    {
        $order = $this->seedOrder()['order'];
        $cardItems = $order->card_items;

        $this->assertCount(1, $cardItems);
        $this->assertSame([
            ['label' => 'Whole', 'kind' => null],
            ['label' => 'Oat', 'kind' => 'oat'],
            ['label' => 'Takeaway', 'kind' => null],
        ], $cardItems[0]['modifiers']);
    }

    public function test_kds_page_renders_badge_and_plain_chip(): void
    {
        $this->seedOrder();
        $this->actingAs($this->barista());

        $response = $this->get('/kds');
        $response->assertOk()
            ->assertSee('mb--oat', false)
            ->assertSee('mb--f-milk', false)
            ->assertSee('#mb-shape-milk', false)
            ->assertSee('<span class="item__mod">Takeaway</span>', false);

        $this->getJson('/kds/orders')
            ->assertOk()
            ->assertJsonPath('active.0.items.0.modifiers.1.kind', 'oat')
            ->assertJsonPath('active.0.items.0.modifiers.1.label', 'Oat')
            ->assertJsonPath('active.0.items.0.modifiers.2.kind', null);
    }

    public function test_metadata_page_shows_badge_picker(): void
    {
        $oat = $this->seedOrder()['oat'];
        $this->actingAs($this->admin());

        $this->get('/coffee/metadata')
            ->assertOk()
            ->assertSee("badge_kind_{$oat->id}", false)
            ->assertSee('<option value="oat" selected', false)
            ->assertSee('Plain chip', false)
            // The POS product with no metadata yet is still offered.
            ->assertSee('Flat White');
    }

    public function test_component_falls_back_to_plain_chip_for_unknown_kind(): void
    {
        $plain = Blade::render('<x-kds.modifier-badge kind="nope" label="Takeaway" />');
        $this->assertStringContainsString('class="item__mod"', $plain);
        $this->assertStringNotContainsString('class="mb', $plain);

        $badge = Blade::render('<x-kds.modifier-badge kind="decaf" label="Decaf" />');
        $this->assertStringContainsString('mb--f-shot', $badge);
        $this->assertStringContainsString('mb--decaf', $badge);
        $this->assertStringContainsString('#mb-shape-shot', $badge);
        $this->assertStringContainsString('>Decaf<', $badge);
    }
}
