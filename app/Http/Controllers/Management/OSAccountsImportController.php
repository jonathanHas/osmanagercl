<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\AccountingSupplier;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\InvoiceVatLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OSAccountsImportController extends Controller
{
    public function index()
    {
        $status = $this->getImportStatus();

        return view('management.osaccounts-import.index', compact('status'));
    }

    public function validateConnection()
    {
        try {
            // Test OSAccounts database connection
            $osAccountsCount = DB::connection('osaccounts')->table('INVOICES')->count();

            return response()->json([
                'success' => true,
                'message' => "OSAccounts connection successful. Found {$osAccountsCount} invoices.",
                'invoice_count' => $osAccountsCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OSAccounts database connection failed: '.$e->getMessage(),
            ]);
        }
    }

    public function checkSupplierMapping()
    {
        try {
            $unmappedSuppliers = AccountingSupplier::whereNull('external_osaccounts_id')
                ->whereNotNull('external_pos_id')
                ->count();

            $totalSuppliers = AccountingSupplier::whereNotNull('external_pos_id')->count();
            $mappedSuppliers = $totalSuppliers - $unmappedSuppliers;

            return response()->json([
                'success' => true,
                'total_suppliers' => $totalSuppliers,
                'mapped_suppliers' => $mappedSuppliers,
                'unmapped_suppliers' => $unmappedSuppliers,
                'mapping_complete' => $unmappedSuppliers === 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check supplier mapping: '.$e->getMessage(),
            ]);
        }
    }

    public function syncSuppliers(Request $request)
    {
        $dryRun = $request->boolean('dry_run', false);

        return $this->streamCommand('osaccounts:sync-supplier-mapping', [
            '--dry-run' => $dryRun,
            '--detailed' => true,
            '--user' => auth()->id(), // Pass current authenticated user's ID
        ], 'Supplier Sync');
    }

    public function importSuppliers(Request $request)
    {
        $dryRun = $request->boolean('dry_run', false);
        $force = $request->boolean('force', false);

        $options = [];
        if ($dryRun) {
            $options['--dry-run'] = true;
        }
        if ($force) {
            $options['--force'] = true;
        }

        // Pass current authenticated user's ID
        $options['--user'] = auth()->id();

        return $this->streamCommand('osaccounts:import-suppliers', $options, 'Suppliers Import');
    }

    public function importInvoices(Request $request)
    {
        \Log::info('Import Invoices Request', [
            'method' => $request->method(),
            'all_data' => $request->all(),
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'role' => $request->user()->role?->name,
            ] : null,
        ]);

        try {
            $request->validate([
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'dry_run' => 'boolean',
                'force' => 'boolean',
                'limit' => 'nullable|integer|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            throw $e;
        }

        \Log::info('Validation passed, building options');

        $options = [
            '--date-from' => $request->date_from,
            '--date-to' => $request->date_to,
            '--user' => auth()->id(), // Pass current authenticated user's ID
        ];

        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }

        if ($request->boolean('force')) {
            $options['--force'] = true;
        }

        if ($request->filled('limit')) {
            $options['--limit'] = $request->limit;
        }

        \Log::info('About to call streamCommand', ['options' => $options]);

        return $this->streamCommand('osaccounts:import-invoices', $options, 'Invoice Import');
    }

    public function importVatLines(Request $request)
    {
        $options = [];

        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }

        if ($request->boolean('force')) {
            $options['--force'] = true;
        }

        // Pass current authenticated user's ID
        $options['--user'] = auth()->id();

        return $this->streamCommand('osaccounts:import-invoice-vat-lines', $options, 'VAT Lines Import');
    }

    public function importAttachments(Request $request)
    {
        $request->validate([
            'base_path' => 'nullable|string',
            'dry_run' => 'boolean',
            'force' => 'boolean',
        ]);

        $options = [
            '--base-path' => $request->base_path ?: config('osaccounts.file_path', '/var/www/html/OSManager/invoice_storage'),
            '--user' => auth()->id(), // Pass current authenticated user's ID
        ];

        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }

        if ($request->boolean('force')) {
            $options['--force'] = true;
        }

        return $this->streamCommand('osaccounts:import-attachments', $options, 'Attachments Import');
    }

    public function importVatReturns(Request $request)
    {
        $options = [];

        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }

        if ($request->boolean('force')) {
            $options['--force'] = true;
        }

        return $this->streamCommand('osaccounts:import-vat-returns', $options, 'VAT Returns Import');
    }

    public function getImportStats()
    {
        try {
            $stats = [
                'total_invoices' => Invoice::count(),
                'osaccounts_invoices' => Invoice::whereNotNull('external_osaccounts_id')->count(),
                'invoices_with_vat_lines' => Invoice::has('vatLines')->count(),
                'invoices_with_attachments' => Invoice::has('attachments')->count(),
                'recent_imports' => Invoice::whereNotNull('external_osaccounts_id')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ];

            // Get date range of imported invoices
            $dateRange = Invoice::whereNotNull('external_osaccounts_id')
                ->selectRaw('MIN(invoice_date) as earliest, MAX(invoice_date) as latest')
                ->first();

            $stats['import_date_range'] = $dateRange;

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get import stats: '.$e->getMessage(),
            ]);
        }
    }

    public function testStream()
    {
        return new StreamedResponse(function () {
            $this->sendStreamMessage('start', 'Starting test stream...');
            sleep(1);

            $this->sendStreamMessage('output', 'This is a test message');
            sleep(1);

            $this->sendStreamMessage('output', '🚀 Testing emojis and special chars');
            sleep(1);

            $this->sendStreamMessage('output', 'Another test message');
            sleep(1);

            $this->sendStreamMessage('complete', 'Test stream completed successfully', true);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function getImportStatus()
    {
        try {
            $status = [
                'osaccounts_connection' => false,
                'supplier_mapping_complete' => false,
                'invoices_imported' => 0,
                'vat_lines_imported' => 0,
                'attachments_imported' => 0,
                'last_import_date' => null,
            ];

            // Check OSAccounts connection
            try {
                DB::connection('osaccounts')->getPdo();
                $status['osaccounts_connection'] = true;
            } catch (\Exception $e) {
                // Connection failed
            }

            // Check supplier mapping
            $unmapped = AccountingSupplier::whereNull('external_osaccounts_id')
                ->whereNotNull('external_pos_id')
                ->count();
            $status['supplier_mapping_complete'] = $unmapped === 0;

            // Get import counts
            $status['invoices_imported'] = Invoice::whereNotNull('external_osaccounts_id')->count();
            $status['vat_lines_imported'] = InvoiceVatLine::count();
            $status['attachments_imported'] = InvoiceAttachment::count();

            // Get last import date
            $lastImport = Invoice::whereNotNull('external_osaccounts_id')
                ->latest('created_at')
                ->first();
            $status['last_import_date'] = $lastImport?->created_at;

            return $status;
        } catch (\Exception $e) {
            Log::error('Failed to get import status: '.$e->getMessage());

            return [
                'osaccounts_connection' => false,
                'supplier_mapping_complete' => false,
                'invoices_imported' => 0,
                'vat_lines_imported' => 0,
                'attachments_imported' => 0,
                'last_import_date' => null,
            ];
        }
    }

    private function streamCommand(string $command, array $options, string $title)
    {
        \Log::info('StreamCommand called', [
            'command' => $command,
            'options' => $options,
            'title' => $title,
        ]);

        return new StreamedResponse(function () use ($command, $options, $title) {
            // Send initial message
            $this->sendStreamMessage('start', "Starting {$title}...");

            try {
                // Build the actual command string with full path
                $artisanPath = base_path('artisan');
                $commandString = 'php '.$artisanPath.' '.$command;
                foreach ($options as $key => $value) {
                    if (is_bool($value) && $value) {
                        $commandString .= " {$key}";
                    } elseif (! is_bool($value)) {
                        $commandString .= " {$key}=".escapeshellarg($value);
                    }
                }

                // NOTE: We don't use sudo here anymore because:
                // 1. The import command now sets proper group permissions (664)
                // 2. In production, the web server runs as www-data already
                // 3. For CLI/cron, the user should be in the www-data group
                // This avoids sudo password prompts in production

                // Log the command being run for debugging
                Log::info("Running command: {$commandString}");

                // Execute the command and capture output
                $output = shell_exec($commandString.' 2>&1');
                $exitCode = 0; // shell_exec doesn't provide exit code easily

                // Log output for debugging
                Log::info('Command output length: '.strlen($output ?? ''));

                if (empty(trim($output ?? ''))) {
                    $this->sendStreamMessage('error', 'Command executed but produced no output. Check logs for details.');

                    return;
                }

                // Stream the output line by line
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (! empty($trimmed)) {
                        $this->sendStreamMessage('output', $trimmed);
                        usleep(50000); // Small delay for better UX
                    }
                }

                $this->sendStreamMessage('complete',
                    "{$title} completed successfully",
                    true
                );

            } catch (\Exception $e) {
                Log::error('Stream command error: '.$e->getMessage());
                $this->sendStreamMessage('error', "Error during {$title}: ".$e->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function syncPaymentStatus(Request $request)
    {
        try {
            $isDryRun = $request->boolean('dry_run', true);

            if ($isDryRun) {
                // Preview mode - use the command to show what would be updated
                $output = [];
                $exitCode = \Artisan::call('osaccounts:import-invoices', [
                    '--update-existing' => true,
                    '--dry-run' => true,
                    '--user' => auth()->id(),
                ], $output);

                $outputText = \Artisan::output();

                // Parse the results from the command output
                preg_match('/Total Processed\s+\|\s*(\d+)/', $outputText, $totalMatches);
                preg_match('/Would Update\s+\|\s*(\d+)/', $outputText, $updateMatches);
                preg_match('/Skipped\s+\|\s*(\d+)/', $outputText, $skippedMatches);
                preg_match('/Errors\s+\|\s*(\d+)/', $outputText, $errorMatches);

                $total = $totalMatches[1] ?? 0;
                $updated = $updateMatches[1] ?? 0;
                $skipped = $skippedMatches[1] ?? 0;
                $errors = $errorMatches[1] ?? 0;

                // Parse sample invoice changes if they exist
                $samples = $this->parseSampleInvoiceChanges($outputText);

                // Debug: Log the samples for troubleshooting
                \Log::info('Parsed samples from command output', ['samples' => $samples]);

                return response()->json([
                    'success' => true,
                    'message' => "Preview complete. {$updated} invoice(s) would be updated with payment status from OSAccounts.",
                    'summary' => [
                        'total' => (int) $total,
                        'updated' => (int) $updated,
                        'skipped' => (int) $skipped,
                        'errors' => (int) $errors,
                    ],
                    'samples' => $samples,
                ]);
            } else {
                // Actually sync the payment status
                $exitCode = \Artisan::call('osaccounts:import-invoices', [
                    '--update-existing' => true,
                    '--user' => auth()->id(),
                ]);

                $outputText = \Artisan::output();

                // Parse the results from the command output
                preg_match('/Total Processed\s+\|\s*(\d+)/', $outputText, $totalMatches);
                preg_match('/Updated\s+\|\s*(\d+)/', $outputText, $updateMatches);
                preg_match('/Skipped\s+\|\s*(\d+)/', $outputText, $skippedMatches);
                preg_match('/Errors\s+\|\s*(\d+)/', $outputText, $errorMatches);

                $total = $totalMatches[1] ?? 0;
                $updated = $updateMatches[1] ?? 0;
                $skipped = $skippedMatches[1] ?? 0;
                $errors = $errorMatches[1] ?? 0;

                if ($exitCode === 0) {
                    return response()->json([
                        'success' => true,
                        'message' => "Payment status sync completed successfully. {$updated} invoice(s) updated.",
                        'summary' => [
                            'total' => (int) $total,
                            'updated' => (int) $updated,
                            'skipped' => (int) $skipped,
                            'errors' => (int) $errors,
                        ],
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Payment status sync failed. Check logs for details.',
                        'command_output' => $outputText,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Payment status sync error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Payment status sync failed: '.$e->getMessage(),
            ]);
        }
    }

    private function sendStreamMessage(string $type, string $message, ?bool $success = null)
    {
        $data = [
            'type' => $type,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($success !== null) {
            $data['success'] = $success;
        }

        echo 'data: '.json_encode($data)."\n\n";
        ob_flush();
        flush();
    }

    /**
     * Parse sample invoice changes from command output
     */
    private function parseSampleInvoiceChanges(string $output): array
    {
        $samples = [];

        // Look for the sample table section in the output
        if (strpos($output, '📋 Sample Invoice Changes (Dry Run):') !== false) {
            $tableStart = strpos($output, '📋 Sample Invoice Changes (Dry Run):');
            $tableSection = substr($output, $tableStart);

            $lines = explode("\n", $tableSection);
            foreach ($lines as $line) {
                $line = trim($line);

                // Look for data rows using regex pattern
                if (preg_match('/\| (\d+)\s+\| (.+?) \| (.+?) \| (.+?) \| (.+?) \| (.+?) \|/', $line, $matches)) {
                    $samples[] = [
                        'invoice_number' => trim($matches[1]),
                        'supplier_name' => trim($matches[2]),
                        'old_status' => trim($matches[3]),
                        'new_status' => trim($matches[4]),
                        'payment_date' => trim($matches[5]) === 'N/A' ? null : trim($matches[5]),
                        'amount' => trim($matches[6]),
                    ];
                }
            }
        }

        return $samples;
    }
}
