<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use App\Services\VoucherAdminService;
use App\Services\VoucherPosProductService;
use App\Services\VoucherTillSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * Admin changeover tools (vouchers cycle 4): the service behind the bulk actions.
 */
class VoucherAdminServiceTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-29 12:00:00');
        $this->createVoucherPosTables();
        $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): VoucherAdminService
    {
        return app(VoucherAdminService::class);
    }

    /**
     * A voucher as the old manual process left it (activated by hand, one issue row).
     */
    private function voucher(string $code, string $status = Voucher::STATUS_ACTIVE, float $balance = 10, bool $withProduct = true, ?float $initial = null): Voucher
    {
        $voucher = Voucher::create([
            'code' => $code,
            'initial_value' => $status === Voucher::STATUS_INACTIVE ? null : ($initial ?? $balance),
            'current_balance' => $balance,
            'status' => $status,
        ]);

        if ($status !== Voucher::STATUS_INACTIVE) {
            $voucher->transactions()->create(['type' => 'issue', 'amount' => $initial ?? $balance, 'balance_after' => $initial ?? $balance]);
        }

        if ($withProduct) {
            app(VoucherPosProductService::class)->sync($voucher);
        }

        return $voucher->fresh();
    }

    private function forSale(string $code, float $value = 5): Voucher
    {
        $voucher = Voucher::create(['code' => $code, 'face_value' => $value, 'current_balance' => 0, 'status' => Voucher::STATUS_INACTIVE]);
        app(VoucherPosProductService::class)->sync($voucher);

        return $voucher->fresh();
    }

    private function product(string $code): ?object
    {
        return DB::connection('pos')->table('PRODUCTS')->where('REFERENCE', $code)->first();
    }

    // --- Deactivate / reactivate ---

    public function test_bulk_deactivate_changes_only_active_vouchers(): void
    {
        $active = $this->voucher('GVACTIVE0001');
        $inactive = $this->forSale('GVFORSALE001');
        $deactivated = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);

        $result = $this->service()->deactivate([$active->id, $inactive->id, $deactivated->id], 'Changeover', $this->admin);

        $this->assertSame(1, $result['done']);
        $this->assertSame([
            ['code' => 'GVFORSALE001', 'reason' => 'not active'],
            ['code' => 'GVDEACT00001', 'reason' => 'not active'],
        ], $result['skipped']);

        $this->assertSame(Voucher::STATUS_DEACTIVATED, $active->fresh()->status);
        $tx = $active->transactions()->where('type', VoucherTransaction::TYPE_DEACTIVATE)->sole();
        $this->assertSame($this->admin->id, $tx->user_id);
        $this->assertSame('Changeover', $tx->note);
        $this->assertSame('0.00', $tx->amount);
        $this->assertSame('10.00', $tx->balance_after);
        $this->assertSame('Gift Voucher GVACTIVE0001 [deactivated]', $this->product('GVACTIVE0001')->NAME);
    }

    public function test_deactivating_a_voucher_without_a_till_product_creates_one(): void
    {
        $voucher = $this->voucher('GVNOPRODUCT1', withProduct: false);
        $this->assertNull($this->product('GVNOPRODUCT1'));

        $this->service()->deactivate([$voucher->id], null, $this->admin);

        $product = $this->product('GVNOPRODUCT1');
        $this->assertSame('Gift Voucher GVNOPRODUCT1 [deactivated]', $product->NAME);
        $this->assertEquals(0, $product->PRICESELL);
        $this->assertSame(0, DB::connection('pos')->table('PRODUCTS_CAT')->count());
    }

    public function test_bulk_reactivate(): void
    {
        $deactivated = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);
        $active = $this->voucher('GVACTIVE0001');

        $result = $this->service()->reactivate([$deactivated->id, $active->id], null, $this->admin);

        $this->assertSame(1, $result['done']);
        $this->assertSame([['code' => 'GVACTIVE0001', 'reason' => 'not deactivated']], $result['skipped']);
        $this->assertSame(Voucher::STATUS_ACTIVE, $deactivated->fresh()->status);
        $this->assertSame(1, $deactivated->transactions()->where('type', VoucherTransaction::TYPE_ACTIVATE)->count());
        $this->assertSame('Gift Voucher GVDEACT00001 [bal €10.00]', $this->product('GVDEACT00001')->NAME);
    }

    // --- Delete / restore ---

    public function test_delete_refuses_an_active_voucher_and_deletes_the_rest(): void
    {
        $active = $this->voucher('GVACTIVE0001');
        $inactive = $this->voucher('GVINACTIVE01', Voucher::STATUS_INACTIVE, 0);
        $deactivated = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);
        $exhausted = $this->voucher('GVEXHAUST001', Voucher::STATUS_EXHAUSTED, 0, initial: 20);

        $result = $this->service()->delete([$active->id, $inactive->id, $deactivated->id, $exhausted->id], 'Test labels', $this->admin);

        $this->assertSame(3, $result['done']);
        $this->assertSame([['code' => 'GVACTIVE0001', 'reason' => 'active: deactivate it first']], $result['skipped']);
        $this->assertFalse($active->fresh()->trashed());

        foreach ([$inactive, $deactivated, $exhausted] as $v) {
            $trashed = Voucher::withTrashed()->find($v->id);
            $this->assertTrue($trashed->trashed(), $v->code);
            $tx = VoucherTransaction::where('voucher_id', $v->id)->where('type', VoucherTransaction::TYPE_DELETE)->sole();
            $this->assertSame('Test labels', $tx->note);
            $this->assertSame($this->admin->id, $tx->user_id);
            $this->assertSame("Gift Voucher {$v->code} [deleted]", $this->product($v->code)->NAME);
            $this->assertEquals(0, $this->product($v->code)->PRICESELL);
        }

        // Balance and status are kept, only hidden.
        $this->assertSame('10.00', Voucher::withTrashed()->find($deactivated->id)->current_balance);
    }

    public function test_deleting_a_for_sale_voucher_drops_its_till_price(): void
    {
        $voucher = $this->forSale('GVFORSALE001', 20);
        $this->assertEquals(20, $this->product('GVFORSALE001')->PRICESELL);

        $this->service()->delete([$voucher->id], 'Test', $this->admin);

        $product = $this->product('GVFORSALE001');
        $this->assertSame('Gift Voucher GVFORSALE001 [deleted]', $product->NAME);
        $this->assertEquals(0, $product->PRICESELL);
    }

    public function test_delete_marks_the_vouchers_till_exceptions_reviewed(): void
    {
        $voucher = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);
        $other = $this->voucher('GVOTHER00001', Voucher::STATUS_DEACTIVATED);
        foreach ([$voucher, $other] as $i => $v) {
            VoucherTillRedemption::create([
                'pos_ticket_id' => 'ticket-'.$i, 'pos_product_id' => $v->pos_product_id, 'voucher_code' => $v->code,
                'ticket_number' => 430000 + $i, 'sold_at' => now(), 'status' => VoucherTillRedemption::STATUS_INACTIVE,
                'voucher_id' => $v->id,
            ]);
        }

        $this->service()->delete([$voucher->id], 'Test', $this->admin);

        $row = VoucherTillRedemption::where('voucher_id', $voucher->id)->sole();
        $this->assertNotNull($row->reviewed_at);
        $this->assertSame($this->admin->id, $row->reviewed_by);
        $this->assertNull(VoucherTillRedemption::where('voucher_id', $other->id)->sole()->reviewed_at);
    }

    public function test_deleting_twice_skips(): void
    {
        $voucher = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);
        $this->service()->delete([$voucher->id], 'Test', $this->admin);

        $result = $this->service()->delete([$voucher->id], 'Test', $this->admin);

        $this->assertSame(0, $result['done']);
        $this->assertSame([['code' => 'GVDEACT00001', 'reason' => 'already deleted']], $result['skipped']);
        $this->assertSame(1, VoucherTransaction::where('voucher_id', $voucher->id)->where('type', 'delete')->count());
    }

    public function test_restore_brings_status_balance_and_product_back(): void
    {
        $deactivated = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED, 12.5);
        $forSale = $this->forSale('GVFORSALE001', 20);
        $notDeleted = $this->voucher('GVACTIVE0001');
        $this->service()->delete([$deactivated->id, $forSale->id], 'Test', $this->admin);

        $result = $this->service()->restore([$deactivated->id, $forSale->id, $notDeleted->id], 'Oops', $this->admin);

        $this->assertSame(2, $result['done']);
        $this->assertSame([['code' => 'GVACTIVE0001', 'reason' => 'not deleted']], $result['skipped']);

        $v = $deactivated->fresh();
        $this->assertFalse($v->trashed());
        $this->assertSame(Voucher::STATUS_DEACTIVATED, $v->status);
        $this->assertSame('12.50', $v->current_balance);
        $this->assertSame('Gift Voucher GVDEACT00001 [deactivated]', $this->product('GVDEACT00001')->NAME);
        $this->assertSame(1, $v->transactions()->where('type', VoucherTransaction::TYPE_RESTORE)->count());

        // A for-sale voucher is priced again.
        $this->assertSame('Gift Voucher GVFORSALE001 [for sale €20.00]', $this->product('GVFORSALE001')->NAME);
        $this->assertEquals(20, $this->product('GVFORSALE001')->PRICESELL);
    }

    public function test_a_pos_outage_still_completes_the_local_change(): void
    {
        $voucher = $this->voucher('GVACTIVE0001');
        DB::connection('pos')->getSchemaBuilder()->drop('PRODUCTS');

        $result = $this->service()->deactivate([$voucher->id], null, $this->admin);

        $this->assertSame(1, $result['done']);
        $this->assertSame(Voucher::STATUS_DEACTIVATED, $voucher->fresh()->status);
    }

    public function test_one_bad_id_does_not_stop_the_others(): void
    {
        $a = $this->voucher('GVACTIVE0001');
        $b = $this->voucher('GVACTIVE0002');

        $result = $this->service()->deactivate([$a->id, 99999, $b->id], null, $this->admin);

        $this->assertSame(2, $result['done']);
        $this->assertSame([['code' => '#99999', 'reason' => 'not found']], $result['skipped']);
        $this->assertSame(Voucher::STATUS_DEACTIVATED, $b->fresh()->status);
    }

    // --- Make for sale ---

    public function test_an_untouched_active_voucher_is_made_for_sale(): void
    {
        $voucher = $this->voucher('GVACTIVE0001', balance: 10, withProduct: false);

        $result = $this->service()->makeForSale([$voucher->id], 'Changeover', $this->admin);

        $this->assertSame(1, $result['done']);
        $v = $voucher->fresh();
        $this->assertSame(Voucher::STATUS_INACTIVE, $v->status);
        $this->assertSame('10.00', $v->face_value);
        $this->assertSame('0.00', $v->current_balance);
        $this->assertNull($v->initial_value);
        $this->assertTrue($v->isForSale());

        $tx = $v->transactions()->where('type', VoucherTransaction::TYPE_FOR_SALE)->sole();
        $this->assertSame('10.00', $tx->amount);
        $this->assertSame('0.00', $tx->balance_after);
        $this->assertSame($this->admin->id, $tx->user_id);
        $this->assertSame('Changeover', $tx->note);

        // The till product did not exist and was created, priced to sell.
        $product = $this->product('GVACTIVE0001');
        $this->assertSame('Gift Voucher GVACTIVE0001 [for sale €10.00]', $product->NAME);
        $this->assertEquals(10, $product->PRICESELL);
    }

    public function test_an_untouched_deactivated_voucher_is_made_for_sale(): void
    {
        $voucher = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED, 50);

        $this->assertSame(1, $this->service()->makeForSale([$voucher->id], null, $this->admin)['done']);
        $this->assertSame('50.00', $voucher->fresh()->face_value);
        $this->assertEquals(50, $this->product('GVDEACT00001')->PRICESELL);
    }

    public function test_make_for_sale_skips_vouchers_that_do_not_qualify(): void
    {
        $spent = $this->voucher('GVSPENT00001', balance: 10);
        $spent->transactions()->create(['type' => 'deduct', 'amount' => 0.01, 'balance_after' => 9.99]);
        $zero = $this->voucher('GVZERO000001', balance: 0, initial: 0);
        $exhausted = $this->voucher('GVEXHAUST001', Voucher::STATUS_EXHAUSTED, 0, initial: 20);
        $forSale = $this->forSale('GVFORSALE001');
        $differs = $this->voucher('GVDIFFERS001', balance: 8, initial: 10);
        $deleted = $this->voucher('GVDELETED001', Voucher::STATUS_DEACTIVATED);
        $deleted->delete();

        $result = $this->service()->makeForSale(
            [$spent->id, $zero->id, $exhausted->id, $forSale->id, $differs->id, $deleted->id], null, $this->admin
        );

        $this->assertSame(0, $result['done']);
        $this->assertSame([
            ['code' => 'GVSPENT00001', 'reason' => 'has been spent from'],
            ['code' => 'GVZERO000001', 'reason' => 'no balance'],
            ['code' => 'GVEXHAUST001', 'reason' => 'not active or deactivated'],
            ['code' => 'GVFORSALE001', 'reason' => 'not active or deactivated'],
            ['code' => 'GVDIFFERS001', 'reason' => 'balance differs from its initial value'],
            ['code' => 'GVDELETED001', 'reason' => 'deleted'],
        ], $result['skipped']);
        $this->assertSame('10.00', $spent->fresh()->current_balance);
    }

    public function test_the_whole_path_make_for_sale_then_sell_then_redeem(): void
    {
        $voucher = $this->voucher('GVACTIVE0001', balance: 20);
        $this->service()->makeForSale([$voucher->id], null, $this->admin);
        $voucher->refresh();

        $goods = $this->posGoods();
        $this->posSale(430500, [
            ['product' => $goods, 'price' => 10, 'tax' => '001'],
            ['product' => $voucher->pos_product_id, 'price' => 20],
        ], [['payment' => 'magcard', 'total' => 32.30]], now()->subMinute());

        $counts = app(VoucherTillSyncService::class)->sync();

        $this->assertSame(1, $counts['activated']);
        $voucher->refresh();
        $this->assertSame(Voucher::STATUS_ACTIVE, $voucher->status);
        $this->assertSame('20.00', $voucher->current_balance);
        $this->assertSame('20.00', $voucher->initial_value);
        $this->assertEquals(0, $this->product('GVACTIVE0001')->PRICESELL);

        $this->posSale(430501, [
            ['product' => $goods, 'price' => 10, 'tax' => '001'],
            ['product' => $voucher->pos_product_id, 'price' => 0],
        ], [['payment' => 'paperin', 'total' => 7]], now()->subSeconds(30));

        $this->assertSame(1, app(VoucherTillSyncService::class)->sync()['applied']);
        $this->assertSame('13.00', $voucher->fresh()->current_balance);
    }
}
