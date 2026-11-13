<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Delivery;
use App\Models\DynamisPricingLine;
use App\Models\DynamisProductLink;
use App\Models\Role;
use App\Models\User;
use App\Models\VegPrintQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * Dynamis invoice pricing: invoice PDF -> docket -> price each fruit & veg
 * line for the till (docs/features/dynamis-pricing.md).
 */
class DynamisPricingTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->createDepositPosTables();

        $pos = DB::connection('pos')->getSchemaBuilder();
        $pos->create('units', function (Blueprint $table) {
            $table->integer('ID')->primary();
            $table->string('units');
        });
        $pos->create('vegDetails', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('product')->nullable();
            $table->integer('countryCode')->nullable();
            $table->string('classId')->nullable();
            $table->integer('unitId')->nullable();
        });
        $pos->create('class', function (Blueprint $table) {
            $table->integer('ID')->primary();
            $table->integer('classNum');
            $table->string('class');
        });
        DB::connection('pos')->table('class')->insert([['ID' => 1, 'classNum' => 1, 'class' => 'I'], ['ID' => 2, 'classNum' => 2, 'class' => 'II']]);
        DB::connection('pos')->table('CATEGORIES')->insert([['ID' => 'SUB1', 'NAME' => 'Fruit'], ['ID' => 'SUB2', 'NAME' => 'Vegetables']]);
        DB::connection('pos')->table('units')->insert([['ID' => 1, 'units' => 'kg'], ['ID' => 2, 'units' => 'each']]);
        DB::connection('pos')->table('TAXCATEGORIES')->insert([['ID' => '000', 'NAME' => 'Zero'], ['ID' => '001', 'NAME' => 'Standard']]);
        DB::connection('pos')->table('TAXES')->insert([
            ['ID' => '000', 'NAME' => 'Zero', 'CATEGORY' => '000', 'RATE' => 0],
            ['ID' => '001', 'NAME' => 'Standard', 'CATEGORY' => '001', 'RATE' => 0.23],
        ]);

        $this->tillProduct('p-hass', '1003', 'Avocado Hass', 1.90, 2);
        $this->tillProduct('p-pear', '1050', 'Pears Conference', 3.99, 1);
        $this->tillProduct('p-kiwi', '1010', 'Kiwi', 1.00, 2);
        $this->tillProduct('p-oil', '3520836120354', 'Philia Extra Virgin Olive Oil 75cl', 10.00, null, 'GROCERY', '001');

        Storage::fake('local');
        Storage::fake('private');
        Queue::fake();
        Process::fake([
            '*' => Process::result(output: file_get_contents(base_path('tests/Fixtures/dynamis_invoice_FC10006631.txt'))),
        ]);
    }

    private function tillProduct(string $id, string $code, string $name, float $price, ?int $unitId, string $category = 'SUB1', string $taxCat = '000', bool $onTill = true): void
    {
        $pos = DB::connection('pos');
        $pos->table('PRODUCTS')->insert([
            'ID' => $id, 'NAME' => $name, 'CODE' => $code, 'REFERENCE' => $code,
            'CATEGORY' => $category, 'TAXCAT' => $taxCat, 'PRICESELL' => $price,
        ]);
        if ($onTill) {
            $pos->table('PRODUCTS_CAT')->insert(['PRODUCT' => $id]);
        }
        if ($unitId) {
            $pos->table('vegDetails')->insert(['ID' => "v-$id", 'product' => $code, 'countryCode' => 1, 'classId' => '1', 'unitId' => $unitId]);
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->firstOrFail()->id]);
    }

    private function upload(?User $user = null)
    {
        return $this->actingAs($user ?? $this->user('manager'))->post(route('deliveries.dynamis-invoice'), [
            'invoice_pdf' => UploadedFile::fake()->create('FC10006631.pdf', 40, 'application/pdf'),
        ]);
    }

    private function import(): Delivery
    {
        $this->upload()->assertSessionHasNoErrors();

        return Delivery::latest('id')->firstOrFail();
    }

    private function line(Delivery $delivery, string $code): DynamisPricingLine
    {
        return DynamisPricingLine::where('delivery_id', $delivery->id)->where('dynamis_code', $code)->firstOrFail();
    }

    private function tillPrice(string $id): float
    {
        return round((float) DB::connection('pos')->table('PRODUCTS')->where('ID', $id)->value('PRICESELL'), 4);
    }

    public function test_only_managers_and_admins_can_use_it(): void
    {
        $this->upload($this->user('employee'))->assertForbidden();
        $this->assertSame(0, Delivery::count());

        $delivery = $this->import();
        $this->actingAs($this->user('employee'))->get(route('deliveries.dynamis-pricing.show', $delivery))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('deliveries.dynamis-pricing.show', $delivery))->assertOk();
    }

    public function test_upload_creates_a_docket_with_a_pricing_line_per_invoice_line(): void
    {
        $this->upload()->assertRedirect(route('deliveries.dynamis-pricing.show', Delivery::firstOrFail()));

        $delivery = Delivery::firstOrFail();
        $this->assertSame(56, (int) $delivery->supplier_id);
        $this->assertSame('2026-11-09', $delivery->delivery_date->toDateString());
        $this->assertSame('FC10006631', $delivery->import_data['dynamis']['invoice_number']);
        $this->assertEquals(1022.44, $delivery->import_data['dynamis']['gross']);
        $this->assertNull($delivery->freight_charge);
        $this->assertSame(41, $delivery->items()->count());
        $this->assertSame(41, DynamisPricingLine::where('delivery_id', $delivery->id)->count());
        $this->assertSame(2, DynamisPricingLine::where('is_barcoded', true)->count());
        $this->assertSame(1, $delivery->documents()->count());
        Queue::assertNothingPushed();

        // A barcode that is on the till is matched on the docket; a name guess is only a suggestion.
        $this->assertSame('p-oil', $this->line($delivery, '26184')->deliveryItem->product_id);
        $hass = $this->line($delivery, 'AVO012');
        $this->assertNull($hass->deliveryItem->product_id);
        $this->assertSame('p-hass', $hass->suggested_product_id);
        $this->assertSame(22.0, $hass->units_per_box);
        $this->assertSame('size', $hass->count_source);
        $this->assertNull($this->line($delivery, 'AVO001')->units_per_box);
        $this->assertSame(13.0, $this->line($delivery, 'POI009')->kg_per_box);
        $this->assertNull($this->line($delivery, 'KIW015')->units_per_box, 'SIZE 33 on a per-kg line is a grade');
    }

    public function test_the_same_invoice_is_not_imported_twice(): void
    {
        $delivery = $this->import();

        $this->upload()->assertRedirect(route('deliveries.dynamis-pricing.show', $delivery));
        $this->assertSame(1, Delivery::count());
    }

    public function test_a_file_that_is_not_a_dynamis_invoice_is_refused(): void
    {
        Process::fake(['*' => Process::result(output: "Some other supplier\nTotal 12,00\n")]);

        $this->upload()->assertSessionHasErrors('invoice_pdf');
        $this->assertSame(0, Delivery::count());
    }

    public function test_page_shows_produce_lines_barcoded_lines_and_the_defaults(): void
    {
        $delivery = $this->import();

        $this->actingAs($this->user('manager'))
            ->get(route('deliveries.dynamis-pricing.show', $delivery))
            ->assertOk()
            ->assertSee('FC10006631')
            ->assertSee('None on this invoice')
            ->assertSee('AVOCADO HASS SIZE 22 SPAIN CLASS II')
            ->assertSee('Barcoded lines')
            ->assertSee('Philia Extra Virgin Olive Oil 75cl');
    }

    public function test_choosing_a_till_product_links_the_docket_line_and_is_remembered(): void
    {
        $delivery = $this->import();
        $line = $this->line($delivery, 'AVO012');

        $this->actingAs($this->user('manager'))
            ->postJson(route('deliveries.dynamis-pricing.link', [$delivery, $line]), ['product_id' => 'p-hass'])
            ->assertOk()
            ->assertJsonPath('line.product.name', 'Avocado Hass')
            ->assertJsonPath('line.product.till_unit', 'each')
            ->assertJsonPath('line.mode', 'each');

        $this->assertSame('p-hass', $line->deliveryItem->fresh()->product_id);
        $link = DynamisProductLink::where('dynamis_code', 'AVO012')->firstOrFail();
        $this->assertSame('p-hass', $link->pos_product_id);
        $this->assertSame('fuzzy', $link->matched_by);
    }

    public function test_applying_a_price_updates_the_till_history_and_label_queue(): void
    {
        $manager = $this->user('manager');
        $delivery = $this->import();
        $line = $this->line($delivery, 'AVO012');
        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.link', [$delivery, $line]), ['product_id' => 'p-hass']);

        $this->actingAs($manager)
            ->postJson(route('deliveries.dynamis-pricing.apply', [$delivery, $line]), [
                'mode' => 'each', 'units_per_box' => 20, 'price' => 1.99,
            ])
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('line.applied_price', 1.99)
            ->assertJsonPath('line.product.till_price', 1.99);

        $this->assertSame(1.99, $this->tillPrice('p-hass'));
        $this->assertDatabaseHas('veg_price_history', ['product_code' => '1003', 'old_price' => 1.90, 'new_price' => 1.99, 'changed_by' => $manager->id]);
        $this->assertTrue(VegPrintQueue::where('product_code', '1003')->exists());

        $line->refresh();
        $this->assertSame('each', $line->pricing_mode);
        $this->assertSame(20.0, $line->units_per_box);
        $this->assertSame($manager->id, (int) $line->applied_by);

        $link = DynamisProductLink::where('dynamis_code', 'AVO012')->firstOrFail();
        $this->assertSame('each', $link->pricing_mode);
        $this->assertSame(20.0, $link->units_per_box);
    }

    public function test_a_line_without_a_till_product_cannot_be_applied(): void
    {
        $delivery = $this->import();

        $this->actingAs($this->user('manager'))
            ->postJson(route('deliveries.dynamis-pricing.apply', [$delivery, $this->line($delivery, 'AVO001')]), [
                'mode' => 'each', 'units_per_box' => 14, 'price' => 1.50,
            ])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('veg_price_history')->count());
    }

    public function test_barcoded_lines_cannot_be_priced_here(): void
    {
        $delivery = $this->import();

        $this->actingAs($this->user('manager'))
            ->postJson(route('deliveries.dynamis-pricing.apply', [$delivery, $this->line($delivery, '26184')]), [
                'mode' => 'each', 'price' => 12.50,
            ])
            ->assertNotFound();

        $this->assertSame(round(10 / 1, 4), $this->tillPrice('p-oil'));
    }

    public function test_next_invoice_reuses_the_link_the_mode_and_the_count(): void
    {
        $manager = $this->user('manager');
        $first = $this->import();
        $kiwi = $this->line($first, 'KIW015');
        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.link', [$first, $kiwi]), ['product_id' => 'p-kiwi']);
        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.apply', [$first, $kiwi]), [
            'mode' => 'each', 'units_per_box' => 90, 'price' => 0.85,
        ])->assertOk();

        // Same lines on next week's invoice
        Process::fake(['*' => Process::result(output: str_replace(
            'FC10006631',
            'FC10007200',
            file_get_contents(base_path('tests/Fixtures/dynamis_invoice_FC10006631.txt'))
        ))]);
        sleep(1); // docket numbers are stamped to the wall-clock second
        $second = $this->import();
        $this->assertNotSame($first->id, $second->id);

        $line = $this->line($second, 'KIW015');
        $this->assertSame('p-kiwi', $line->deliveryItem->product_id);
        $this->assertSame('each', $line->pricing_mode);
        $this->assertSame(90.0, $line->units_per_box);
        $this->assertSame('saved', $line->count_source);
        $this->assertSame(10.0, $line->kg_per_box);
        $this->assertNull($line->applied_price);
    }

    public function test_skip_and_undo(): void
    {
        $manager = $this->user('manager');
        $delivery = $this->import();
        $line = $this->line($delivery, 'POI009');

        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.skip', [$delivery, $line]), ['skip' => true])
            ->assertOk()->assertJsonPath('line.skipped', true);
        $this->assertSame('skip', $line->fresh()->pricing_mode);

        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.skip', [$delivery, $line]), ['skip' => false])
            ->assertOk()->assertJsonPath('line.skipped', false);
        $this->assertNull($line->fresh()->pricing_mode);
    }

    public function test_transport_and_markup_are_per_invoice_unless_made_the_default(): void
    {
        $manager = $this->user('manager');
        $delivery = $this->import();

        $this->actingAs($manager)
            ->postJson(route('deliveries.dynamis-pricing.settings', $delivery), ['transport' => 1.15, 'markup' => 1.6])
            ->assertOk()->assertJsonPath('settings.transport', 1.15);
        $this->assertEquals(1.6, $delivery->fresh()->import_data['dynamis']['markup']);
        $this->assertNull(AppSetting::get('dynamis.transport_multiplier'));

        $this->actingAs($manager)
            ->postJson(route('deliveries.dynamis-pricing.settings', $delivery), ['transport' => 1.25, 'markup' => 1.5, 'as_default' => true])
            ->assertOk();
        $this->assertEquals(1.25, AppSetting::get('dynamis.transport_multiplier'));
    }

    public function test_fruit_veg_manage_price_update_still_writes_till_history_and_queue(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->postJson(route('fruit-veg.prices.update'), ['product_code' => '1050', 'new_price' => 4.25])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(4.25, $this->tillPrice('p-pear'));
        $this->assertDatabaseHas('veg_price_history', ['product_code' => '1050', 'new_price' => 4.25]);
        $this->assertTrue(VegPrintQueue::where('product_code', '1050')->exists());
    }

    public function test_guesses_prefer_products_on_the_till_and_say_when_one_is_hidden(): void
    {
        // Same words, one hidden: the visible one (from setUp) wins the tie.
        $this->tillProduct('p-pear-hidden', '7001', 'Pear Conference', 3.50, null, 'SUB1', '000', false);
        // A hidden product whose name is an exact match beats a loose match on the till.
        $this->tillProduct('p-bacon', '7003', 'Avocado Bacon', 1.50, null, 'SUB1', '000', false);
        // A hidden product that is only a slightly better match than the till's Kiwi does not.
        $this->tillProduct('p-kiwi-gold', '7005', 'Kiwi Gold Large', 0.90, null, 'SUB1', '000', false);

        $delivery = $this->import();

        $this->assertSame('p-pear', $this->line($delivery, 'POI009')->suggested_product_id);
        $this->assertSame('p-bacon', $this->line($delivery, 'AVO001')->suggested_product_id);
        $this->assertSame('p-hass', $this->line($delivery, 'AVO012')->suggested_product_id);
        $this->assertSame('p-kiwi', DynamisPricingLine::where('description', 'like', 'KIWI GOLD%')->firstOrFail()->suggested_product_id);

        $lines = collect(app(\App\Services\DynamisPricingService::class)->lines($delivery)['produce'])->keyBy('code');
        $this->assertFalse($lines['AVO001']['suggestion']['on_till']);
        $this->assertTrue($lines['AVO012']['suggestion']['on_till']);

        // Putting a better match on the till changes the guess for lines not yet linked.
        DB::connection('pos')->table('PRODUCTS_CAT')->insert(['PRODUCT' => 'p-kiwi-gold']);
        app(\App\Services\DynamisPricingService::class)->refreshGuesses($delivery);
        $this->assertSame('p-kiwi-gold', DynamisPricingLine::where('description', 'like', 'KIWI GOLD%')->firstOrFail()->suggested_product_id);
    }

    public function test_a_linked_product_hidden_from_the_till_can_be_shown_on_it(): void
    {
        $this->tillProduct('p-bacon', '7003', 'Avocado Bacon', 1.50, 2, 'SUB1', '000', false);
        $delivery = $this->import();
        $line = $this->line($delivery, 'AVO001');
        $manager = $this->user('manager');
        $url = route('deliveries.dynamis-pricing.show-on-till', [$delivery, $line]);

        // Nothing linked yet: a guess alone is not put on the till.
        $this->actingAs($manager)->postJson($url)->assertStatus(422);
        $this->assertSame(0, DB::connection('pos')->table('PRODUCTS_CAT')->where('PRODUCT', 'p-bacon')->count());

        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.link', [$delivery, $line]), ['product_id' => 'p-bacon'])
            ->assertJsonPath('line.product.on_till', false);

        $this->actingAs($this->user('employee'))->postJson($url)->assertForbidden();

        $this->actingAs($manager)->postJson($url)->assertOk()->assertJsonPath('line.product.on_till', true);
        $this->actingAs($manager)->postJson($url)->assertOk();
        $this->assertSame(1, DB::connection('pos')->table('PRODUCTS_CAT')->where('PRODUCT', 'p-bacon')->count());
        $this->assertDatabaseHas('product_activity_logs', ['product_id' => 'p-bacon', 'category' => 'fruit_veg']);
    }

    public function test_country_of_origin_is_compared_and_only_changed_on_a_click(): void
    {
        $spain = \App\Models\Country::where('code', 'ES')->firstOrFail();
        $ireland = \App\Models\Country::where('code', 'IE')->firstOrFail();
        $country = fn () => (int) DB::connection('pos')->table('vegDetails')->where('product', '1003')->value('countryCode');
        $this->assertSame((int) $ireland->id, $country());

        $delivery = $this->import();
        $line = $this->line($delivery, 'AVO012');
        $manager = $this->user('manager');
        $url = route('deliveries.dynamis-pricing.country', [$delivery, $line]);

        // No till product yet
        $this->actingAs($manager)->postJson($url)->assertStatus(422);

        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.link', [$delivery, $line]), ['product_id' => 'p-hass'])
            ->assertJsonPath('line.origin', 'ES')
            ->assertJsonPath('line.origin_country.name', 'Spain')
            ->assertJsonPath('line.product.country_name', 'Ireland');

        // Applying a price leaves the country alone.
        $this->actingAs($manager)->postJson(route('deliveries.dynamis-pricing.apply', [$delivery, $line]), [
            'mode' => 'each', 'units_per_box' => 22, 'price' => 1.85,
        ])->assertOk();
        $this->assertSame((int) $ireland->id, $country());
        VegPrintQueue::query()->delete();

        $this->actingAs($this->user('employee'))->postJson($url)->assertForbidden();

        $this->actingAs($manager)->postJson($url)->assertOk()
            ->assertJsonPath('line.product.country_id', (int) $spain->id)
            ->assertJsonPath('line.product.country_name', 'Spain');
        $this->assertSame((int) $spain->id, $country());
        $this->assertSame('country_updated', VegPrintQueue::where('product_code', '1003')->value('reason'));

        // An origin the shop does not list cannot be applied.
        $line->update(['origin' => 'ZZ']);
        $this->actingAs($manager)->postJson($url)->assertStatus(422);
        $this->assertSame((int) $spain->id, $country());
    }

    public function test_a_new_till_product_can_be_created_from_a_line(): void
    {
        $delivery = $this->import();
        $line = $this->line($delivery, 'AVO001'); // AVOCADO BACON SPAIN CLASS II, 1 box at 25.20, no count on the invoice
        $manager = $this->user('manager');
        $url = route('deliveries.dynamis-pricing.new-product', [$delivery, $line]);
        $spain = \App\Models\Country::where('code', 'ES')->firstOrFail();

        $page = collect(app(\App\Services\DynamisPricingService::class)->lines($delivery)['produce'])->keyBy('code');
        $this->assertSame(['name' => 'Avocado Bacon', 'class' => 'II'], $page['AVO001']['new_product']);

        $form = [
            'name' => 'Avocado Bacon', 'display' => 'Bacon Avo', 'category' => 'SUB1', 'mode' => 'each', 'units_per_box' => 14,
            'price' => 2.70, 'country_id' => $spain->id, 'class_id' => 2, 'show_on_till' => 1,
            'image' => UploadedFile::fake()->image('bacon.jpg', 600, 400),
        ];

        $this->actingAs($this->user('employee'))->post($url, $form, ['Accept' => 'application/json'])->assertForbidden();
        // No count yet: the cost per piece cannot be worked out.
        $this->actingAs($manager)->post($url, ['units_per_box' => null] + $form, ['Accept' => 'application/json'])->assertStatus(422);
        // A name the till already has.
        $this->actingAs($manager)->post($url, ['name' => 'Avocado Hass'] + $form, ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, DB::connection('pos')->table('PRODUCTS')->where('NAME', 'Avocado Bacon')->count());

        $this->actingAs($manager)->post($url, $form, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('line.product.name', 'Avocado Bacon')
            ->assertJsonPath('line.product.on_till', true)
            ->assertJsonPath('line.product.till_unit', 'each')
            ->assertJsonPath('line.product.country_name', 'Spain')
            ->assertJsonPath('line.applied_price', 2.7);

        $product = DB::connection('pos')->table('PRODUCTS')->where('NAME', 'Avocado Bacon')->first();
        $this->assertSame('Bacon Avo', $product->DISPLAY);
        $this->assertSame('SUB1', $product->CATEGORY);
        $this->assertSame('000', $product->TAXCAT);
        $this->assertEquals(2.70, round((float) $product->PRICESELL, 2));
        // Cost is the invoice cost per piece, without delivery.
        $this->assertEquals(round($line->net_unit_price * $line->quantity / $line->packages / 14, 4), round((float) $product->PRICEBUY, 4));
        $this->assertNotEmpty($product->IMAGE);
        $this->assertLessThanOrEqual(128, imagesx(imagecreatefromstring($product->IMAGE)));
        $this->assertTrue(ctype_digit($product->CODE) && (int) $product->CODE >= 1000 && (int) $product->CODE <= 2999);

        $details = DB::connection('pos')->table('vegDetails')->where('product', $product->CODE)->first();
        $this->assertSame([(int) $spain->id, 2, 2], [(int) $details->countryCode, (int) $details->classId, (int) $details->unitId]);
        $this->assertSame('new_product', VegPrintQueue::where('product_code', $product->CODE)->value('reason'));
        $this->assertSame($product->ID, $line->deliveryItem->fresh()->product_id);
        $this->assertSame($product->ID, \App\Models\DynamisProductLink::where('dynamis_code', 'AVO001')->value('pos_product_id'));

        // The line has a product now; a second one is refused.
        $this->actingAs($manager)->post($url, ['name' => 'Avocado Bacon Two'] + $form, ['Accept' => 'application/json'])->assertStatus(422);
    }
}
