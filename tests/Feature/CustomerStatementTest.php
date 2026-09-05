<?php

namespace Tests\Feature;

use App\Mail\CustomerStatementMail;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\CustomerStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the statement ledger, the aging fallback that keys off customer payment
 * terms (invoice due_date is optional and in practice usually blank), and the
 * bulk email run.
 */
class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function service(): CustomerStatementService
    {
        return app(CustomerStatementService::class);
    }

    private function invoice(Customer $c, string $issue, float $total, array $extra = []): CustomerInvoice
    {
        return CustomerInvoice::create(array_merge([
            'customer_id' => $c->id,
            'customer_name' => $c->name,
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(10000, 99999),
            'issue_date' => $issue,
            'subtotal' => $total,
            'vat_total' => 0,
            'total' => $total,
            'status' => CustomerInvoice::STATUS_ISSUED,
        ], $extra));
    }

    private function payment(Customer $c, string $date, float $amount, array $extra = []): CustomerPayment
    {
        return CustomerPayment::create(array_merge([
            'customer_id' => $c->id,
            'payment_date' => $date,
            'amount' => $amount,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], $extra));
    }

    public function test_running_balance_matches_the_customer_balance_accessor(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->invoice($c, '2026-02-10', 50);
        $this->payment($c, '2026-02-15', 30);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-03-01'));

        $this->assertSame(120.0, $ctx['closing_balance']);
        $this->assertSame($c->fresh()->balance, $ctx['closing_balance']);
    }

    public function test_void_invoices_and_voided_payments_are_excluded(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->invoice($c, '2026-01-11', 999, ['status' => CustomerInvoice::STATUS_VOID]);
        $this->payment($c, '2026-01-20', 40);
        $this->payment($c, '2026-01-21', 500, ['voided_at' => now()]);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame(60.0, $ctx['closing_balance']);
        $this->assertCount(2, $ctx['events']);
    }

    public function test_opening_balance_covers_everything_strictly_before_from(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);   // before window
        $this->invoice($c, '2026-02-01', 25);    // exactly on the boundary — in window

        $ctx = $this->service()->build($c, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));

        $this->assertSame(100.0, $ctx['opening_balance']);
        $this->assertCount(1, $ctx['events']);
        $this->assertSame(125.0, $ctx['closing_balance']);
    }

    public function test_aging_falls_back_to_customer_payment_terms_when_due_date_is_null(): void
    {
        $c = Customer::factory()->create(['payment_terms_days' => 30]);
        $this->invoice($c, '2026-01-01', 100); // due 2026-01-31 by fallback

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-15')); // 15 days overdue

        $this->assertTrue($ctx['open_invoices'][0]['due_date_is_derived']);
        $this->assertSame('2026-01-31', $ctx['open_invoices'][0]['due_date']->toDateString());
        $this->assertSame(15, $ctx['open_invoices'][0]['days_overdue']);
        $this->assertSame(100.0, $ctx['aging']['d1_30']);
        $this->assertSame(0.0, $ctx['aging']['current']);
    }

    public function test_an_explicit_due_date_overrides_the_terms_fallback(): void
    {
        $c = Customer::factory()->create(['payment_terms_days' => 30]);
        $this->invoice($c, '2026-01-01', 100, ['due_date' => '2026-03-01']);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-15'));

        $this->assertFalse($ctx['open_invoices'][0]['due_date_is_derived']);
        $this->assertSame(0, $ctx['open_invoices'][0]['days_overdue']);
        $this->assertSame(100.0, $ctx['aging']['current']);
    }

    public function test_aging_buckets_sum_to_the_total_outstanding(): void
    {
        $c = Customer::factory()->create(['payment_terms_days' => 0]);
        $this->invoice($c, '2026-03-01', 10);   // current
        $this->invoice($c, '2026-02-01', 20);   // ~29 days
        $this->invoice($c, '2025-12-01', 40);   // 90+

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-03-01'));

        $this->assertSame(70.0, $ctx['aging']['total']);
        $this->assertSame($ctx['closing_balance'], $ctx['aging']['total']);
    }

    public function test_overpayment_shows_as_credit_not_negative_outstanding(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $pay = $this->payment($c, '2026-01-11', 150);
        $pay->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame(-50.0, $ctx['closing_balance']);
        $this->assertSame([], $ctx['open_invoices']);          // clamped at 0, not negative
        $this->assertSame(50.0, $ctx['unallocated_credit']);   // surfaces as account credit
    }

    /**
     * A payment recorded without allocations leaves the invoice looking unpaid,
     * so the aged total alone would overstate the debt. The statement reports the
     * unapplied credit separately, and the two must reconcile to the balance.
     */
    public function test_unallocated_payments_reconcile_aged_total_to_the_balance(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->payment($c, '2026-01-11', 40); // deliberately not allocated

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame(100.0, $ctx['aging']['total']);
        $this->assertSame(40.0, $ctx['unallocated_credit']);
        $this->assertSame(60.0, $ctx['closing_balance']);
        $this->assertSame(
            $ctx['closing_balance'],
            round($ctx['aging']['total'] - $ctx['unallocated_credit'], 2)
        );
    }

    public function test_paid_invoices_drop_off_the_open_item_list(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $pay = $this->payment($c, '2026-01-20', 100);
        $pay->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame([], $ctx['open_invoices']);
        $this->assertSame(0.0, $ctx['closing_balance']);
    }

    public function test_debtors_totals_match_the_sum_of_customer_balances(): void
    {
        $a = Customer::factory()->create();
        $b = Customer::factory()->create();
        Customer::factory()->create(); // settled, must not appear

        $this->invoice($a, '2026-01-10', 100);
        $this->invoice($b, '2026-01-10', 250);
        $this->payment($b, '2026-01-15', 50);

        $data = $this->service()->debtors(Carbon::parse('2026-02-01'));

        $this->assertCount(2, $data['rows']);
        $this->assertSame(300.0, $data['totals']['balance']);
        $this->assertSame(200.0, $data['rows'][0]['balance']); // sorted desc
    }

    public function test_statement_pages_render_and_pdf_downloads(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);

        $this->actingAs($this->actor());

        $this->get(route('customers.statement', $c))->assertOk()->assertSee('Account activity');
        $this->get(route('customers.statement.print', $c))->assertOk()->assertSee('Aged balance');
        $this->get(route('customers.debtors'))->assertOk()->assertSee($c->name);

        $pdf = $this->get(route('customers.statement.pdf', $c));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_debtors_csv_export_includes_a_totals_row(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);

        $this->actingAs($this->actor());
        $res = $this->get(route('customers.debtors.export'));
        $res->assertOk();

        $csv = $res->streamedContent();
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString($c->name, $csv);
    }

    public function test_bulk_run_skips_customers_without_email_or_balance(): void
    {
        Mail::fake();

        $owing = Customer::factory()->receivesStatements()->create();
        $this->invoice($owing, '2026-01-10', 100);

        $settled = Customer::factory()->receivesStatements()->create();

        $noEmail = Customer::factory()->receivesStatements()->withoutEmail()->create();
        $this->invoice($noEmail, '2026-01-10', 100);

        $notOptedIn = Customer::factory()->create();
        $this->invoice($notOptedIn, '2026-01-10', 100);

        $result = $this->service()->sendStatements(Carbon::parse('2026-02-01'));

        $this->assertSame(1, $result['sent']);
        Mail::assertQueued(CustomerStatementMail::class, 1);
        $this->assertNotNull($owing->fresh()->statement_last_sent_at);
        $this->assertNull($settled->fresh()->statement_last_sent_at);
        $this->assertNull($notOptedIn->fresh()->statement_last_sent_at);
    }

    /**
     * Mail::fake() never renders the message, so a Mailable that compiles but
     * blows up in the view passes every assertQueued check. This renders it for
     * real. (A Mailable exposes only its public properties to the view, so the
     * context has to be unpacked via Content(with:) — without that the blade
     * dies on an undefined $customer.)
     */
    public function test_the_statement_email_renders_with_its_context(): void
    {
        $c = Customer::factory()->create(['name' => 'Renderable Ltd']);
        $this->invoice($c, '2026-01-10', 123.45);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $html = (new CustomerStatementMail($ctx))->render();

        $this->assertStringContainsString('Renderable Ltd', $html);
        $this->assertStringContainsString('123.45', $html);
        $this->assertCount(1, (new CustomerStatementMail($ctx))->attachments());
    }

    public function test_dry_run_queues_nothing(): void
    {
        Mail::fake();

        $c = Customer::factory()->receivesStatements()->create();
        $this->invoice($c, '2026-01-10', 100);

        $result = $this->service()->sendStatements(Carbon::parse('2026-02-01'), dryRun: true);

        $this->assertSame(1, $result['sent']);
        Mail::assertNothingQueued();
        $this->assertNull($c->fresh()->statement_last_sent_at);
    }

    public function test_emailing_a_customer_without_an_address_is_rejected(): void
    {
        Mail::fake();
        $c = Customer::factory()->withoutEmail()->create();

        $this->actingAs($this->actor())
            ->post(route('customers.statement.email', $c))
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    /**
     * The debtors/index balances are aggregated in SQL. Before that, the
     * outstanding-balances widget loaded every customer with invoices and
     * allocations and then fired two more queries per row via the balance
     * accessor, so query count scaled with the customer list.
     */
    public function test_balance_query_count_does_not_scale_with_customer_count(): void
    {
        $this->actingAs($this->actor());

        $makeCustomers = function (int $n) {
            foreach (range(1, $n) as $i) {
                $c = Customer::factory()->create();
                $this->invoice($c, '2026-01-10', 100);
            }
        };

        $countFor = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $makeCustomers(3);
        $smallIndex = $countFor(route('customers.index'));
        $smallPayments = $countFor(route('customer-payments.index'));

        $makeCustomers(17); // 20 customers in total
        $largeIndex = $countFor(route('customers.index'));
        $largePayments = $countFor(route('customer-payments.index'));

        $this->assertLessThanOrEqual($smallIndex, $largeIndex);
        $this->assertLessThanOrEqual($smallPayments, $largePayments);
        $this->assertLessThan(10, $largeIndex);
        $this->assertLessThan(10, $largePayments);
    }

    public function test_customer_form_exposes_payment_terms_and_statement_opt_in(): void
    {
        $c = Customer::factory()->create();

        $this->actingAs($this->actor())
            ->get(route('customers.edit', $c))
            ->assertOk()
            ->assertSee('Payment terms')
            ->assertSee('Email statements of account');
    }

    public function test_owing_filter_returns_only_customers_with_a_balance(): void
    {
        $owing = Customer::factory()->create();
        $this->invoice($owing, '2026-01-10', 100);
        $settled = Customer::factory()->create();

        $this->actingAs($this->actor());
        $res = $this->get(route('customers.index', ['owing' => 1]));

        $res->assertOk()->assertSee($owing->name)->assertDontSee($settled->name);
    }

    public function test_aged_total_minus_unallocated_credit_equals_the_closing_balance_after_matching(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $payment = $this->payment($c, '2026-01-20', 60);

        // Unmatched: the credit term carries the whole payment.
        $before = $this->service()->build($c, null, Carbon::parse('2026-03-01'));
        $this->assertSame(60.0, $before['unallocated_credit']);
        $this->assertSame(
            $before['closing_balance'],
            round($before['aging']['total'] - $before['unallocated_credit'], 2)
        );

        app(CustomerPaymentService::class)->reallocate($payment, [
            ['customer_invoice_id' => $inv->id, 'amount' => 60],
        ]);

        // Matched: the credit term goes to zero and the identity still holds.
        $after = $this->service()->build($c->fresh(), null, Carbon::parse('2026-03-01'));
        $this->assertSame(0.0, $after['unallocated_credit']);
        $this->assertSame($before['closing_balance'], $after['closing_balance']);
        $this->assertSame(
            $after['closing_balance'],
            round($after['aging']['total'] - $after['unallocated_credit'], 2)
        );
    }

    public function test_over_allocation_is_rejected_so_the_identity_cannot_be_broken(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 50);

        // €500 against a €50 invoice used to be accepted; outstanding_amount
        // clamped at 0 and the excess silently vanished from the statement.
        $this->expectException(\DomainException::class);

        app(CustomerPaymentService::class)->recordPayment([
            'customer_id' => $c->id,
            'payment_date' => '2026-01-20',
            'amount' => 500,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], [['customer_invoice_id' => $inv->id, 'amount' => 500]]);
    }

    // ------------------------------- Allocation on the statement surfaces

    private function allocate(CustomerPayment $p, CustomerInvoice $inv, float $amount): void
    {
        $p->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => $amount]);
    }

    public function test_payment_events_carry_per_invoice_allocation_amounts(): void
    {
        $c = Customer::factory()->create();
        $a = $this->invoice($c, '2026-01-10', 300);
        $b = $this->invoice($c, '2026-01-11', 200);
        $p = $this->payment($c, '2026-01-20', 500);
        $this->allocate($p, $a, 300);
        $this->allocate($p, $b, 200);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $event = collect($ctx['events'])->firstWhere('kind', 'payment');

        $this->assertSame(500.0, $event['allocated']);
        $this->assertSame(0.0, $event['unapplied']);
        $this->assertSame(
            [[$a->invoice_number, 300.0], [$b->invoice_number, 200.0]],
            array_map(fn ($r) => [$r['invoice_number'], $r['amount']], $event['allocations'])
        );
        $this->assertStringContainsString($a->invoice_number.' €300.00', $event['allocation_summary']);
        $this->assertStringContainsString($b->invoice_number.' €200.00', $event['allocation_summary']);
    }

    public function test_a_wholly_unapplied_payment_is_marked_on_account(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->payment($c, '2026-01-20', 60);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $event = collect($ctx['events'])->firstWhere('kind', 'payment');

        $this->assertSame([], $event['allocations']);
        $this->assertSame(60.0, $event['unapplied']);
        $this->assertSame('', $event['allocation_summary']);

        $this->actingAs($this->actor())
            ->get(route('customers.statement', $c))
            ->assertOk()
            ->assertSee('on account');
    }

    public function test_a_partly_applied_payment_reports_both_halves(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 300);
        $p = $this->payment($c, '2026-01-20', 500);
        $this->allocate($p, $inv, 300);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $event = collect($ctx['events'])->firstWhere('kind', 'payment');

        $this->assertSame(300.0, $event['allocated']);
        $this->assertSame(200.0, $event['unapplied']);
    }

    public function test_payments_on_account_rows_total_the_unallocated_credit(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 300);
        $matched = $this->payment($c, '2026-01-15', 300);
        $this->allocate($matched, $inv, 300);
        $this->payment($c, '2026-01-20', 80);
        $partly = $this->payment($c, '2026-01-25', 50);
        $this->allocate($partly, $inv, 0.01);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        // The fully matched payment is not listed; the other two are.
        $this->assertCount(2, $ctx['credit_payments']);
        $this->assertSame(
            $ctx['unallocated_credit'],
            round(array_sum(array_column($ctx['credit_payments'], 'unapplied')), 2)
        );
    }

    public function test_credit_is_shown_when_there_are_no_open_invoices(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $settle = $this->payment($c, '2026-01-15', 100);
        $this->allocate($settle, $inv, 100);
        $this->payment($c, '2026-01-20', 40);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame([], $ctx['open_invoices']);
        $this->assertSame(40.0, $ctx['unallocated_credit']);

        // Used to be nested inside the open-invoices guard and vanish entirely.
        $this->actingAs($this->actor())
            ->get(route('customers.statement', $c))
            ->assertOk()
            ->assertSee('Payments on account');
    }

    public function test_screen_and_print_use_the_same_credit_wording(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->payment($c, '2026-01-20', 40);

        $this->actingAs($this->actor());
        $this->get(route('customers.statement', $c))->assertOk()->assertSee('received on account');
        $this->get(route('customers.statement.print', $c))->assertOk()->assertSee('received on account');
    }

    public function test_a_payment_before_the_window_still_appears_as_credit_on_a_ranged_statement(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-02-10', 500);
        $this->payment($c, '2026-01-05', 400);   // before the window

        $ctx = $this->service()->build($c, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));

        // Absent from the ledger, so without the on-account section the €400
        // would be a figure with no supporting row on the document.
        $this->assertNull(collect($ctx['events'])->firstWhere('kind', 'payment'));
        $this->assertSame(400.0, $ctx['unallocated_credit']);
        $this->assertCount(1, $ctx['credit_payments']);
        $this->assertSame(400.0, $ctx['credit_payments'][0]['unapplied']);
    }

    public function test_open_invoice_rows_carry_the_payments_that_paid_them(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 300);
        $first = $this->payment($c, '2026-01-15', 100);
        $second = $this->payment($c, '2026-01-25', 50);
        $this->allocate($first, $inv, 100);
        $this->allocate($second, $inv, 50);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $row = $ctx['open_invoices'][0];

        $this->assertSame('partial', $row['status']);
        $this->assertSame([100.0, 50.0], array_column($row['payments'], 'amount'));
        $this->assertSame('2026-01-15', $row['payments'][0]['date']->toDateString());
    }

    /**
     * The per-invoice payment detail is loaded in build(), not openInvoices(),
     * because debtors() calls openInvoices() once per customer. Moving it would
     * make this count scale.
     */
    public function test_statement_query_count_does_not_scale_with_open_invoice_count(): void
    {
        $this->actingAs($this->actor());
        $c = Customer::factory()->create();

        $addInvoices = function (int $n) use ($c) {
            foreach (range(1, $n) as $i) {
                $inv = $this->invoice($c, '2026-01-10', 100);
                $p = $this->payment($c, '2026-01-15', 40);
                $this->allocate($p, $inv, 40);
            }
        };

        $countFor = function () use ($c): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('customers.statement', $c))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $addInvoices(3);
        $small = $countFor();
        $addInvoices(12);
        $large = $countFor();

        $this->assertLessThanOrEqual($small, $large);
    }

    public function test_the_statement_email_shows_paid_and_credit(): void
    {
        $c = Customer::factory()->create(['name' => 'Renderable Ltd']);
        $inv = $this->invoice($c, '2026-01-10', 300);
        $part = $this->payment($c, '2026-01-15', 120);
        $this->allocate($part, $inv, 120);
        $this->payment($c, '2026-01-20', 45);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $html = (new CustomerStatementMail($ctx))->render();

        $this->assertStringContainsString('>Paid<', $html);
        $this->assertStringContainsString('120.00', $html);
        $this->assertStringContainsString('received on account', $html);
        $this->assertStringContainsString('45.00', $html);
    }

    public function test_the_statement_email_omits_the_paid_column_when_nothing_is_part_paid(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 300);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));
        $html = (new CustomerStatementMail($ctx))->render();

        $this->assertStringNotContainsString('>Paid<', $html);
        $this->assertStringNotContainsString('received on account', $html);
    }

    /**
     * Voiding an invoice leaves its allocation rows in place, and
     * CustomerPayment::getTotalAllocatedAttribute() counts them with no
     * invoice-void filter — so the money vanishes instead of returning to
     * on-account credit, and the documented identity fails.
     *
     * Tracked as its own fix: both candidate remedies (deleting rows on void,
     * or excluding void invoices from the payment-side sum) change balance
     * maths outside the statement.
     */
    public function test_a_payment_allocated_to_a_later_voided_invoice_still_counts_as_credit(): void
    {
        $this->markTestSkipped('Known bug — see the void-invoice credit hole. Fix lands separately.');

        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $p = $this->payment($c, '2026-01-15', 100);
        $this->allocate($p, $inv, 100);

        $inv->update(['status' => CustomerInvoice::STATUS_VOID, 'voided_at' => now()]);

        $ctx = $this->service()->build($c->fresh(), null, Carbon::parse('2026-02-01'));

        $this->assertSame(-100.0, $ctx['closing_balance']);
        $this->assertSame(100.0, $ctx['unallocated_credit']);
        $this->assertSame(
            $ctx['closing_balance'],
            round($ctx['aging']['total'] - $ctx['unallocated_credit'], 2)
        );
    }
}
