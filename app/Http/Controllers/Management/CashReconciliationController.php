<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Repositories\CashReconciliationRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class CashReconciliationController extends Controller
{
    protected CashReconciliationRepository $repository;

    public function __construct(CashReconciliationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display the cash reconciliation interface
     */
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $tillId = $request->input('till_id', 1);

        $tills = $this->repository->getAvailableTills();
        $tillName = $tills[$tillId] ?? $tills->first();

        try {
            $selectedDate = Carbon::parse($date);
            $reconciliation = $this->repository->getOrCreateReconciliation($selectedDate, $tillId, $tillName);
            $reconciliation->load(['legacyCashLodgement', 'cashLodgements.cashLodgement', 'payments']);
            $suppliers = $this->repository->getSuppliers();
            $history = $this->repository->getHistory($tillId, 7);
            $adjacentDates = $this->repository->getAdjacentDates($selectedDate, $tillName);

            // Customer-invoice payments through this till on this date — informational only.
            // Variance maths are NOT touched; this just explains why the operator may see
            // extra cash/card revenue that the POS doesn't know about.
            $customerInvoicePayments = CustomerPayment::query()
                ->whereDate('payment_date', $selectedDate)
                ->where(function ($q) use ($tillId, $tillName) {
                    $q->where('till_id', (string) $tillId);
                    if ($tillName) {
                        $q->orWhere('till_name', $tillName);
                    }
                })
                ->whereIn('method', [CustomerPayment::METHOD_CARD_TILL, CustomerPayment::METHOD_CASH_TILL])
                ->notVoid()
                ->with('customer', 'allocations.invoice')
                ->orderBy('id')
                ->get();

            return view('management.cash-reconciliation.index', compact(
                'reconciliation',
                'selectedDate',
                'tillId',
                'tillName',
                'tills',
                'suppliers',
                'history',
                'adjacentDates',
                'customerInvoicePayments'
            ));
        } catch (\Exception $e) {
            Log::error('Cash reconciliation error: '.$e->getMessage());

            return view('management.cash-reconciliation.index', [
                'error' => $e->getMessage(),
                'selectedDate' => Carbon::parse($date),
                'tillId' => $tillId,
                'tillName' => $tillName,
                'tills' => $tills,
                'suppliers' => collect(),
                'history' => collect(),
                'reconciliation' => null,
                'adjacentDates' => ['prev' => null, 'next' => null],
                'customerInvoicePayments' => collect(),
            ]);
        }
    }

    /**
     * Save reconciliation data
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reconciliation_id' => 'required|uuid|exists:cash_reconciliations,id',
            'cash_50' => 'nullable|integer|min:0',
            'cash_20' => 'nullable|integer|min:0',
            'cash_10' => 'nullable|integer|min:0',
            'cash_5' => 'nullable|integer|min:0',
            'cash_2' => 'nullable|integer|min:0',
            'cash_1' => 'nullable|integer|min:0',
            'cash_50c' => 'nullable|integer|min:0',
            'cash_20c' => 'nullable|integer|min:0',
            'cash_10c' => 'nullable|integer|min:0',
            'note_float' => 'nullable|numeric|min:0',
            'coin_float' => 'nullable|numeric|min:0',
            'card' => 'nullable|numeric|min:0',
            'cash_back' => 'nullable|numeric|min:0',
            'cheque' => 'nullable|numeric|min:0',
            'debt' => 'nullable|numeric|min:0',
            'debt_paid_cash' => 'nullable|numeric|min:0',
            'debt_paid_cheque' => 'nullable|numeric|min:0',
            'debt_paid_card' => 'nullable|numeric|min:0',
            'free' => 'nullable|numeric|min:0',
            'voucher_used' => 'nullable|numeric|min:0',
            'money_added' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'payments' => 'nullable|array',
            'payments.*.supplier_id' => 'nullable|string',
            'payments.*.payee_name' => 'nullable|string|max:255',
            'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.description' => 'nullable|string|max:255',
        ]);

        try {
            $reconciliation = \App\Models\CashReconciliation::findOrFail($validated['reconciliation_id']);
            $this->repository->saveReconciliation($reconciliation, $validated);

            return redirect()
                ->route('cash-reconciliation.index', [
                    'date' => $reconciliation->date->format('Y-m-d'),
                    'till_id' => $reconciliation->till_id,
                ])
                ->with('success', 'Cash reconciliation saved successfully');
        } catch (\Exception $e) {
            Log::error('Error saving reconciliation: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to save reconciliation: '.$e->getMessage());
        }
    }

    /**
     * Force re-import of legacy POS data for a till/date.
     *
     * Handles days that were auto-created before the old POS system had written the
     * day's cash count, leaving the reconciliation stuck with zero denominations.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'till_id' => 'required|integer',
        ]);

        $date = Carbon::parse($request->input('date'));
        $tillId = (int) $request->input('till_id');
        $tills = $this->repository->getAvailableTills();
        $tillName = $tills[$tillId] ?? $tills->first();

        try {
            $result = $this->repository->resyncFromLegacy($date, $tillId, $tillName);

            $redirect = redirect()->route('cash-reconciliation.index', [
                'date' => $date->format('Y-m-d'),
                'till_id' => $tillId,
            ]);

            if ($result['status'] === 'no_legacy_data') {
                return $redirect->with('error', 'The POS system has no cash count for this day yet — POS totals were refreshed.');
            }

            return $redirect->with('success', 'Synced cash figures from the POS system.');
        } catch (\Exception $e) {
            Log::error('Cash reconciliation sync error: '.$e->getMessage());

            return redirect()
                ->route('cash-reconciliation.index', [
                    'date' => $date->format('Y-m-d'),
                    'till_id' => $tillId,
                ])
                ->with('error', 'Failed to sync from POS: '.$e->getMessage());
        }
    }

    /**
     * Get previous day's float via AJAX
     */
    public function getPreviousFloat(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'till_id' => 'required|integer',
        ]);

        try {
            $date = Carbon::parse($request->input('date'));
            $tillId = $request->input('till_id');

            $previousFloat = $this->repository->getPreviousDayFloat($date, $tillId);

            return response()->json([
                'success' => true,
                'note_float' => $previousFloat['notes'],
                'coin_float' => $previousFloat['coins'],
                'date' => $previousFloat['date'],
                'source' => $previousFloat['source'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export reconciliations to CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'till_id' => 'nullable|integer',
        ]);

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            $tillId = $request->input('till_id');

            $csv = $this->repository->exportToCsv($startDate, $endDate, $tillId);

            $filename = sprintf(
                'cash-reconciliation-%s-to-%s.csv',
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            return Response::make($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (\Exception $e) {
            Log::error('Export error: '.$e->getMessage());

            return back()->with('error', 'Failed to export data: '.$e->getMessage());
        }
    }

    /**
     * Get reconciliation data via AJAX
     */
    public function getReconciliation(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'till_id' => 'required|integer',
        ]);

        try {
            $date = Carbon::parse($request->input('date'));
            $tillId = $request->input('till_id');
            $tills = $this->repository->getAvailableTills();
            $tillName = $tills[$tillId] ?? $tills->first();

            $reconciliation = $this->repository->getOrCreateReconciliation($date, $tillId, $tillName);

            return response()->json([
                'success' => true,
                'reconciliation' => $reconciliation->load(['payments.supplier', 'latestNote']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
