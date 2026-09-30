<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use App\Services\VoucherPosProductService;
use App\Services\VoucherSyncHeartbeat;
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

    // --- Heartbeat (vouchers cycle 2) ---

    private function lastBeat(): ?array
    {
        return app(VoucherSyncHeartbeat::class)->read()['last'];
    }

    public function test_a_successful_sync_records_the_heartbeat_with_its_counts_and_source(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430025, [$voucher], [['payment' => 'paperin', 'total' => 30]]);

        $counts = app(VoucherTillSyncService::class)->sync(null, VoucherSyncHeartbeat::SOURCE_SCHEDULE);

        $beat = $this->lastBeat();
        $this->assertTrue($beat['ok']);
        $this->assertSame('schedule', $beat['source']);
        $this->assertSame($counts, $beat['counts']);
        $this->assertSame(1, $beat['counts']['applied']);
        $this->assertSame(0, $beat['counts']['errors']);
    }

    public function test_an_unreachable_till_records_a_failed_heartbeat_and_still_throws(): void
    {
        DB::connection('pos')->getSchemaBuilder()->drop('TICKETS');

        try {
            $this->sync();
            $this->fail('sync() should rethrow');
        } catch (\Illuminate\Database\QueryException $e) {
            // expected
        }

        $beat = $this->lastBeat();
        $this->assertFalse($beat['ok']);
        $this->assertSame('command', $beat['source']);
        $this->assertNotEmpty($beat['error']);
        $this->assertNull(app(VoucherSyncHeartbeat::class)->read()['last_ok_at']);
    }

    public function test_a_ticket_that_fails_inside_the_loop_counts_as_an_error_but_the_run_is_ok(): void
    {
        $voucher = $this->voucher(50);
        $this->sale(430025, [$voucher], [['payment' => 'paperin', 'total' => 30]]);
        // The ticket-total query joins TAXES, so dropping it fails this ticket
        // inside the loop, after the ticket list has been read.
        DB::connection('pos')->getSchemaBuilder()->drop('TAXES');

        $counts = $this->sync();

        $this->assertSame(1, $counts['tickets']);
        $this->assertSame(1, $counts['errors']);
        $this->assertSame(0, $counts['applied']);
        $this->assertSame('50.00', $voucher->fresh()->current_balance);

        $beat = $this->lastBeat();
        $this->assertTrue($beat['ok']);
        $this->assertSame(1, $beat['counts']['errors']);
    }

    public function test_sync_if_due_records_the_given_source(): void
    {
        app(VoucherTillSyncService::class)->syncIfDue(VoucherSyncHeartbeat::SOURCE_ACTIVITY);

        $this->assertSame('activity', $this->lastBeat()['source']);
    }

    public function test_the_command_records_schedule_only_with_the_flag(): void
    {
        $this->artisan('vouchers:sync-till')->assertExitCode(0);
        $this->assertSame('command', $this->lastBeat()['source']);
        $this->assertNull(app(VoucherSyncHeartbeat::class)->read()['last_scheduled_at']);

        $this->artisan('vouchers:sync-till --scheduled')->assertExitCode(0);
        $this->assertSame('schedule', $this->lastBeat()['source']);
        $this->assertFalse(app(VoucherSyncHeartbeat::class)->read()['scheduler_stale']);
    }

    // --- Vouchers cycle 3: selling a voucher at the till activates it ---

    /**
     * An unsold voucher with a value, and its till product priced at that value.
     */
    private function forSale(float $value = 20, string $code = 'GVSALE000001'): Voucher
    {
        $voucher = Voucher::create([
            'code' => $code,
            'face_value' => $value,
            'current_balance' => 0,
            'status' => Voucher::STATUS_INACTIVE,
        ]);
        app(VoucherPosProductService::class)->sync($voucher);

        return $voucher->fresh();
    }

    /**
     * A ticket with a goods line, then the given voucher lines (price at the
     * voucher's face value unless given, units 1 unless given).
     *
     * @param  array<int, array{voucher: Voucher|string, price?: float, units?: float}>  $voucherLines
     * @param  array<int, array{payment: string, total: float}>  $payments
     */
    private function ticket(int $ticketNo, array $voucherLines, array $payments, int $type = 0, bool $goods = true): string
    {
        $lines = $goods ? [['product' => $this->goods, 'price' => 10, 'units' => $type === 1 ? -1 : 1, 'tax' => '001']] : [];
        foreach ($voucherLines as $l) {
            $v = $l['voucher'];
            $lines[] = [
                'product' => $v instanceof Voucher ? $v->pos_product_id : $v,
                'price' => $l['price'] ?? ($v instanceof Voucher ? (float) $v->face_value : 0),
                'units' => $l['units'] ?? 1,
                'tax' => '000',
            ];
        }

        return $this->posSale($ticketNo, $lines, $payments, now()->subMinutes(2), $type);
    }

    private function posPrice(Voucher $voucher): float
    {
        return (float) DB::connection('pos')->table('PRODUCTS')->where('ID', $voucher->pos_product_id)->value('PRICESELL');
    }

    public function test_selling_a_voucher_for_its_value_activates_it(): void
    {
        $voucher = $this->forSale(20);
        $this->assertEquals(20, $this->posPrice($voucher));
        $this->ticket(430100, [['voucher' => $voucher]], [['payment' => 'magcard', 'total' => 32.30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['activated']);
        $voucher->refresh();
        $this->assertSame(Voucher::STATUS_ACTIVE, $voucher->status);
        $this->assertSame('20.00', $voucher->initial_value);
        $this->assertSame('20.00', $voucher->current_balance);

        $tx = $voucher->transactions()->sole();
        $this->assertSame(VoucherTransaction::TYPE_ISSUE, $tx->type);
        $this->assertSame(VoucherTransaction::SOURCE_TILL, $tx->source);
        $this->assertSame('20.00', $tx->amount);
        $this->assertSame('Till #430100', $tx->note);
        $this->assertNull($tx->user_id);

        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_ACTIVATED, $row->status);
        $this->assertSame('20.00', $row->sale_amount);
        $this->assertSame('0.00', $row->amount_deducted);
        $this->assertSame($tx->id, $row->voucher_transaction_id);

        $this->assertEquals(0, $this->posPrice($voucher));
        $this->assertSame('Gift Voucher GVSALE000001 [bal €20.00]', $this->posName($voucher));
    }

    public function test_quantity_above_one_activates_nothing(): void
    {
        $voucher = $this->forSale(20);
        $this->ticket(430101, [['voucher' => $voucher, 'units' => 2]], [['payment' => 'cash', 'total' => 52.30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['sale_flagged']);
        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->fresh()->status);
        $this->assertEquals(20, $this->posPrice($voucher));
        $row = VoucherTillRedemption::sole();
        $this->assertSame('40.00', $row->sale_amount);
        $this->assertSame('Quantity 2 on one voucher (charged €40.00). Not activated: each voucher is scanned itself.', $row->note);
    }

    public function test_the_same_voucher_on_two_lines_activates_nothing(): void
    {
        $voucher = $this->forSale(20);
        $this->ticket(430102, [['voucher' => $voucher], ['voucher' => $voucher]], [['payment' => 'cash', 'total' => 52.30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['sale_flagged']);
        $this->assertSame(1, VoucherTillRedemption::count());
        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->fresh()->status);
    }

    public function test_a_sale_paid_with_the_free_tender_activates_nothing(): void
    {
        $voucher = $this->forSale(20);
        $this->ticket(430103, [['voucher' => $voucher]], [['payment' => 'free', 'total' => 32.30]]);

        $this->sync();

        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_SALE_FLAGGED, $row->status);
        $this->assertSame('Paid with the Free tender. Not activated.', $row->note);
        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->fresh()->status);
    }

    public function test_a_price_edited_at_the_till_activates_nothing(): void
    {
        $voucher = $this->forSale(20);
        $this->ticket(430104, [['voucher' => $voucher, 'price' => 15]], [['payment' => 'cash', 'total' => 27.30]]);

        $this->sync();

        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_SALE_FLAGGED, $row->status);
        $this->assertSame("Charged €15.00 but the voucher's value is €20.00. Not activated.", $row->note);
        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->fresh()->status);
    }

    public function test_selling_an_active_or_deactivated_voucher_adds_nothing(): void
    {
        $active = $this->voucher(15, Voucher::STATUS_ACTIVE, 'GVACTIVE0001');
        $deactivated = $this->voucher(15, Voucher::STATUS_DEACTIVATED, 'GVDEACT00001');
        $this->ticket(430105, [['voucher' => $active, 'price' => 20]], [['payment' => 'cash', 'total' => 32.30]]);
        $this->ticket(430106, [['voucher' => $deactivated, 'price' => 20]], [['payment' => 'cash', 'total' => 32.30]]);

        $counts = $this->sync();

        $this->assertSame(2, $counts['sale_flagged']);
        $this->assertSame('15.00', $active->fresh()->current_balance);
        $this->assertSame(Voucher::STATUS_DEACTIVATED, $deactivated->fresh()->status);
        $this->assertSame(0, VoucherTransaction::count());
        $this->assertSame(
            'Charged €20.00 but the voucher was already active (balance €15.00). Nothing was added.',
            VoucherTillRedemption::where('ticket_number', 430105)->value('note')
        );
    }

    public function test_a_refunded_voucher_sale_is_flagged_and_the_voucher_untouched(): void
    {
        $voucher = $this->forSale(20);
        $this->ticket(430107, [['voucher' => $voucher, 'units' => -1]], [['payment' => 'cashrefund', 'total' => -32.30]], type: 1);

        $counts = $this->sync();

        $this->assertSame(1, $counts['refund']);
        $row = VoucherTillRedemption::sole();
        $this->assertSame(VoucherTillRedemption::STATUS_REFUND, $row->status);
        $this->assertSame('20.00', $row->sale_amount);
        $this->assertSame('Refund of a voucher sale (€20.00). Deactivate the voucher if it was handed back.', $row->note);
        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->fresh()->status);
    }

    public function test_an_unknown_product_sold_at_a_price_is_unknown_with_the_amount(): void
    {
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'orphan-sale',
            'NAME' => 'Gift Voucher GV99999999ZZ [for sale €30.00]',
            'CODE' => 'GV99999999ZZ',
            'REFERENCE' => 'GV99999999ZZ',
            'CATEGORY' => app(VoucherPosProductService::class)->categoryId(),
            'TAXCAT' => '000',
            'PRICESELL' => 30,
        ]);
        $this->ticket(430108, [['voucher' => 'orphan-sale', 'price' => 30]], [['payment' => 'cash', 'total' => 42.30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['unknown']);
        $row = VoucherTillRedemption::sole();
        $this->assertSame('30.00', $row->sale_amount);
        $this->assertSame('No voucher with this code in the app.', $row->note);
    }

    public function test_a_sale_and_a_redemption_on_one_ticket(): void
    {
        $sold = $this->forSale(20, 'GVSALE000001');
        $redeemed = $this->voucher(50, code: 'GVREDEEM0001');
        $this->ticket(430109, [['voucher' => $sold], ['voucher' => $redeemed]], [
            ['payment' => 'magcard', 'total' => 22.30],
            ['payment' => 'paperin', 'total' => 10],
        ]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['activated']);
        $this->assertSame(1, $counts['applied']);
        $this->assertSame('20.00', $sold->fresh()->current_balance);
        $this->assertSame('40.00', $redeemed->fresh()->current_balance);
    }

    public function test_a_sale_does_not_count_as_the_last_redemption_line(): void
    {
        // The redemption comes first and the sale last: the redemption must still be `partial`.
        $redeemed = $this->voucher(5, code: 'GVREDEEM0001');
        $sold = $this->forSale(20, 'GVSALE000001');
        $this->ticket(430110, [['voucher' => $redeemed], ['voucher' => $sold]], [
            ['payment' => 'paperin', 'total' => 8],
            ['payment' => 'cash', 'total' => 24.30],
        ]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['partial']);
        $this->assertSame(1, $counts['activated']);
        $row = VoucherTillRedemption::where('voucher_id', $redeemed->id)->sole();
        $this->assertSame('3.00', $row->shortfall);
    }

    public function test_a_voucher_bought_with_a_voucher(): void
    {
        $sold = $this->forSale(20, 'GVSALE000001');
        $redeemed = $this->voucher(50, code: 'GVREDEEM0001');
        $this->ticket(430111, [['voucher' => $sold], ['voucher' => $redeemed]], [['payment' => 'paperin', 'total' => 20]], goods: false);

        $counts = $this->sync();

        $this->assertSame(1, $counts['activated']);
        $this->assertSame(1, $counts['applied']);
        $this->assertSame('20.00', $sold->fresh()->current_balance);
        $this->assertSame('30.00', $redeemed->fresh()->current_balance);
    }

    public function test_after_activation_the_label_redeems_normally(): void
    {
        $voucher = $this->forSale(20);
        $this->ticket(430112, [['voucher' => $voucher]], [['payment' => 'cash', 'total' => 32.30]]);
        $this->sync();

        $second = $this->sync();
        $this->assertSame(0, $second['activated']);
        $this->assertSame(1, $second['skipped']);

        // Price is now 0: a later scan is a €0.00 redemption line.
        $this->assertEquals(0, $this->posPrice($voucher));
        $this->ticket(430113, [['voucher' => $voucher->fresh(), 'price' => $this->posPrice($voucher)]], [['payment' => 'paperin', 'total' => 5], ['payment' => 'cash', 'total' => 7.30]]);
        $counts = $this->sync();

        $this->assertSame(1, $counts['applied']);
        $this->assertSame('15.00', $voucher->fresh()->current_balance);
        $this->assertSame('Gift Voucher GVSALE000001 [bal €15.00]', $this->posName($voucher));
    }

    public function test_the_counts_include_sales(): void
    {
        $counts = $this->sync();

        $this->assertArrayHasKey('activated', $counts);
        $this->assertArrayHasKey('sale_flagged', $counts);
    }

    // --- Vouchers cycle 4: deleted vouchers at the till ---

    public function test_a_deleted_vouchers_label_with_a_voucher_tender_deducts_nothing(): void
    {
        $voucher = $this->voucher(50);
        $voucher->update(['status' => Voucher::STATUS_DEACTIVATED]);
        $voucher->delete();
        $this->sale(430600, [$voucher], [['payment' => 'paperin', 'total' => 10]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['inactive']);
        $row = VoucherTillRedemption::sole();
        $this->assertSame('Voucher was deleted.', $row->note);
        $this->assertSame($voucher->id, $row->voucher_id);
        $this->assertSame('50.00', Voucher::withTrashed()->find($voucher->id)->current_balance);
        $this->assertSame(0, VoucherTransaction::where('type', 'deduct')->count());
    }

    public function test_a_deleted_for_sale_voucher_sold_at_the_till_is_not_activated(): void
    {
        $voucher = $this->forSale(20);
        $voucher->delete();
        // Sold before the till picked up the [deleted] price of 0.
        $this->ticket(430601, [['voucher' => $voucher, 'price' => 20]], [['payment' => 'cash', 'total' => 32.30]]);

        $counts = $this->sync();

        $this->assertSame(1, $counts['sale_flagged']);
        $this->assertSame('Charged €20.00 but the voucher was deleted. Nothing was activated.', VoucherTillRedemption::sole()->note);
        $this->assertSame(Voucher::STATUS_INACTIVE, Voucher::withTrashed()->find($voucher->id)->status);
    }

    public function test_a_restored_voucher_redeems_normally(): void
    {
        $voucher = $this->voucher(50);
        $voucher->delete();
        $voucher->restore();
        $this->sale(430602, [$voucher], [['payment' => 'paperin', 'total' => 10]]);

        $this->assertSame(1, $this->sync()['applied']);
        $this->assertSame('40.00', $voucher->fresh()->current_balance);
    }
}
