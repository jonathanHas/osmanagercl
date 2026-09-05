<?php

namespace App\Http\Controllers;

use App\Jobs\SendCustomerStatementsJob;
use App\Services\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerDebtorsController extends Controller
{
    public function __construct(private CustomerStatementService $statements) {}

    public function index(Request $request): View
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of')) : Carbon::today();
        $includeCredits = $request->boolean('include_credits');

        $data = $this->statements->debtors($asOf, $includeCredits);

        return view('customers.debtors', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'as_of' => $asOf,
            'include_credits' => $includeCredits,
            'bucket_labels' => CustomerStatementService::BUCKET_LABELS,
        ]);
    }

    public function export(Request $request)
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of')) : Carbon::today();
        $data = $this->statements->debtors($asOf, $request->boolean('include_credits'));
        $labels = CustomerStatementService::BUCKET_LABELS;

        $filename = 'aged-debtors-'.$asOf->format('Ymd').'.csv';

        return response()->stream(function () use ($data, $labels) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge(
                ['Customer', 'Email', 'Balance'],
                array_values($labels),
                ['Total due', 'Open invoices', 'Oldest overdue (days)', 'Unapplied credit']
            ));

            foreach ($data['rows'] as $row) {
                fputcsv($out, array_merge(
                    [$row['customer']->name, $row['customer']->email, number_format($row['balance'], 2, '.', '')],
                    array_map(fn ($k) => number_format($row['aging'][$k], 2, '.', ''), array_keys($labels)),
                    [
                        number_format($row['aging']['total'], 2, '.', ''),
                        $row['open_count'],
                        $row['oldest_days'],
                        number_format($row['unapplied_credit'], 2, '.', ''),
                    ]
                ));
            }

            fputcsv($out, array_merge(
                ['TOTAL', '', number_format($data['totals']['balance'], 2, '.', '')],
                array_map(fn ($k) => number_format($data['totals'][$k], 2, '.', ''), array_keys($labels)),
                [
                    number_format($data['totals']['total'], 2, '.', ''),
                    '',
                    '',
                    number_format($data['totals']['credit'], 2, '.', ''),
                ]
            ));

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Queue the bulk statement run. Dispatched as a job rather than run inline
     * so a large customer list can't time out the request.
     */
    public function sendStatements(Request $request): RedirectResponse
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of')) : Carbon::today();

        SendCustomerStatementsJob::dispatch($asOf->toDateString());

        $note = config('mail.default') === 'log'
            ? ' Note: MAIL_MAILER=log, so these are written to the log rather than delivered.'
            : '';

        return back()->with('status', 'Statement run queued for all opted-in customers with a balance.'.$note);
    }
}
