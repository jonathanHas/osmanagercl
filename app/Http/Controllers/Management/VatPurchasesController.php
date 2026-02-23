<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VatPurchasesController extends Controller
{
    public function index(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()->format('Y-m-d')));

        $invoices = Invoice::with('supplier:id,name,rtd_classification')
            ->whereBetween('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('invoice_date')
            ->get();

        $emptyBucket = [
            'zero_net' => 0, 'zero_vat' => 0,
            'second_reduced_net' => 0, 'second_reduced_vat' => 0,
            'reduced_net' => 0, 'reduced_vat' => 0,
            'standard_net' => 0, 'standard_vat' => 0,
            'total_net' => 0, 'total_vat' => 0,
            'invoice_count' => 0,
        ];

        $retail = $emptyBucket;
        $nonRetail = $emptyBucket;
        $unclassified = $emptyBucket;

        foreach ($invoices as $invoice) {
            $classification = $invoice->supplier->rtd_classification ?? 'not_applicable';

            if (in_array($classification, ['goods_simple', 'goods_parser'])) {
                $bucket = &$retail;
            } elseif ($classification === 'service_overhead') {
                $bucket = &$nonRetail;
            } else {
                $bucket = &$unclassified;
            }

            $bucket['zero_net'] += (float) ($invoice->zero_net ?? 0);
            $bucket['zero_vat'] += (float) ($invoice->zero_vat ?? 0);
            $bucket['second_reduced_net'] += (float) ($invoice->second_reduced_net ?? 0);
            $bucket['second_reduced_vat'] += (float) ($invoice->second_reduced_vat ?? 0);
            $bucket['reduced_net'] += (float) ($invoice->reduced_net ?? 0);
            $bucket['reduced_vat'] += (float) ($invoice->reduced_vat ?? 0);
            $bucket['standard_net'] += (float) ($invoice->standard_net ?? 0);
            $bucket['standard_vat'] += (float) ($invoice->standard_vat ?? 0);
            $bucket['invoice_count']++;
            unset($bucket);
        }

        // Calculate total_net and total_vat for each bucket
        foreach ([&$retail, &$nonRetail, &$unclassified] as &$bucket) {
            $bucket['total_net'] = round($bucket['zero_net'] + $bucket['second_reduced_net'] + $bucket['reduced_net'] + $bucket['standard_net'], 2);
            $bucket['total_vat'] = round($bucket['zero_vat'] + $bucket['second_reduced_vat'] + $bucket['reduced_vat'] + $bucket['standard_vat'], 2);

            // Round individual fields
            foreach (['zero_net', 'zero_vat', 'second_reduced_net', 'second_reduced_vat', 'reduced_net', 'reduced_vat', 'standard_net', 'standard_vat'] as $field) {
                $bucket[$field] = round($bucket[$field], 2);
            }
        }
        unset($bucket);

        $totals = [
            'zero_net' => $retail['zero_net'] + $nonRetail['zero_net'] + $unclassified['zero_net'],
            'zero_vat' => $retail['zero_vat'] + $nonRetail['zero_vat'] + $unclassified['zero_vat'],
            'second_reduced_net' => $retail['second_reduced_net'] + $nonRetail['second_reduced_net'] + $unclassified['second_reduced_net'],
            'second_reduced_vat' => $retail['second_reduced_vat'] + $nonRetail['second_reduced_vat'] + $unclassified['second_reduced_vat'],
            'reduced_net' => $retail['reduced_net'] + $nonRetail['reduced_net'] + $unclassified['reduced_net'],
            'reduced_vat' => $retail['reduced_vat'] + $nonRetail['reduced_vat'] + $unclassified['reduced_vat'],
            'standard_net' => $retail['standard_net'] + $nonRetail['standard_net'] + $unclassified['standard_net'],
            'standard_vat' => $retail['standard_vat'] + $nonRetail['standard_vat'] + $unclassified['standard_vat'],
            'total_net' => $retail['total_net'] + $nonRetail['total_net'] + $unclassified['total_net'],
            'total_vat' => $retail['total_vat'] + $nonRetail['total_vat'] + $unclassified['total_vat'],
            'invoice_count' => $retail['invoice_count'] + $nonRetail['invoice_count'] + $unclassified['invoice_count'],
        ];

        return view('management.vat-purchases.index', compact(
            'startDate',
            'endDate',
            'retail',
            'nonRetail',
            'unclassified',
            'totals',
            'invoices'
        ));
    }
}
