<?php

namespace Tests\Feature;

use App\Models\BarrelCode;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Models\Role;
use App\Models\User;
use App\Support\PosProductAttributes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * The /deposits office screen (deposit cycle 2).
 */
class DepositScreenTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
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

    private function tier(string $code = '313', float $price = 0.25, bool $charge = true): BarrelCode
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

    private function suggestion(string $productId, BarrelCode $tier, string $code = '8711521947614'): ProductDeposit
    {
        return ProductDeposit::create([
            'product_id' => $productId,
            'product_code' => $code,
            'barrel_code_id' => $tier->id,
            'status' => ProductDeposit::STATUS_SUGGESTED,
            'source' => ProductDeposit::SOURCE_INVOICE,
            'sightings_units' => 54,
            'sightings_count' => 12,
            'last_seen_on' => '2026-07-15',
        ]);
    }

    private function attributes(string $productId): ?string
    {
        return DB::connection('pos')->table('PRODUCTS')->where('ID', $productId)->value('ATTRIBUTES');
    }

    public function test_requires_deliveries_manage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('deposits.index'))->assertForbidden();
        $this->actingAs($user)->post(route('deposits.sync'))->assertForbidden();
    }

    public function test_page_renders_the_three_cards(): void
    {
        $tier = $this->tier();
        $this->tier('7', 15.00, charge: false);
        $this->tier('15', 0.00, charge: false);
        $juice = $this->posProduct('8711521947614', 'Luna e Terra Apple-mango juice 200ml');
        $this->suggestion($juice, $tier);

        $this->actingAs($this->manager())
            ->get(route('deposits.index'))
            ->assertOk()
            ->assertSee('Deposit tiers')
            ->assertSee('Products')
            ->assertSee('Add a product')
            ->assertSee('Luna e Terra Apple-mango juice 200ml')
            ->assertSee('54 units in 12 lines, last 2026-07-15')
            ->assertSee('Confirm all suggested (1)')
            ->assertSee('Statiegeld 7')
            ->assertDontSee('Statiegeld 15');
    }

    public function test_add_card_says_what_was_picked(): void
    {
        $this->tier();

        $this->actingAs($this->manager())
            ->get(route('deposits.index'))
            ->assertOk()
            ->assertSee('Scan or type a barcode, or type part of the name, then choose a result; Enter selects the top match.')
            ->assertSee('x-on:product-search:selected="picked = $event.detail"', false)
            ->assertSee('x-on:product-search:cleared="picked = null"', false)
            ->assertSee('x-text="picked?.name"', false)
            ->assertSee('x-text="picked?.code"', false)
            ->assertSee('x-bind:disabled="!picked"', false);
    }

    public function test_switching_a_tier_on_creates_till_products(): void
    {
        $tier = $this->tier(charge: false);

        $this->actingAs($this->manager())
            ->patch(route('deposits.tiers.update', $tier), ['charge_customer' => '1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tier->refresh();
        $this->assertTrue($tier->charge_customer);
        $this->assertSame(Product::where('CODE', 'DEP-025')->value('ID'), $tier->pos_product_id);
        $this->assertSame(Product::where('CODE', 'DEP-025-RET')->value('ID'), $tier->pos_refund_product_id);
    }

    public function test_switching_a_tier_off_clears_its_products(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice');
        $row = $this->suggestion($juice, $tier);
        $user = $this->manager();
        $this->actingAs($user)->patch(route('deposits.products.update', $row), ['status' => 'confirmed']);
        $this->assertTrue(PosProductAttributes::hasDeposit($this->attributes($juice)));

        $this->actingAs($user)->patch(route('deposits.tiers.update', $tier), ['charge_customer' => '0'])->assertRedirect();

        $this->assertFalse($tier->fresh()->charge_customer);
        $this->assertNull($this->attributes($juice));
    }

    public function test_confirm_writes_attributes_and_reject_clears_them(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Juice');
        $row = $this->suggestion($juice, $tier);
        $user = $this->manager();

        $this->actingAs($user)
            ->patch(route('deposits.products.update', $row), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame('confirmed', $row->status);
        $this->assertSame($user->id, $row->confirmed_by);
        $this->assertNotNull($row->confirmed_at);
        $this->assertNotNull($row->pos_synced_at);
        $this->assertSame([
            'deposit.id' => $tier->fresh()->pos_product_id,
            'deposit.name' => 'Bottle deposit 0.25',
            'deposit.price' => '0.25',
        ], PosProductAttributes::parse($this->attributes($juice)));

        $this->actingAs($user)->patch(route('deposits.products.update', $row), ['status' => 'rejected'])->assertRedirect();

        $this->assertSame('rejected', $row->fresh()->status);
        $this->assertNull($row->fresh()->confirmed_by);
        $this->assertNull($this->attributes($juice));
    }

    public function test_changing_the_tier_rewrites_the_deposit(): void
    {
        $t313 = $this->tier();
        $t315 = $this->tier('315', 0.70);
        $juice = $this->posProduct('8711521947614', 'Juice');
        $row = $this->suggestion($juice, $t313);
        $user = $this->manager();
        $this->actingAs($user)->patch(route('deposits.products.update', $row), ['status' => 'confirmed']);

        $this->actingAs($user)->patch(route('deposits.products.update', $row), ['barrel_code_id' => $t315->id])->assertRedirect();

        $this->assertSame($t315->id, $row->fresh()->barrel_code_id);
        $this->assertSame('confirmed', $row->fresh()->status);
        $this->assertSame('0.70', PosProductAttributes::parse($this->attributes($juice))['deposit.price']);
    }

    public function test_manual_add_confirms_and_writes_then_remove_clears_and_deletes(): void
    {
        $tier = $this->tier();
        $milk = $this->posProduct('8714728001004', 'Ommelanden Buttermilk 1l');
        $user = $this->manager();

        $this->actingAs($user)
            ->post(route('deposits.products.store'), ['product_id' => $milk, 'barrel_code_id' => $tier->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = ProductDeposit::where('product_id', $milk)->sole();
        $this->assertSame('confirmed', $row->status);
        $this->assertSame('manual', $row->source);
        $this->assertSame('8714728001004', $row->product_code);
        $this->assertSame($user->id, $row->confirmed_by);
        $this->assertTrue(PosProductAttributes::hasDeposit($this->attributes($milk)));

        $this->actingAs($user)->delete(route('deposits.products.destroy', $row))->assertRedirect();

        $this->assertSame(0, ProductDeposit::count());
        $this->assertNull($this->attributes($milk));
    }

    public function test_manual_add_validates_product_and_tier(): void
    {
        $off = $this->tier('10046', 0.15, charge: false);
        $milk = $this->posProduct('8714728001004', 'Buttermilk');

        $this->actingAs($this->manager())
            ->post(route('deposits.products.store'), ['product_id' => 'nope', 'barrel_code_id' => $off->id])
            ->assertSessionHasErrors(['product_id', 'barrel_code_id']);

        $this->assertSame(0, ProductDeposit::count());
        $this->assertNull($this->attributes($milk));
    }

    public function test_confirm_all_confirms_every_suggestion_and_syncs(): void
    {
        $tier = $this->tier();
        $a = $this->posProduct('8711521947614', 'Juice');
        $b = $this->posProduct('8714728001004', 'Buttermilk');
        $this->suggestion($a, $tier, '8711521947614');
        $this->suggestion($b, $tier, '8714728001004');

        $this->actingAs($this->manager())
            ->post(route('deposits.products.confirm-all'))
            ->assertRedirect()
            ->assertSessionHas('success', '2 suggestion(s) confirmed; till: 2 written, 0 refused.');

        $this->assertSame(2, ProductDeposit::confirmed()->count());
        $this->assertTrue(PosProductAttributes::hasDeposit($this->attributes($a)));
        $this->assertTrue(PosProductAttributes::hasDeposit($this->attributes($b)));
    }

    public function test_refresh_and_sync_redirect_with_a_flash(): void
    {
        $user = $this->manager();

        $this->actingAs($user)->post(route('deposits.refresh'))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->post(route('deposits.sync'))->assertRedirect()->assertSessionHas('success');
    }
}
