<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use App\Services\VoucherPosProductService;
use App\Services\VoucherTillSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * Till-driven voucher redemption (cycle 28): tickets carrying a voucher product
 * have the receipt's paperin (Voucher tender) deducted from that voucher.
 */
class VoucherTillSyncServiceTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    private string $goods;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-27 12:00:00');
        $this->createVoucherPosTables();
        $this->goods = $this->posGoods();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sync(?Carbon $since = null): array
    {
        return app(VoucherTillSyncService::class)->sync($since);
    }

    /**
     * A voucher with its POS product, as the backfill leaves it.
     */
    private function voucher(float $balance = 50, string $status = Voucher::STATUS_ACTIVE, string $code = 'GV7KQFM2RA9T'): Voucher
    {
        $voucher = Voucher::create([
            'code' => $code,
            'initial_value' => $balance,
            'current_balance' => $balance,
            'status' => $status,
        ]);
        app(VoucherPosProductService::class)->sync($voucher);

        return $voucher->fresh();
    }

    /**
     * Goods line, then the voucher line(s), paid as given.
     *
     * @param  array<int, Voucher|string>  $vouchers  vouchers or raw POS product ids
     * @param  array<int, array{payment: string, total: float}>  $payments
     */
    private function sale(int $ticketNo, array $vouchers, array $payments, ?Carbon $at = null, int $type = 0): string
    {
        $lines = [['product' => $this->goods, 'price' => 40, 'units' => 1, 'tax' => '001']];
        foreach ($vouchers as $v) {
            $lines[] = ['product' => $v instanceof Voucher ? $v->pos_product_id : $v, 'price' => 0];
        }

        return $this->posSale($ticketNo, $lines, $payments, $at ?? now()->subMinutes(2), $type);
    }

    private function posName(Voucher $voucher): string
    {
        return DB::connection('pos')->table('PRODUCTS')->where('ID', $voucher->pos_product_id)->value('NAME');
    }

    public function test_voucher_tender_is_deducted(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430025, [$voucher], [['payment' => 'paperin', 'total' => 30], ['payment' => 'cash', 'total' => 19.20]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['tickets']);
        $this->assertSame(1, $counts['applied']);

        $voucher->refresh();
        $this->assertSame('20.00', $voucher->current_balance);
        $this->assertSame(Voucher::STATUS_ACTIVE, $voucher->status);

        $tx = $voucher->transactions()->first();
        $this->assertSame(VoucherTransaction::TYPE_DEDUCT, $tx->type);
        $this->assertSame(VoucherTransaction::SOURCE_TILL, $tx->source);
        $this->assertSame('30.00', $tx->amount);
        $this->assertSame('20.00', $tx->balance_after);
        $this->assertNull($tx->user_id);
        $this->assertSame('Till #430025', $tx->note);

        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_APPLIED, $row->status);
        $this->assertSame(430025, $row->ticket_number);
        $this->assertSame('30.00', $row->voucher_tender);
        $this->assertSame('30.00', $row->amount_deducted);
        $this->assertSame('0.00', $row->shortfall);
        $this->assertSame('49.20', $row->ticket_total); // 40 × 1.23
        $this->assertSame($tx->id, $row->voucher_transaction_id);
        $this->assertSame($voucher->id, $row->voucher_id);
        $this->assertSame('GV7KQFM2RA9T', $row->voucher_code);

        $this->assertSame('Gift Voucher GV7KQFM2RA9T [bal €20.00]', $this->posName($voucher));
    }

    public function test_tender_above_the_balance_deducts_to_zero_and_flags_partial(): void
    {
        $voucher = $this->voucher(20);
        $this->sale(430026, [$voucher], [['payment' => 'paperin', 'total' => 30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['partial']);
        $voucher->refresh();
        $this->assertSame('0.00', $voucher->current_balance);
        $this->assertSame(Voucher::STATUS_EXHAUSTED, $voucher->status);

        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_PARTIAL, $row->status);
        $this->assertSame('20.00', $row->amount_deducted);
        $this->assertSame('10.00', $row->shortfall);
        $this->assertSame('Till #430026 · short €10.00', $voucher->transactions()->first()->note);

        $this->assertSame('Gift Voucher GV7KQFM2RA9T [€0.00 used up]', $this->posName($voucher));
    }

    public function test_voucher_scanned_but_paid_otherwise_is_no_tender(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430027, [$voucher], [['payment' => 'cash', 'total' => 49.20]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['no_tender']);
        $this->assertSame('50.00', $voucher->fresh()->current_balance);
        $this->assertSame(0, $voucher->transactions()->count());
        $this->assertSame(VoucherTillRedemption::STATUS_NO_TENDER, VoucherTillRedemption::sole()->status);
    }

    public function test_vouchers_that_are_not_active_are_left_alone(): void
    {
        $inactive = $this->voucher(0, Voucher::STATUS_INACTIVE, 'GV22222222AA');
        $deactivated = $this->voucher(15, Voucher::STATUS_DEACTIVATED, 'GV33333333BB');
        $exhausted = $this->voucher(0, Voucher::STATUS_EXHAUSTED, 'GV44444444CC');

        $this->sale(1, [$inactive], [['payment' => 'paperin', 'total' => 10]]);
        $this->sale(2, [$deactivated], [['payment' => 'paperin', 'total' => 10]]);
        $this->sale(3, [$exhausted], [['payment' => 'paperin', 'total' => 10]]);

        $counts = $this->sync();

        $this->assertSame(3, $counts['inactive']);
        $this->assertSame(0, VoucherTransaction::count());
        $this->assertSame('15.00', $deactivated->fresh()->current_balance);
        $this->assertSame(Voucher::STATUS_DEACTIVATED, $deactivated->fresh()->status);
        $this->assertSame(3, VoucherTillRedemption::where('status', VoucherTillRedemption::STATUS_INACTIVE)->count());
    }

    public function test_a_voucher_product_with_no_voucher_is_unknown(): void
    {
        // A product in the voucher category the app has no voucher for.
        $category = app(VoucherPosProductService::class)->categoryId();
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'orphan-product',
            'NAME' => 'Gift Voucher GV99999999ZZ [bal €5.00]',
            'CODE' => 'GV99999999ZZ',
            'REFERENCE' => 'GV99999999ZZ',
            'CATEGORY' => $category,
            'TAXCAT' => '000',
        ]);
        $this->sale(430028, ['orphan-product'], [['payment' => 'paperin', 'total' => 5]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['unknown']);
        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_UNKNOWN, $row->status);
        $this->assertSame('GV99999999ZZ', $row->voucher_code);
        $this->assertNull($row->voucher_id);
    }

    public function test_a_missing_product_link_is_repaired_by_code(): void
    {
        $voucher = $this->voucher(50);
        $productId = $voucher->pos_product_id;
        $voucher->forceFill(['pos_product_id' => null])->saveQuietly();

        $this->sale(430029, [$productId], [['payment' => 'paperin', 'total' => 5]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['applied']);
        $voucher->refresh();
        $this->assertSame($productId, $voucher->pos_product_id);
        $this->assertSame('45.00', $voucher->current_balance);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430030, [$voucher], [['payment' => 'paperin', 'total' => 30]]);

        $this->sync();
        $second = $this->sync();

        // The ticket is inside the overlap window, so it is read again and skipped.
        $this->assertSame(1, $second['tickets']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(0, $second['applied']);
        $this->assertSame('20.00', $voucher->fresh()->current_balance);
        $this->assertSame(1, VoucherTillRedemption::count());
        $this->assertSame(1, VoucherTransaction::count());
    }

    public function test_a_double_scan_counts_once(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430031, [$voucher, $voucher], [['payment' => 'paperin', 'total' => 30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['applied']);
        $this->assertSame(1, VoucherTillRedemption::count());
        $this->assertSame('20.00', $voucher->fresh()->current_balance);
    }

    public function test_two_vouchers_split_the_tender_in_line_order(): void
    {
        $first = $this->voucher(20, code: 'GV22222222AA');
        $second = $this->voucher(15, code: 'GV33333333BB');
        $this->sale(430032, [$first, $second], [['payment' => 'paperin', 'total' => 40]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['applied']);
        $this->assertSame(1, $counts['partial']);

        $this->assertSame('0.00', $first->fresh()->current_balance);
        $this->assertSame('0.00', $second->fresh()->current_balance);

        $rowFirst = VoucherTillRedemption::where('voucher_id', $first->id)->sole();
        $rowSecond = VoucherTillRedemption::where('voucher_id', $second->id)->sole();
        $this->assertSame(VoucherTillRedemption::STATUS_APPLIED, $rowFirst->status);
        $this->assertSame('20.00', $rowFirst->amount_deducted);
        $this->assertSame(VoucherTillRedemption::STATUS_PARTIAL, $rowSecond->status);
        $this->assertSame('15.00', $rowSecond->amount_deducted);
        $this->assertSame('5.00', $rowSecond->shortfall);
    }

    public function test_two_vouchers_with_enough_balance_are_both_applied(): void
    {
        $first = $this->voucher(20, code: 'GV22222222AA');
        $second = $this->voucher(15, code: 'GV33333333BB');
        $this->sale(430033, [$first, $second], [['payment' => 'paperin', 'total' => 25]]);

        $counts = $this->sync();

        $this->assertSame(2, $counts['applied']);
        $this->assertSame('0.00', $first->fresh()->current_balance);
        $this->assertSame('10.00', $second->fresh()->current_balance);
    }

    public function test_a_refund_ticket_is_recorded_not_reversed(): void
    {
        $voucher = $this->voucher(20);
        $this->sale(430034, [$voucher], [['payment' => 'paperin', 'total' => -10]], type: 1);

        $counts = $this->sync();

        $this->assertSame(1, $counts['refund']);
        $this->assertSame('20.00', $voucher->fresh()->current_balance);
        $this->assertSame(0, VoucherTransaction::count());
        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_REFUND, $row->status);
        $this->assertSame(1, $row->ticket_type);
        $this->assertSame($voucher->id, $row->voucher_id);
    }

    public function test_the_watermark_ignores_tickets_before_the_overlap(): void
    {
        $voucher = $this->voucher(100);
        $this->sale(1, [$voucher], [['payment' => 'paperin', 'total' => 1]], now()->subMinutes(5));
        $this->sync();

        // Latest recorded sale is 11:55; overlap 15 min → since 11:40.
        $this->sale(2, [$voucher], [['payment' => 'paperin', 'total' => 2]], now()->subMinutes(30)); // 11:30
        $this->sale(3, [$voucher], [['payment' => 'paperin', 'total' => 3]], now()->subMinutes(10)); // 11:50

        $counts = $this->sync();

        $this->assertSame(1, $counts['applied']);
        $this->assertSame([1, 3], VoucherTillRedemption::orderBy('ticket_number')->pluck('ticket_number')->all());
        $this->assertSame('96.00', $voucher->fresh()->current_balance);
    }

    public function test_an_empty_table_looks_back_the_configured_hours(): void
    {
        $voucher = $this->voucher(100);
        $this->sale(1, [$voucher], [['payment' => 'paperin', 'total' => 1]], now()->subHours(25));
        $this->sale(2, [$voucher], [['payment' => 'paperin', 'total' => 2]], now()->subHours(23));

        $counts = $this->sync();

        $this->assertSame(1, $counts['tickets']);
        $this->assertSame([2], VoucherTillRedemption::pluck('ticket_number')->all());
    }

    public function test_since_overrides_the_watermark(): void
    {
        $voucher = $this->voucher(100);
        $this->sale(1, [$voucher], [['payment' => 'paperin', 'total' => 1]], now()->subHours(48));

        $counts = $this->sync(now()->subHours(72));

        $this->assertSame(1, $counts['applied']);
    }

    public function test_tender_is_rounded_to_cents(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430035, [$voucher], [['payment' => 'paperin', 'total' => 10.005]]);

        $this->sync();

        $row = VoucherTillRedemption::sole();
        $this->assertSame('10.01', $row->voucher_tender);
        $this->assertSame('10.01', $row->amount_deducted);
        $this->assertSame('39.99', $voucher->fresh()->current_balance);
    }

    public function test_ordinary_sales_are_ignored(): void
    {
        $this->voucher(50);
        $this->posSale(430036, [['product' => $this->goods, 'price' => 2]], [['payment' => 'paperin', 'total' => 2]], now()->subMinute());

        $counts = $this->sync();

        $this->assertSame(0, $counts['tickets']);
        $this->assertSame(0, VoucherTillRedemption::count());
    }

    public function test_sync_if_due_is_throttled_and_can_be_switched_off(): void
    {
        $service = app(VoucherTillSyncService::class);

        $this->assertIsArray($service->syncIfDue());
        $this->assertNull($service->syncIfDue()); // inside the throttle window

        config(['vouchers.sync.on_lookup' => false]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertNull($service->syncIfDue());
    }

    public function test_sync_if_due_swallows_a_pos_outage(): void
    {
        DB::connection('pos')->getSchemaBuilder()->drop('TICKETS');

        $this->assertNull(app(VoucherTillSyncService::class)->syncIfDue());
    }
}
