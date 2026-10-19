<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BarrelCode;
use App\Models\DepositSighting;
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
 * The /deposits office screen (deposit cycle 2; layout 2a and the one-step
 * confirm with a price choice in cycle 4 revision 3).
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

    private function sighting(string $barrelCode): DepositSighting
    {
        static $source = 0;

        return DepositSighting::create([
            'supplier_id' => 5,
            'supplier_code' => '92505',
            'barrel_code' => $barrelCode,
            'units' => 6,
            'source_type' => DepositSighting::SOURCE_DELIVERY_ITEM,
            'source_id' => ++$source,
            'seen_on' => '2026-10-05',
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
        $this->sighting('7');
        $this->tier('15', 0.00, charge: false);
        $juice = $this->posProduct('8711521947614', 'Luna e Terra Apple-mango juice 200ml');
        $this->suggestion($juice, $tier);

        $this->actingAs($this->manager())
            ->get(route('deposits.index'))
            ->assertOk()
            ->assertSee('Deposit codes')
            ->assertSee('Price review')
            ->assertSee('Add a product')
            ->assertSee('Luna e Terra Apple-mango juice 200ml')
            ->assertSee('54 units · 12 dockets')
            ->assertSee('last docket 2026-07-15')
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

    private function priceSell(string $productId): float
    {
        return (float) DB::connection('pos')->table('PRODUCTS')->where('ID', $productId)->value('PRICESELL');
    }

    public function test_page_shows_the_price_review_columns(): void
    {
        $tier = $this->tier();
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70, 'PRICEBUY' => 1.31]);
        $this->suggestion($juice, $tier);
        $soldOn = now()->subDays(3)->toDateString();
        $this->posSale($juice, $soldOn, 2.70, 4);

        $this->actingAs($this->manager())
            ->get(route('deposits.index', ['sort' => 'markup']))
            ->assertOk()
            ->assertSee("sold 4 in 12 mo · last {$soldOn}")
            ->assertSee('0 of 1 decided')
            ->assertSee('€2.70')
            ->assertSee('landed 1.51 · net 2.70')
            ->assertSee('−€0.25');
    }

    public function test_keep_records_the_review_without_touching_the_price(): void
    {
        $user = $this->manager();
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());

        $this->actingAs($user)
            ->patch(route('deposits.products.price', $row), ['action' => 'keep'])
            ->assertRedirect()
            ->assertSessionHas('success', '8711521947614: price kept at €2.70.');

        $row->refresh();
        $this->assertSame(ProductDeposit::PRICE_KEPT, $row->price_review);
        $this->assertSame($user->id, $row->price_reviewed_by);
        $this->assertSame(2.70, $this->priceSell($juice));
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $row->status);
    }

    public function test_lower_then_reset_keeps_the_lowered_price(): void
    {
        $user = $this->manager();
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());

        $this->actingAs($user)
            ->patch(route('deposits.products.price', $row), ['action' => 'lower'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Lime juice: €2.70 → €2.45 on the till (net 2.7000 → 2.4500); label queued.');

        $this->assertEqualsWithDelta(2.45, $this->priceSell($juice), 0.00005);
        $this->assertSame(ProductDeposit::PRICE_LOWERED, $row->fresh()->price_review);

        $this->actingAs($user)
            ->get(route('deposits.index'))
            ->assertSee('lowered from €2.70')
            ->assertSee('1 price reviewed');

        $this->actingAs($user)
            ->patch(route('deposits.products.price', $row), ['action' => 'reset'])
            ->assertRedirect()
            ->assertSessionHas('success', '8711521947614: review cleared (price unchanged).');

        $row->refresh();
        $this->assertNull($row->price_review);
        $this->assertNull($row->price_review_from);
        $this->assertNull($row->price_reviewed_at);
        $this->assertNull($row->price_reviewed_by);
        $this->assertEqualsWithDelta(2.45, $this->priceSell($juice), 0.00005);
    }

    public function test_lower_refused_flashes_an_error(): void
    {
        $water = $this->posProduct('8711521947614', 'Cheap water', ['TAXCAT' => '000', 'PRICESELL' => 0.20]);
        $row = $this->suggestion($water, $this->tier());

        $this->actingAs($this->manager())
            ->patch(route('deposits.products.price', $row), ['action' => 'lower'])
            ->assertRedirect()
            ->assertSessionHasErrors('price_review');

        $this->assertSame(0.20, $this->priceSell($water));
    }

    public function test_price_review_rejects_an_unknown_action(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice');
        $row = $this->suggestion($juice, $this->tier());

        $this->actingAs($this->manager())
            ->patchJson(route('deposits.products.price', $row), ['action' => 'raise'])
            ->assertStatus(422);

        $this->assertNull($row->fresh()->price_review);
    }

    public function test_rows_sit_under_tier_and_brand_headers(): void
    {
        $tier = $this->tier();
        $milk = $this->posProduct('8714728001004', 'Ommelanden Buttermilk 1l');
        $this->suggestion($milk, $tier, '8714728001004');
        $user = $this->manager();

        $this->actingAs($user)
            ->get(route('deposits.index'))
            ->assertOk()
            ->assertSee('Tier 313')
            ->assertSee('€0.25 deposit · 1 product')
            ->assertSee('Confirm 1');

        $this->actingAs($user)
            ->get(route('deposits.index', ['group' => 'brand']))
            ->assertOk()
            ->assertDontSee('Tier 313')
            ->assertSee('<input type="hidden" name="brand" value="Ommelanden">', false);

        $this->actingAs($user)
            ->get(route('deposits.index', ['group' => 'none']))
            ->assertOk()
            ->assertDontSee('Tier 313')
            ->assertSee('Ommelanden Buttermilk 1l');
    }

    public function test_sort_by_markup_puts_the_lowest_first(): void
    {
        $tier = $this->tier();
        $high = $this->posProduct('8711521947614', 'Alpha juice', ['TAXCAT' => '000', 'PRICESELL' => 3.00, 'PRICEBUY' => 1.00]);
        $low = $this->posProduct('8714728001004', 'Beta juice', ['TAXCAT' => '000', 'PRICESELL' => 1.50, 'PRICEBUY' => 1.00]);
        $this->suggestion($high, $tier, '8711521947614');
        $this->suggestion($low, $tier, '8714728001004');
        $user = $this->manager();

        $this->actingAs($user)->get(route('deposits.index'))->assertSeeInOrder(['Alpha juice</a>', 'Beta juice</a>'], false);
        $this->actingAs($user)->get(route('deposits.index', ['sort' => 'markup']))->assertSeeInOrder(['Beta juice</a>', 'Alpha juice</a>'], false);
    }

    public function test_confirm_with_lower_confirms_and_lowers_the_price(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());

        $this->actingAs($this->manager())
            ->patch(route('deposits.products.update', $row), ['status' => 'confirmed', 'price_action' => 'lower'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '8711521947614: confirmed (written on the till). Lime juice: €2.70 → €2.45 on the till (net 2.7000 → 2.4500); label queued.');

        $row->refresh();
        $this->assertSame(ProductDeposit::STATUS_CONFIRMED, $row->status);
        $this->assertSame(ProductDeposit::PRICE_LOWERED, $row->price_review);
        $this->assertEqualsWithDelta(2.45, $this->priceSell($juice), 0.00005);
        $this->assertTrue(PosProductAttributes::hasDeposit($this->attributes($juice)));
    }

    public function test_confirm_with_a_custom_price_sets_it(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());

        $this->actingAs($this->manager())
            ->patch(route('deposits.products.update', $row), ['status' => 'confirmed', 'price_action' => 'custom', 'new_price' => '2.49'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertSame(ProductDeposit::STATUS_CONFIRMED, $row->status);
        $this->assertSame(ProductDeposit::PRICE_SET, $row->price_review);
        $this->assertSame('2.7000', $row->price_review_from);
        $this->assertEqualsWithDelta(2.49, $this->priceSell($juice), 0.00005);
    }

    public function test_confirm_with_keep_records_kept_and_custom_needs_a_price(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());
        $user = $this->manager();

        $this->actingAs($user)
            ->patch(route('deposits.products.update', $row), ['status' => 'confirmed', 'price_action' => 'custom', 'new_price' => ''])
            ->assertSessionHasErrors('new_price');
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $row->fresh()->status);

        $this->actingAs($user)
            ->patch(route('deposits.products.update', $row), ['status' => 'confirmed', 'price_action' => 'keep'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProductDeposit::PRICE_KEPT, $row->fresh()->price_review);
        $this->assertSame(2.70, $this->priceSell($juice));
        $this->assertDatabaseCount('label_logs', 0);
    }

    public function test_reject_ignores_the_price_choice(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());

        $this->actingAs($this->manager())
            ->patch(route('deposits.products.update', $row), ['status' => 'rejected', 'price_action' => 'lower'])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame(ProductDeposit::STATUS_REJECTED, $row->status);
        $this->assertNull($row->price_review);
        $this->assertSame(2.70, $this->priceSell($juice));
        $this->assertDatabaseCount('label_logs', 0);
    }

    public function test_a_refused_price_write_keeps_the_confirmation(): void
    {
        $water = $this->posProduct('8711521947614', 'Cheap water', ['TAXCAT' => '000', 'PRICESELL' => 0.20]);
        $row = $this->suggestion($water, $this->tier());

        $this->actingAs($this->manager())
            ->patch(route('deposits.products.update', $row), ['status' => 'confirmed', 'price_action' => 'lower'])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasErrors('price_review');

        $this->assertSame(ProductDeposit::STATUS_CONFIRMED, $row->fresh()->status);
        $this->assertSame(0.20, $this->priceSell($water));
    }

    public function test_confirm_all_for_one_tier_leaves_other_tiers_and_prices(): void
    {
        $t313 = $this->tier();
        $t315 = $this->tier('315', 0.70);
        $a = $this->posProduct('8711521947614', 'Juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $b = $this->posProduct('8714728001004', 'Buttermilk', ['TAXCAT' => '000', 'PRICESELL' => 2.50]);
        $ra = $this->suggestion($a, $t313, '8711521947614');
        $rb = $this->suggestion($b, $t315, '8714728001004');

        $this->actingAs($this->manager())
            ->post(route('deposits.products.confirm-all'), ['barrel_code_id' => $t313->id])
            ->assertRedirect()
            ->assertSessionHas('success', '1 suggestion(s) confirmed; till: 1 written, 0 refused.');

        $this->assertSame(ProductDeposit::STATUS_CONFIRMED, $ra->fresh()->status);
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $rb->fresh()->status);
        $this->assertNull($ra->fresh()->price_review);
        $this->assertSame(2.70, $this->priceSell($a));
        $this->assertSame(2.50, $this->priceSell($b));
    }

    public function test_confirm_all_for_one_brand(): void
    {
        $tier = $this->tier();
        $a = $this->posProduct('8711521947614', 'Ommelanden Buttermilk 1l');
        $b = $this->posProduct('8714728001004', 'Laitois Yogurt 500g');
        $ra = $this->suggestion($a, $tier, '8711521947614');
        $rb = $this->suggestion($b, $tier, '8714728001004');

        $this->actingAs($this->manager())
            ->post(route('deposits.products.confirm-all'), ['brand' => 'ommelanden'])
            ->assertSessionHas('success', '1 suggestion(s) confirmed; till: 1 written, 0 refused.');

        $this->assertSame(ProductDeposit::STATUS_CONFIRMED, $ra->fresh()->status);
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $rb->fresh()->status);
    }

    public function test_undo_restores_the_price_and_the_suggestion(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70]);
        $row = $this->suggestion($juice, $this->tier());
        $user = $this->manager();
        $this->actingAs($user)->patch(route('deposits.products.update', $row), ['status' => 'confirmed', 'price_action' => 'lower']);
        $this->assertEqualsWithDelta(2.45, $this->priceSell($juice), 0.00005);

        $this->actingAs($user)
            ->get(route('deposits.index'))
            ->assertSee('Undo also restores the price to \\u20ac2.70. Continue?', false);

        $this->actingAs($user)
            ->patch(route('deposits.products.price', $row), ['action' => 'undo'])
            ->assertRedirect()
            ->assertSessionHas('success', '8711521947614: back to suggested; price restored to €2.70.');

        $row->refresh();
        $this->assertSame(ProductDeposit::STATUS_SUGGESTED, $row->status);
        $this->assertNull($row->confirmed_by);
        $this->assertNull($row->price_review);
        $this->assertNull($row->price_review_from);
        $this->assertNull($row->price_reviewed_at);
        $this->assertNull($row->price_reviewed_by);
        $this->assertEqualsWithDelta(2.70, $this->priceSell($juice), 0.00005);
        $this->assertNull($this->attributes($juice));
        $this->assertDatabaseCount('label_logs', 2);
    }

    public function test_delivery_charge_setting_changes_the_landed_cost(): void
    {
        $juice = $this->posProduct('8711521947614', 'Lime juice', ['TAXCAT' => '000', 'PRICESELL' => 2.70, 'PRICEBUY' => 1.00]);
        $this->suggestion($juice, $this->tier());
        $user = $this->manager();

        $this->actingAs($user)->get(route('deposits.index'))->assertSee('landed 1.15 · net 2.70');

        $this->actingAs($user)
            ->patch(route('deposits.settings'), ['delivery_pct' => '20'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Delivery charge set to 20%.');

        $this->assertSame('20', AppSetting::get('deposits.delivery_pct'));
        $this->actingAs($user)->get(route('deposits.index'))->assertSee('landed 1.20 · net 2.70');

        $this->actingAs($user)
            ->patch(route('deposits.settings'), ['delivery_pct' => '150'])
            ->assertSessionHasErrors('delivery_pct');
        $this->assertSame('20', AppSetting::get('deposits.delivery_pct'));
    }

    public function test_strip_shows_charged_and_seen_codes_unless_all_are_asked_for(): void
    {
        $this->tier();
        BarrelCode::create([
            'supplier_code' => '7', 'supplier_id' => 5, 'description' => 'Europallet',
            'unit_price' => 15.00, 'is_active' => true, 'charge_customer' => false,
        ]);
        $this->tier('10046', 0.15, charge: false);
        $this->sighting('10046');
        $user = $this->manager();

        $this->actingAs($user)
            ->get(route('deposits.index', ['group' => 'brand']))
            ->assertOk()
            ->assertSee('Statiegeld 313')
            ->assertSee('Statiegeld 10046')
            ->assertDontSee('Europallet')
            ->assertSee('Show all 3 codes')
            ->assertSee('codes=all', false)
            ->assertSee('group=brand', false);

        $this->actingAs($user)
            ->get(route('deposits.index', ['codes' => 'all']))
            ->assertOk()
            ->assertSee('Europallet')
            ->assertSee('Statiegeld 10046')
            ->assertSee('Show deposit codes only');
    }
}
