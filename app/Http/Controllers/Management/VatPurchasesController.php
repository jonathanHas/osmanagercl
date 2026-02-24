<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        [$retail, $nonRetail, $unclassified, $totals] = $this->calculateBuckets($invoices);

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

    public function export(Request $request): StreamedResponse
    {
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()->format('Y-m-d')));

        $invoices = Invoice::with('supplier:id,name,rtd_classification')
            ->whereBetween('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('invoice_date')
            ->get();

        [$retail, $nonRetail, $unclassified, $totals] = $this->calculateBuckets($invoices);

        $filename = 'vat-purchases-'.$startDate->format('Y-m-d').'-to-'.$endDate->format('Y-m-d').'.csv';

        return new StreamedResponse(function () use ($startDate, $endDate, $retail, $nonRetail, $unclassified, $totals, $invoices) {
            $handle = fopen('php://output', 'w');

            // Report Header
            fputcsv($handle, ['VAT on Purchases Report']);
            fputcsv($handle, ['Period', $startDate->format('d M Y').' - '.$endDate->format('d M Y')]);
            fputcsv($handle, ['Generated', now()->format('d M Y H:i')]);
            fputcsv($handle, ['Total Invoices', $totals['invoice_count']]);
            fputcsv($handle, []);

            // Summary by Classification
            fputcsv($handle, ['SUMMARY BY CLASSIFICATION']);
            fputcsv($handle, ['Classification', 'Invoices', 'Net', 'VAT']);
            fputcsv($handle, ['Retail (T1)', $retail['invoice_count'], number_format($retail['total_net'], 2), number_format($retail['total_vat'], 2)]);
            fputcsv($handle, ['Non-Retail (T2)', $nonRetail['invoice_count'], number_format($nonRetail['total_net'], 2), number_format($nonRetail['total_vat'], 2)]);
            fputcsv($handle, ['Unclassified', $unclassified['invoice_count'], number_format($unclassified['total_net'], 2), number_format($unclassified['total_vat'], 2)]);
            fputcsv($handle, ['TOTAL', $totals['invoice_count'], number_format($totals['total_net'], 2), number_format($totals['total_vat'], 2)]);
            fputcsv($handle, []);

            // VAT Rate Breakdown
            fputcsv($handle, ['VAT RATE BREAKDOWN']);
            fputcsv($handle, ['VAT Rate', 'Retail Net', 'Retail VAT', 'Non-Retail Net', 'Non-Retail VAT', 'Unclass. Net', 'Unclass. VAT', 'Total Net', 'Total VAT']);

            $rates = [
                ['label' => '0%', 'net' => 'zero_net', 'vat' => 'zero_vat'],
                ['label' => '9%', 'net' => 'second_reduced_net', 'vat' => 'second_reduced_vat'],
                ['label' => '13.5%', 'net' => 'reduced_net', 'vat' => 'reduced_vat'],
                ['label' => '23%', 'net' => 'standard_net', 'vat' => 'standard_vat'],
            ];

            foreach ($rates as $rate) {
                fputcsv($handle, [
                    $rate['label'],
                    number_format($retail[$rate['net']], 2),
                    number_format($retail[$rate['vat']], 2),
                    number_format($nonRetail[$rate['net']], 2),
                    number_format($nonRetail[$rate['vat']], 2),
                    number_format($unclassified[$rate['net']], 2),
                    number_format($unclassified[$rate['vat']], 2),
                    number_format($totals[$rate['net']], 2),
                    number_format($totals[$rate['vat']], 2),
                ]);
            }

            fputcsv($handle, [
                'TOTAL',
                number_format($retail['total_net'], 2),
                number_format($retail['total_vat'], 2),
                number_format($nonRetail['total_net'], 2),
                number_format($nonRetail['total_vat'], 2),
                number_format($unclassified['total_net'], 2),
                number_format($unclassified['total_vat'], 2),
                number_format($totals['total_net'], 2),
                number_format($totals['total_vat'], 2),
            ]);
            fputcsv($handle, []);

            // Invoice Detail
            fputcsv($handle, ['INVOICE DETAIL']);
            fputcsv($handle, ['Date', 'Invoice #', 'Supplier', 'RTD Classification', 'Net', 'VAT', 'Gross']);

            foreach ($invoices as $invoice) {
                $classification = $invoice->supplier->rtd_classification ?? 'not_applicable';
                if (in_array($classification, ['goods_simple', 'goods_parser'])) {
                    $classLabel = 'Retail';
                } elseif ($classification === 'service_overhead') {
                    $classLabel = 'Non-Retail';
                } else {
                    $classLabel = 'Unclassified';
                }

                fputcsv($handle, [
                    $invoice->invoice_date->format('d M Y'),
                    $invoice->invoice_number,
                    $invoice->supplier_name ?? $invoice->supplier->name ?? '-',
                    $classLabel,
                    number_format($invoice->subtotal ?? 0, 2),
                    number_format($invoice->vat_amount ?? 0, 2),
                    number_format($invoice->total_amount ?? 0, 2),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function calculateBuckets($invoices): array
    {
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

        return [$retail, $nonRetail, $unclassified, $totals];
    }
}
