<?php

namespace Tests\Feature\Shop;

use App\Models\Delivery;
use App\Models\DynamisPricingLine;
use App\Models\Role;
use App\Models\User;
use App\Services\DynamisPricingService;
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
 * Shop mode Dynamis pricing: the office pricing page for a phone, at
 * /shop/fv/pricing. The writes are the office endpoints, covered by
 * Tests\Feature\DynamisPricingTest; this covers who may open the Shop screens
 * and what they are handed.
 */
class ShopDynamisPricingTest extends TestCase
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
        DB::connection('pos')->table('units')->insert([['ID' => 1, 'units' => 'kg'], ['ID' => 2, 'units' => 'each']]);
        DB::connection('pos')->table('TAXCATEGORIES')->insert([['ID' => '000', 'NAME' => 'Zero']]);
        DB::connection('pos')->table('TAXES')->insert([['ID' => '000', 'NAME' => 'Zero', 'CATEGORY' => '000', 'RATE' => 0]]);
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'p-hass', 'NAME' => 'Avocado Hass', 'CODE' => '1003', 'REFERENCE' => '1003',
            'CATEGORY' => 'SUB1', 'TAXCAT' => '000', 'PRICESELL' => 1.90,
        ]);
        DB::connection('pos')->table('PRODUCTS_CAT')->insert(['PRODUCT' => 'p-hass']);
        DB::connection('pos')->table('vegDetails')->insert(['ID' => 'v-hass', 'product' => '1003', 'countryCode' => 1, 'classId' => '1', 'unitId' => 2]);

        Storage::fake('local');
        Storage::fake('private');
        Queue::fake();
        Process::fake([
            '*' => Process::result(output: file_get_contents(base_path('tests/Fixtures/dynamis_invoice_FC10006631.txt'))),
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->firstOrFail()->id]);
    }

    private function import(): Delivery
    {
        $this->actingAs($this->user('manager'))->post(route('deliveries.dynamis-invoice'), [
            'invoice_pdf' => UploadedFile::fake()->create('FC10006631.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return Delivery::latest('id')->firstOrFail();
    }

    public function test_only_managers_and_admins_can_open_the_screens(): void
    {
        $delivery = $this->import();

        $employee = $this->user('employee');
        $this->actingAs($employee)->get(route('shop.fv.pricing'))->assertForbidden();
        $this->actingAs($employee)->get(route('shop.fv.pricing.show', $delivery))->assertForbidden();

        $this->actingAs($this->user('manager'))->get(route('shop.fv.pricing'))->assertOk();
        $this->actingAs($this->user('admin'))->get(route('shop.fv.pricing.show', $delivery))->assertOk();
    }

    public function test_the_list_shows_each_invoice_and_how_far_it_has_got(): void
    {
        $delivery = $this->import();
        $produce = DynamisPricingLine::where('delivery_id', $delivery->id)->where('is_barcoded', false);
        $total = (clone $produce)->count();

        $pricing = app(DynamisPricingService::class);
        $hass = (clone $produce)->where('dynamis_code', 'AVO012')->firstOrFail();
        $pricing->linkProduct($hass, 'p-hass', null);
        $pricing->apply($hass->refresh(), DynamisPricingLine::MODE_EACH, 22, null, 2.10, null);
        $pricing->skip((clone $produce)->where('dynamis_code', 'KIW015')->firstOrFail());

        $invoices = $pricing->recentInvoices();
        $this->assertCount(1, $invoices);
        $this->assertSame('FC10006631', $invoices[0]['invoice_number']);
        $this->assertSame($total, $invoices[0]['lines']);
        $this->assertSame(1, $invoices[0]['priced']);
        $this->assertSame(1, $invoices[0]['skipped']);
        $this->assertSame($total - 2, $invoices[0]['todo']);

        $this->actingAs($this->user('manager'))->get(route('shop.fv.pricing'))
            ->assertOk()
            ->assertSee('FC10006631')
            ->assertSee(($total - 2).' to do')
            ->assertSee(route('shop.fv.pricing.show', $delivery), false)
            // The Pricing tab is the current one.
            ->assertSee('href="'.route('shop.fv.pricing').'" aria-current="page"', false);
    }

    public function test_the_invoice_screen_is_handed_the_produce_lines_and_the_office_endpoints(): void
    {
        $delivery = $this->import();
        $produce = DynamisPricingLine::where('delivery_id', $delivery->id)->where('is_barcoded', false)->count();

        $response = $this->actingAs($this->user('manager'))->get(route('shop.fv.pricing.show', $delivery));

        $response->assertOk()
            ->assertViewHas('lines', fn (array $lines) => count($lines) === $produce)
            ->assertViewHas('barcodedCount', 2)
            ->assertSee('x-data="shopDynamisPricing()"', false)
            ->assertSee('data-line-url="'.url("/deliveries/{$delivery->id}/dynamis-pricing/lines").'"', false)
            ->assertSee('2 barcoded lines are not priced here.');

        // The lines travel as JSON in a data attribute; the page script reads them back.
        preg_match('/data-lines="([^"]*)"/', $response->getContent(), $m);
        $lines = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertCount($produce, $lines);
        $hass = collect($lines)->firstWhere('code', 'AVO012');
        $this->assertSame('Avocado Hass', $hass['suggestion']['name']);
        $this->assertEquals(22, $hass['units_per_box']);

        // The URL the script posts to is the office apply endpoint.
        $this->assertSame(
            url("/deliveries/{$delivery->id}/dynamis-pricing/lines/{$hass['id']}/apply"),
            route('deliveries.dynamis-pricing.apply', [$delivery, $hass['id']])
        );
    }

    public function test_a_docket_without_pricing_lines_is_not_found(): void
    {
        $delivery = $this->import();
        DynamisPricingLine::where('delivery_id', $delivery->id)->delete();

        $this->actingAs($this->user('manager'))->get(route('shop.fv.pricing.show', $delivery))->assertNotFound();
        $this->assertSame([], app(DynamisPricingService::class)->recentInvoices());
    }
}
