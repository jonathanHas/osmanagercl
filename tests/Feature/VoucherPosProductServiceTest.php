<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherPosProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * The hidden uniCenta product behind every gift voucher (cycle 28).
 */
class VoucherPosProductServiceTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createVoucherPosTables();
    }

    private function service(): VoucherPosProductService
    {
        return app(VoucherPosProductService::class);
    }

    private function voucher(string $status = Voucher::STATUS_ACTIVE, float $balance = 42.50, string $code = 'GV7KQFM2RA9T'): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'initial_value' => $balance,
            'current_balance' => $balance,
            'status' => $status,
        ]);
    }

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        foreach (['vouchers.redeem', 'vouchers.manage'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Vouchers']
            ));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function product(string $code): ?object
    {
        return DB::connection('pos')->table('PRODUCTS')->where('CODE', $code)->first();
    }

    public function test_it_creates_a_hidden_zero_price_service_product(): void
    {
        $voucher = $this->voucher();

        $id = $this->service()->sync($voucher);

        $this->assertNotNull($id);
        $product = $this->product('GV7KQFM2RA9T');
        $this->assertSame($id, $product->ID);
        $this->assertSame('Gift Voucher GV7KQFM2RA9T [bal €42.50]', $product->NAME);
        $this->assertSame('GV7KQFM2RA9T', $product->REFERENCE);
        $this->assertSame('000', $product->TAXCAT);
        $this->assertEquals(0, $product->PRICESELL);
        $this->assertEquals(1, $product->ISSERVICE);

        $category = DB::connection('pos')->table('CATEGORIES')->where('ID', $product->CATEGORY)->first();
        $this->assertSame('Gift Voucher Redemption', $category->NAME);
        $this->assertSame('033', $category->PARENTID);
        $this->assertEquals(0, $category->CATSHOWNAME);

        // No till button.
        $this->assertSame(0, DB::connection('pos')->table('PRODUCTS_CAT')->count());

        $this->assertSame($id, $voucher->fresh()->pos_product_id);
    }

    public function test_the_category_is_created_once(): void
    {
        $this->service()->sync($this->voucher(code: 'GV22222222AA'));
        $this->service()->sync($this->voucher(code: 'GV33333333BB'));

        $this->assertSame(1, DB::connection('pos')->table('CATEGORIES')->count());
    }

    public function test_names_follow_the_status(): void
    {
        $service = $this->service();

        $this->assertSame('Gift Voucher GV22222222AA [€0.00 used up]', $service->productName($this->voucher(Voucher::STATUS_EXHAUSTED, 0, 'GV22222222AA')));
        $this->assertSame('Gift Voucher GV33333333BB [not active]', $service->productName($this->voucher(Voucher::STATUS_INACTIVE, 0, 'GV33333333BB')));
        $this->assertSame('Gift Voucher GV44444444CC [deactivated]', $service->productName($this->voucher(Voucher::STATUS_DEACTIVATED, 10, 'GV44444444CC')));
    }

    public function test_sync_is_idempotent(): void
    {
        $voucher = $this->voucher();

        $first = $this->service()->sync($voucher);
        $second = $this->service()->sync($voucher->fresh());

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::connection('pos')->table('PRODUCTS')->count());
    }

    public function test_it_links_an_existing_product_by_code(): void
    {
        $voucher = $this->voucher();
        $id = $this->service()->sync($voucher);

        // A half-failed create: the product exists, the voucher never learned its id.
        $voucher->forceFill(['pos_product_id' => null])->saveQuietly();

        $service = $this->service();
        $this->assertSame($id, $service->sync($voucher->fresh()));
        $this->assertSame('linked', $service->lastAction());
        $this->assertSame($id, $voucher->fresh()->pos_product_id);
        $this->assertSame(1, DB::connection('pos')->table('PRODUCTS')->count());
    }

    public function test_it_renames_the_product_when_the_balance_changes(): void
    {
        $voucher = $this->voucher();
        $this->service()->sync($voucher);

        $voucher->update(['current_balance' => 12.30]);
        $service = $this->service();
        $service->sync($voucher->fresh());

        $this->assertSame('renamed', $service->lastAction());
        $this->assertSame('Gift Voucher GV7KQFM2RA9T [bal €12.30]', $this->product('GV7KQFM2RA9T')->NAME);
    }

    public function test_a_pos_failure_is_logged_and_swallowed(): void
    {
        $voucher = $this->voucher();
        DB::connection('pos')->getSchemaBuilder()->drop('PRODUCTS');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'Voucher POS product sync failed'
                && $context['code'] === 'GV7KQFM2RA9T');

        $this->assertNull($this->service()->sync($voucher));
        $this->assertNull($voucher->fresh()->pos_product_id);
        $this->assertSame('42.50', $voucher->fresh()->current_balance);
    }

    public function test_generating_vouchers_creates_their_products(): void
    {
        $this->actingAs($this->manager())
            ->post(route('vouchers.generate.store'), ['count' => 3])
            ->assertRedirect()
            ->assertSessionMissing('warning');

        $this->assertSame(3, DB::connection('pos')->table('PRODUCTS')->where('CODE', 'like', 'GV%')->count());
        $this->assertSame(3, DB::connection('pos')->table('PRODUCTS')->where('NAME', 'like', '%[not active]')->count());
        $this->assertSame(0, Voucher::whereNull('pos_product_id')->count());
    }

    public function test_generating_warns_when_the_pos_is_down(): void
    {
        DB::connection('pos')->getSchemaBuilder()->drop('PRODUCTS');

        $this->actingAs($this->manager())
            ->post(route('vouchers.generate.store'), ['count' => 2])
            ->assertRedirect()
            ->assertSessionHas('warning');

        // The vouchers themselves are still generated.
        $this->assertSame(2, Voucher::count());
    }

    public function test_activating_an_unknown_code_creates_its_product(): void
    {
        $this->actingAs($this->manager())
            ->postJson(route('vouchers.activate'), ['code' => 'GV55555555DD', 'starting_balance' => '25'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Gift Voucher GV55555555DD [bal €25.00]', $this->product('GV55555555DD')->NAME);
    }

    public function test_a_manual_deduct_renames_the_product(): void
    {
        $voucher = $this->voucher();
        $this->service()->sync($voucher);

        $this->actingAs($this->manager())
            ->postJson(route('vouchers.deduct'), ['code' => $voucher->code, 'amount' => '40.00'])
            ->assertOk();

        $this->assertSame('Gift Voucher GV7KQFM2RA9T [bal €2.50]', $this->product('GV7KQFM2RA9T')->NAME);
    }
}
