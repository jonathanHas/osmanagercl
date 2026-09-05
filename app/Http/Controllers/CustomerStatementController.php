<?php

namespace App\Http\Controllers;

use App\Mail\CustomerStatementMail;
use App\Models\Customer;
use App\Services\CustomerStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CustomerStatementController extends Controller
{
    public function __construct(private CustomerStatementService $statements) {}

    public function show(Customer $customer, Request $request): View
    {
        return view('customer-statements.show', $this->context($customer, $request));
    }

    /**
     * Light-themed A4 page that opens the browser print dialog. Kept separate
     * from the Dompdf view because the two renderers disagree on fixed footers
     * and @page handling.
     */
    public function print(Customer $customer, Request $request): View
    {
        return view('customer-statements.print', $this->context($customer, $request));
    }

    public function downloadPdf(Customer $customer, Request $request)
    {
        $ctx = $this->context($customer, $request);

        return Pdf::loadView('customer-statements._pdf', $ctx)
            ->setPaper('a4')
            ->download($this->statements->filename($customer, $ctx['to']));
    }

    public function email(Customer $customer, Request $request): RedirectResponse
    {
        if (! $customer->email) {
            return back()->with('error', 'This customer has no email address.');
        }

        $ctx = $this->context($customer, $request);

        try {
            Mail::to($customer->email)->queue(new CustomerStatementMail($ctx));
        } catch (\Throwable $e) {
            Log::error('Customer statement email failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not send the statement: '.$e->getMessage());
        }

        $customer->forceFill(['statement_last_sent_at' => now()])->save();

        $note = config('mail.default') === 'log'
            ? ' (MAIL_MAILER=log — written to the log, not actually delivered)'
            : '';

        return back()->with('status', 'Statement queued for '.$customer->email.'.'.$note);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Customer $customer, Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::today();

        return $this->statements->build($customer, $from, $to);
    }
}
