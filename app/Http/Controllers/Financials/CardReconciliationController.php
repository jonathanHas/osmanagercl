<?php

namespace App\Http\Controllers\Financials;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCardTransactions;
use App\Models\CardReconciliationSetting;
use App\Models\CardTransaction;
use App\Models\POS\ClosedCash;
use App\Models\TerminalTillMapping;
use App\Services\CardReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CardReconciliationController extends Controller
{
    public function index()
    {
        $settings = CardReconciliationSetting::getForUser();

        // Get recent batches with stats
        $batches = CardTransaction::select(
            'upload_batch_id',
            'source_filename',
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN reconciliation_status = 'matched' THEN 1 ELSE 0 END) as matched"),
            DB::raw("SUM(CASE WHEN reconciliation_status = 'mismatch' THEN 1 ELSE 0 END) as mismatches"),
            DB::raw("SUM(CASE WHEN reconciliation_status = 'declined' THEN 1 ELSE 0 END) as declined"),
            DB::raw("SUM(CASE WHEN reconciliation_status = 'orphan' THEN 1 ELSE 0 END) as orphans"),
            DB::raw('MIN(transaction_datetime) as date_from'),
            DB::raw('MAX(transaction_datetime) as date_to'),
            DB::raw('MAX(created_at) as uploaded_at')
        )
            ->groupBy('upload_batch_id', 'source_filename')
            ->orderBy('uploaded_at', 'desc')
            ->limit(10)
            ->get();

        // Overall stats
        $overallStats = [
            'total' => CardTransaction::count(),
            'matched' => CardTransaction::matched()->count(),
            'mismatches' => CardTransaction::mismatches()->count(),
            'declined' => CardTransaction::declined()->count(),
            'orphans' => CardTransaction::orphans()->count(),
        ];

        return view('financials.card-reconciliation.index', compact('settings', 'batches', 'overallStats'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'card_transactions_file' => 'required|file|mimes:xls,xlsx|max:10240',
            ], [
                'card_transactions_file.required' => 'Please select an XLS file to upload.',
                'card_transactions_file.mimes' => 'Please upload a valid XLS or XLSX file.',
                'card_transactions_file.max' => 'File size must not exceed 10MB.',
            ]);

            $file = $request->file('card_transactions_file');
            $originalName = $file->getClientOriginalName();

            // Check for duplicate filename
            $existingUpload = CardTransaction::where('source_filename', $originalName)->exists();

            if ($existingUpload) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'A file with the name "'.$originalName.'" has already been uploaded. Please use a different file or delete the previous upload.',
                    ]);
                }

                return redirect()->route('management.card-reconciliation.index')
                    ->with('warning', 'A file with the name "'.$originalName.'" has already been uploaded.');
            }

            // Generate batch ID
            $batchId = 'CARD-'.strtoupper(Str::random(8)).'-'.time();

            // Store file temporarily
            $path = $file->store('temp/card-transactions');

            // Run synchronously (no queue worker needed)
            ProcessCardTransactions::dispatchSync($path, $batchId, $originalName);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File "'.$originalName.'" uploaded and processing started.',
                    'batch_id' => $batchId,
                ]);
            }

            return redirect()->route('management.card-reconciliation.index')
                ->with('success', 'File "'.$originalName.'" uploaded successfully. Processing will complete shortly.')
                ->with('batch_id', $batchId);

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->validator->errors()->first(),
                ], 422);
            }

            return redirect()->route('management.card-reconciliation.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to upload file: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->route('management.card-reconciliation.index')
                ->with('error', 'Failed to upload file: '.$e->getMessage());
        }
    }

    public function status(Request $request, string $batchId)
    {
        $cacheKey = "card_transactions_processing_{$batchId}";
        $status = Cache::get($cacheKey);

        if (! $status) {
            // Check if batch exists in DB
            $exists = CardTransaction::where('upload_batch_id', $batchId)->exists();
            if ($exists) {
                $reconciler = app(CardReconciliationService::class);

                return response()->json([
                    'status' => 'completed',
                    'message' => 'Processing complete',
                    'stats' => $reconciler->getBatchStats($batchId),
                ]);
            }

            return response()->json([
                'status' => 'not_found',
                'message' => 'Batch not found',
            ]);
        }

        return response()->json($status);
    }

    public function transactions(Request $request)
    {
        $query = CardTransaction::query();

        // Filter by batch
        if ($request->filled('batch_id')) {
            $query->forBatch($request->batch_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'discrepancies') {
                $query->whereIn('reconciliation_status', ['mismatch', 'declined', 'orphan']);
            } else {
                $query->where('reconciliation_status', $status);
            }
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('transaction_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('transaction_datetime', '<=', $request->date_to.' 23:59:59');
        }

        // Sort
        $sortBy = $request->get('sort', 'transaction_datetime');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $transactions = $query->paginate(50);

        if ($request->wantsJson()) {
            return response()->json($transactions);
        }

        return view('financials.card-reconciliation.transactions', compact('transactions'));
    }

    public function match(Request $request, CardReconciliationService $reconciler)
    {
        $request->validate([
            'transaction_id' => 'required|uuid',
            'pos_payment_id' => 'required|string',
        ]);

        $transaction = CardTransaction::findOrFail($request->transaction_id);
        $success = $reconciler->manualMatch($transaction, $request->pos_payment_id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Transaction matched successfully.' : 'Failed to match transaction.',
                'transaction' => $transaction->fresh(),
            ]);
        }

        return redirect()->back()->with(
            $success ? 'success' : 'error',
            $success ? 'Transaction matched successfully.' : 'Failed to match transaction.'
        );
    }

    public function unmatch(Request $request, CardReconciliationService $reconciler)
    {
        $request->validate([
            'transaction_id' => 'required|uuid',
        ]);

        $transaction = CardTransaction::findOrFail($request->transaction_id);
        $success = $reconciler->unmatch($transaction);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Match removed successfully.' : 'Failed to remove match.',
                'transaction' => $transaction->fresh(),
            ]);
        }

        return redirect()->back()->with(
            $success ? 'success' : 'error',
            $success ? 'Match removed successfully.' : 'Failed to remove match.'
        );
    }

    public function nearbyPayments(Request $request, CardReconciliationService $reconciler)
    {
        $request->validate([
            'transaction_id' => 'required|uuid',
            'window_minutes' => 'nullable|integer|min:1|max:120',
            'show_all_tills' => 'nullable',
        ]);

        $transaction = CardTransaction::findOrFail($request->transaction_id);
        $windowMinutes = $request->get('window_minutes', 30);
        $showAllTills = filter_var($request->get('show_all_tills', false), FILTER_VALIDATE_BOOLEAN);

        // Get mapped till info for this terminal
        $mappedTill = null;
        if ($transaction->terminal_id) {
            $mappedTill = TerminalTillMapping::getPosHostForTerminal($transaction->terminal_id);
        }

        $payments = $reconciler->findNearbyPosPayments($transaction, $windowMinutes, true, null, $showAllTills);

        return response()->json([
            'transaction' => $transaction,
            'payments' => $payments->values(),
            'mapped_till' => $mappedTill,
            'has_mapping' => $mappedTill !== null,
            'showing_all_tills' => $showAllTills,
        ]);
    }

    public function export(Request $request)
    {
        $query = CardTransaction::query();

        // Apply same filters as transactions
        if ($request->filled('batch_id')) {
            $query->forBatch($request->batch_id);
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'discrepancies') {
                $query->whereIn('reconciliation_status', ['mismatch', 'declined', 'orphan']);
            } else {
                $query->where('reconciliation_status', $status);
            }
        }

        if ($request->filled('date_from')) {
            $query->where('transaction_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('transaction_datetime', '<=', $request->date_to.' 23:59:59');
        }

        $transactions = $query->orderBy('transaction_datetime', 'desc')->get();

        $filename = 'card_reconciliation_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Date/Time',
                'Terminal',
                'Type',
                'Reference',
                'Status',
                'Card',
                'Amount',
                'Reconciliation',
                'POS Match',
                'Confidence',
                'Variance',
            ]);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->transaction_datetime->format('Y-m-d H:i:s'),
                    $tx->terminal_name,
                    $tx->transaction_type,
                    $tx->transaction_reference,
                    $tx->transaction_status,
                    $tx->card_masked,
                    number_format($tx->amount, 2),
                    $tx->reconciliation_status,
                    $tx->pos_payment_id ?: '',
                    $tx->confidence_score ?: '',
                    $tx->variance_amount ? number_format($tx->variance_amount, 2) : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function settings(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'time_window_minutes' => 'required|integer|min:1|max:60',
                'auto_match_threshold' => 'required|integer|min:50|max:100',
            ]);

            $settings = CardReconciliationSetting::getForUser();
            $settings->update([
                'time_window_minutes' => $request->time_window_minutes,
                'auto_match_threshold' => $request->auto_match_threshold,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Settings saved successfully.',
                    'settings' => $settings,
                ]);
            }

            return redirect()->route('management.card-reconciliation.index')
                ->with('success', 'Settings saved successfully.');
        }

        $settings = CardReconciliationSetting::getForUser();

        if ($request->wantsJson()) {
            return response()->json($settings);
        }

        return view('financials.card-reconciliation.settings', compact('settings'));
    }

    public function deleteBatch(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|string',
        ]);

        $deleted = CardTransaction::where('upload_batch_id', $request->batch_id)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $deleted > 0,
                'message' => $deleted > 0
                    ? "Deleted {$deleted} transactions."
                    : 'No transactions found for this batch.',
            ]);
        }

        return redirect()->route('management.card-reconciliation.index')
            ->with($deleted > 0 ? 'success' : 'warning',
                $deleted > 0 ? "Deleted {$deleted} transactions." : 'No transactions found for this batch.');
    }

    public function reprocess(Request $request, CardReconciliationService $reconciler)
    {
        $request->validate([
            'batch_id' => 'required|string',
        ]);

        // Reset all transactions in batch to pending
        CardTransaction::where('upload_batch_id', $request->batch_id)
            ->update([
                'reconciliation_status' => 'pending',
                'pos_payment_id' => null,
                'confidence_score' => null,
                'variance_amount' => null,
            ]);

        // Re-run reconciliation
        $stats = $reconciler->reconcileBatch($request->batch_id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Batch reprocessed successfully.',
                'stats' => $stats,
            ]);
        }

        return redirect()->route('management.card-reconciliation.index')
            ->with('success', 'Batch reprocessed successfully.');
    }

    public function previewAutoMatch(Request $request, CardReconciliationService $reconciler)
    {
        $request->validate([
            'batch_id' => 'required|string',
            'window_minutes' => 'nullable|integer|min:5|max:120',
            'min_confidence' => 'nullable|integer|min:50|max:100',
            'exact_amount_only' => 'nullable',
            'card_only' => 'nullable',
            'respect_till_mapping' => 'nullable',
        ]);

        $options = [
            'window_minutes' => (int) $request->get('window_minutes', 30),
            'min_confidence' => (int) $request->get('min_confidence', 80),
            'exact_amount_only' => filter_var($request->get('exact_amount_only', false), FILTER_VALIDATE_BOOLEAN),
            'card_only' => filter_var($request->get('card_only', true), FILTER_VALIDATE_BOOLEAN),
            'respect_till_mapping' => filter_var($request->get('respect_till_mapping', true), FILTER_VALIDATE_BOOLEAN),
        ];

        $preview = $reconciler->previewAutoMatch($request->batch_id, $options);

        return response()->json($preview);
    }

    public function autoMatchBatch(Request $request, CardReconciliationService $reconciler)
    {
        $request->validate([
            'batch_id' => 'required|string',
            'window_minutes' => 'nullable|integer|min:5|max:120',
            'min_confidence' => 'nullable|integer|min:50|max:100',
            'exact_amount_only' => 'nullable',
            'card_only' => 'nullable',
            'respect_till_mapping' => 'nullable',
        ]);

        $options = [
            'window_minutes' => (int) $request->get('window_minutes', 30),
            'min_confidence' => (int) $request->get('min_confidence', 80),
            'exact_amount_only' => filter_var($request->get('exact_amount_only', false), FILTER_VALIDATE_BOOLEAN),
            'card_only' => filter_var($request->get('card_only', true), FILTER_VALIDATE_BOOLEAN),
            'respect_till_mapping' => filter_var($request->get('respect_till_mapping', true), FILTER_VALIDATE_BOOLEAN),
        ];

        $stats = $reconciler->autoMatchOrphans($request->batch_id, $options);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Auto-matched {$stats['matched']} transactions.",
                'stats' => $stats,
            ]);
        }

        return redirect()->route('management.card-reconciliation.index')
            ->with('success', "Auto-matched {$stats['matched']} transactions ({$stats['still_orphans']} still unmatched).");
    }

    /**
     * Terminal Mappings - View and manage terminal-to-till mappings
     */
    public function terminalMappings()
    {
        $mappings = TerminalTillMapping::orderBy('terminal_name')->get();

        // Get available terminals from recent card transactions (unique terminals)
        $availableTerminals = CardTransaction::select('terminal_id', 'terminal_name')
            ->whereNotNull('terminal_id')
            ->distinct()
            ->orderBy('terminal_name')
            ->get();

        // Get available tills from POS
        $availableTills = ClosedCash::select('HOST')
            ->whereNotNull('HOST')
            ->where('HOST', '!=', '')
            ->distinct()
            ->orderBy('HOST')
            ->pluck('HOST');

        return view('financials.card-reconciliation.terminal-mappings', compact(
            'mappings',
            'availableTerminals',
            'availableTills'
        ));
    }

    /**
     * Save a terminal-to-till mapping
     */
    public function saveTerminalMapping(Request $request)
    {
        $request->validate([
            'terminal_id' => 'required|string|max:255',
            'terminal_name' => 'nullable|string|max:255',
            'pos_host' => 'required|string|max:255',
        ]);

        $mapping = TerminalTillMapping::updateOrCreate(
            ['terminal_id' => $request->terminal_id],
            [
                'terminal_name' => $request->terminal_name,
                'pos_host' => $request->pos_host,
                'is_active' => true,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Terminal mapping saved successfully.',
                'mapping' => $mapping,
            ]);
        }

        return redirect()->route('management.card-reconciliation.terminal-mappings')
            ->with('success', 'Terminal mapping saved successfully.');
    }

    /**
     * Delete a terminal-to-till mapping
     */
    public function deleteTerminalMapping(Request $request, TerminalTillMapping $mapping)
    {
        $terminalName = $mapping->terminal_name ?: $mapping->terminal_id;
        $mapping->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Terminal mapping for '{$terminalName}' removed.",
            ]);
        }

        return redirect()->route('management.card-reconciliation.terminal-mappings')
            ->with('success', "Terminal mapping for '{$terminalName}' removed.");
    }
}
