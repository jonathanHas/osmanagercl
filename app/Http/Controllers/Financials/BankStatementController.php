<?php

namespace App\Http\Controllers\Financials;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBankStatement;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankStatementController extends Controller
{
    public function index()
    {
        // Get upload history - group by source_filename and show stats
        $uploadHistory = BankTransaction::select(
            'source_filename',
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw('MIN(transaction_date) as date_from'),
            DB::raw('MAX(transaction_date) as date_to'),
            DB::raw('MAX(created_at) as uploaded_at')
        )
            ->groupBy('source_filename')
            ->orderBy('uploaded_at', 'desc')
            ->limit(10)
            ->get();

        // Get recent transactions for overview
        $recentTransactions = BankTransaction::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('financials.bank-statements.index', compact('uploadHistory', 'recentTransactions'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'statement_csv' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            ], [
                'statement_csv.required' => 'Please select a CSV file to upload.',
                'statement_csv.mimes' => 'Please upload a valid CSV or TXT file.',
                'statement_csv.max' => 'File size must not exceed 10MB.',
            ]);

            $file = $request->file('statement_csv');
            $originalName = $file->getClientOriginalName();

            // Check if file with same name was already uploaded (any time)
            $existingUpload = BankTransaction::where('source_filename', $originalName)
                ->exists();

            if ($existingUpload) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'A file with the name "'.$originalName.'" has already been uploaded and processed. Please check the upload history below or use a different file.',
                    ]);
                }

                return redirect()->route('management.bank-statements.index')
                    ->with('warning', 'A file with the name "'.$originalName.'" has already been uploaded and processed. Please check the upload history below or use a different file.');
            }

            $path = $file->store('temp');

            ProcessBankStatement::dispatch($path, $originalName);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File "'.$originalName.'" uploaded successfully and is being processed. Processing usually takes 10-30 seconds.',
                ]);
            }

            return redirect()->route('management.bank-statements.index')
                ->with('success', 'File "'.$originalName.'" uploaded successfully and is being processed. Processing usually takes 10-30 seconds.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->validator->errors()->first(),
                ], 422);
            }

            return redirect()->route('management.bank-statements.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to upload file: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->route('management.bank-statements.index')
                ->with('error', 'Failed to upload file: '.$e->getMessage());
        }
    }

    public function reconciliation()
    {
        return view('financials.bank-statements.reconciliation');
    }

    public function getProcessingStatus(Request $request)
    {
        $filename = $request->get('filename');
        if (! $filename) {
            return response()->json(['status' => 'not_found', 'message' => 'Filename not provided']);
        }

        $cacheKey = "bank_statement_processing_{$filename}_".str_replace(['/', ' ', '.'], '_', $filename);
        $status = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (! $status) {
            return response()->json(['status' => 'not_found', 'message' => 'Processing status not found']);
        }

        return response()->json($status);
    }

    public function getUploadHistory()
    {
        // Get upload history - group by source_filename and show stats
        $uploadHistory = BankTransaction::select(
            'source_filename',
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw('MIN(transaction_date) as date_from'),
            DB::raw('MAX(transaction_date) as date_to'),
            DB::raw('MAX(created_at) as uploaded_at')
        )
            ->groupBy('source_filename')
            ->orderBy('uploaded_at', 'desc')
            ->limit(10)
            ->get();

        // Get recent transactions for overview
        $recentTransactions = BankTransaction::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'uploadHistory' => $uploadHistory,
            'recentTransactions' => $recentTransactions,
            'totalTransactions' => BankTransaction::count(),
        ]);
    }

    public function deleteUpload(Request $request)
    {
        $filename = $request->get('filename');
        if (! $filename) {
            return redirect()->route('management.bank-statements.index')
                ->with('error', 'No filename provided.');
        }

        $deletedCount = BankTransaction::where('source_filename', $filename)->delete();

        if ($deletedCount > 0) {
            return redirect()->route('management.bank-statements.index')
                ->with('success', "Successfully deleted {$deletedCount} transactions from file: {$filename}");
        } else {
            return redirect()->route('management.bank-statements.index')
                ->with('warning', 'No transactions found for the specified file.');
        }
    }

    public function cleanupDuplicates()
    {
        // Find duplicate transactions (same date, description, amounts)
        $duplicates = DB::select('
            SELECT t1.id, t1.source_filename
            FROM bank_transactions t1
            INNER JOIN bank_transactions t2 
            WHERE t1.id > t2.id
            AND t1.transaction_date = t2.transaction_date
            AND t1.description = t2.description
            AND t1.debit_amount = t2.debit_amount
            AND t1.credit_amount = t2.credit_amount
            ORDER BY t1.created_at DESC
        ');

        $duplicateIds = collect($duplicates)->pluck('id');
        $deletedCount = 0;

        if ($duplicateIds->isNotEmpty()) {
            $deletedCount = BankTransaction::whereIn('id', $duplicateIds)->delete();
        }

        return redirect()->route('management.bank-statements.index')
            ->with('success', "Cleanup complete. Removed {$deletedCount} duplicate transactions.");
    }
}
