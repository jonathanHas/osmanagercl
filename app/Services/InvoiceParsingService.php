<?php

namespace App\Services;

use App\Models\InvoiceUploadFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class InvoiceParsingService
{
    protected string $pythonPath;
    protected string $parserScript;
    protected string $venvPath;
    protected int $timeout;
    
    public function __construct()
    {
        $this->pythonPath = config('invoices.parsing.python_executable', '/usr/bin/python3');
        $this->parserScript = config('invoices.parsing.python_parser_script', base_path('scripts/invoice-parser/invoice_parser_laravel.py'));
        $this->venvPath = config('invoices.parsing.python_venv_path', base_path('scripts/invoice-parser/venv'));
        $this->timeout = config('invoices.parsing.max_parse_time', 60);
    }
    
    /**
     * Parse an uploaded invoice file
     * 
     * @param InvoiceUploadFile $file
     * @return array
     * @throws \Exception
     */
    public function parseFile(InvoiceUploadFile $file): array
    {
        // Get the full path to the temporary file
        $filePath = $file->temp_file_path;
        
        if (!$filePath || !file_exists($filePath)) {
            // Check if directory exists to provide better debugging info
            $directory = dirname($filePath ?? '');
            $directoryExists = $directory && is_dir($directory);
            $directoryContents = $directoryExists ? scandir($directory) : [];
            
            $debugInfo = [
                'file_path' => $filePath ?? 'null',
                'directory' => $directory,
                'directory_exists' => $directoryExists,
                'directory_contents' => $directoryContents,
                'file_id' => $file->id,
                'batch_id' => $file->batch_id ?? 'unknown'
            ];
            
            Log::error('Temporary file missing during parsing', $debugInfo);
            
            throw new \Exception('Temporary file not found: ' . ($filePath ?? 'null') . '. Directory exists: ' . ($directoryExists ? 'yes' : 'no'));
        }
        
        // Build the command to execute Python with virtual environment
        $venvPython = $this->venvPath . '/bin/python';
        
        // Use venv Python if it exists, otherwise fall back to system Python
        $pythonExecutable = file_exists($venvPython) ? $venvPython : $this->pythonPath;
        
        // Build command array
        $command = [
            $pythonExecutable,
            $this->parserScript,
            '--file', $filePath,
            '--output', 'json'
        ];
        
        Log::info('Executing parser command', [
            'file_id' => $file->id,
            'command' => implode(' ', $command),
            'file_path' => $filePath
        ]);
        
        // Execute the Python parser
        $result = Process::timeout($this->timeout)->run($command);
        
        // Log the output for debugging
        Log::debug('Parser output', [
            'file_id' => $file->id,
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
            'exit_code' => $result->exitCode()
        ]);
        
        // Check if the command was successful
        if (!$result->successful()) {
            $errorMsg = 'Parser execution failed: ' . $result->errorOutput();
            Log::error($errorMsg, [
                'file_id' => $file->id,
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput()
            ]);
            
            throw new \Exception($errorMsg);
        }
        
        // Parse the JSON output
        $output = json_decode($result->output(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'Invalid JSON from parser: ' . json_last_error_msg();
            Log::error($errorMsg, [
                'file_id' => $file->id,
                'raw_output' => $result->output()
            ]);
            
            throw new \Exception($errorMsg);
        }
        
        return $output;
    }
    
    /**
     * Process the parser output and update the file record
     * 
     * @param InvoiceUploadFile $file
     * @param array $output
     * @return void
     */
    public function processParserOutput(InvoiceUploadFile $file, array $output): void
    {
        if ($output['success']) {
            // Store the parsed data
            $parsedData = $output['data'] ?? [];
            
            // Add metadata to parsed data
            $parsedData['metadata'] = $output['metadata'] ?? [];
            $parsedData['warnings'] = $output['warnings'] ?? [];
            
            // Update file with parsed data
            $file->markAsParsed($parsedData, $output['confidence'] ?? 0.0);
            
            // Log successful parsing
            Log::info('Invoice parsed successfully', [
                'file_id' => $file->id,
                'supplier' => $parsedData['supplier_name'] ?? 'Unknown',
                'confidence' => $output['confidence'],
                'has_warnings' => !empty($output['warnings'])
            ]);
            
            // If there are warnings, log them
            if (!empty($output['warnings'])) {
                Log::warning('Parser warnings detected', [
                    'file_id' => $file->id,
                    'warnings' => $output['warnings']
                ]);
            }
            
            // Auto-create invoice if confidence is high enough
            $confidence = $output['confidence'] ?? 0.0;
            // Convert confidence to percentage if it's a decimal (0.85 -> 85)
            if ($confidence <= 1.0) {
                $confidence = $confidence * 100;
            }
            $autoCreateThreshold = config('invoices.parsing.auto_create_threshold', 80.0);
            
            // Check for duplicates first
            $duplicateCheck = $this->checkForDuplicate($file);
            
            if ($duplicateCheck) {
                // Mark as review with duplicate warning
                $file->status = 'review';
                $file->error_message = 'Potential duplicate invoice found: ' . $duplicateCheck->invoice_number . 
                                      ' (ID: ' . $duplicateCheck->id . ')';
                $file->save();
                
                Log::info('Invoice marked for review due to potential duplicate', [
                    'file_id' => $file->id,
                    'existing_invoice_id' => $duplicateCheck->id,
                    'existing_invoice_number' => $duplicateCheck->invoice_number
                ]);
            } elseif ($confidence >= $autoCreateThreshold) {
                // High confidence and no duplicate - auto-create invoice
                $this->autoCreateInvoice($file);
            } else {
                // Low confidence - mark for review
                $file->status = 'review';
                $file->save();
                
                Log::info('Invoice marked for review due to low confidence', [
                    'file_id' => $file->id,
                    'confidence' => $confidence,
                    'threshold' => $autoCreateThreshold
                ]);
            }
        } else {
            // Handle parsing failure
            $errors = $output['errors'] ?? [];
            $errorMessage = 'Parsing failed';
            
            if (!empty($errors)) {
                $errorMessage = $errors[0]['message'] ?? 'Unknown error';
            }
            
            // Update file status
            $file->parsing_errors = $errors;
            $file->status = 'failed';
            $file->error_message = $errorMessage;
            $file->save();
            
            Log::error('Invoice parsing failed', [
                'file_id' => $file->id,
                'errors' => $errors
            ]);
        }
    }
    
    /**
     * Automatically create an invoice from parsed file data
     * 
     * @param InvoiceUploadFile $file
     * @return void
     */
    protected function autoCreateInvoice(InvoiceUploadFile $file): void
    {
        try {
            $creationService = new \App\Services\InvoiceCreationService();
            $invoice = $creationService->createFromParsedFile($file);
            
            if ($invoice) {
                Log::info('Invoice auto-created from parsed file', [
                    'file_id' => $file->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-create invoice', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);
            
            // Mark for review instead of failing completely
            $file->status = 'review';
            $file->error_message = 'Auto-creation failed: ' . $e->getMessage();
            $file->save();
        }
    }
    
    /**
     * Check if the Python parser is properly configured
     * 
     * @return array
     */
    public function checkConfiguration(): array
    {
        $checks = [
            'python_exists' => false,
            'parser_script_exists' => false,
            'venv_exists' => false,
            'tesseract_installed' => false,
            'errors' => []
        ];
        
        // Check Python executable
        $pythonCheck = Process::run([$this->pythonPath, '--version']);
        if ($pythonCheck->successful()) {
            $checks['python_exists'] = true;
            $checks['python_version'] = trim($pythonCheck->output());
        } else {
            $checks['errors'][] = 'Python not found at: ' . $this->pythonPath;
        }
        
        // Check parser script
        if (file_exists($this->parserScript)) {
            $checks['parser_script_exists'] = true;
        } else {
            $checks['errors'][] = 'Parser script not found at: ' . $this->parserScript;
        }
        
        // Check virtual environment
        $venvPython = $this->venvPath . '/bin/python';
        if (file_exists($venvPython)) {
            $checks['venv_exists'] = true;
        } else {
            $checks['errors'][] = 'Virtual environment not found at: ' . $this->venvPath;
        }
        
        // Check tesseract for OCR
        $tesseractCheck = Process::run(['which', 'tesseract']);
        if ($tesseractCheck->successful()) {
            $checks['tesseract_installed'] = true;
            $checks['tesseract_path'] = trim($tesseractCheck->output());
        } else {
            $checks['errors'][] = 'Tesseract OCR not installed (required for scanned documents)';
        }
        
        $checks['all_checks_passed'] = empty($checks['errors']);
        
        return $checks;
    }
    
    /**
     * Test the parser with a sample file
     * 
     * @param string $filePath
     * @return array
     */
    public function testParser(string $filePath): array
    {
        // Run the parser directly without using InvoiceUploadFile model
        try {
            // Build the command to execute Python with virtual environment
            $venvPython = $this->venvPath . '/bin/python';
            
            // Use venv Python if it exists, otherwise fall back to system Python
            $pythonExecutable = file_exists($venvPython) ? $venvPython : $this->pythonPath;
            
            // Build command array
            $command = [
                $pythonExecutable,
                $this->parserScript,
                '--file', $filePath,
                '--output', 'json'
            ];
            
            Log::info('Testing parser command', [
                'command' => implode(' ', $command),
                'file_path' => $filePath
            ]);
            
            // Execute the Python parser
            $result = Process::timeout($this->timeout)->run($command);
            
            if (!$result->successful()) {
                return [
                    'success' => false,
                    'error' => 'Parser execution failed: ' . $result->errorOutput()
                ];
            }
            
            // Parse the JSON output
            $output = json_decode($result->output(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON from parser: ' . json_last_error_msg()
                ];
            }
            
            return [
                'success' => true,
                'result' => $output
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check for potential duplicate invoices
     */
    protected function checkForDuplicate(InvoiceUploadFile $file): ?\App\Models\Invoice
    {
        // Need supplier first
        $supplierName = $file->supplier_detected;
        if (!$supplierName) {
            return null;
        }
        
        // Find supplier
        $supplier = \App\Models\AccountingSupplier::where('name', $supplierName)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($supplierName)])
            ->first();
            
        if (!$supplier) {
            return null;
        }
        
        $invoiceDate = $file->parsed_invoice_date;
        if (!$invoiceDate) {
            return null;
        }
        
        $dateCarbon = \Carbon\Carbon::parse($invoiceDate);
        $totalAmount = $file->parsed_total_amount ?: 0;
        
        // Look for invoices from the same supplier within 1 day and similar amount
        $query = \App\Models\Invoice::where('supplier_id', $supplier->id)
            ->whereBetween('invoice_date', [
                $dateCarbon->copy()->subDay()->format('Y-m-d'),
                $dateCarbon->copy()->addDay()->format('Y-m-d')
            ]);
            
        // Check for similar amounts (within €0.50)
        $potentialDuplicates = $query->get()->filter(function ($invoice) use ($totalAmount) {
            return abs($invoice->total_amount - $totalAmount) <= 0.50;
        });
        
        return $potentialDuplicates->first();
    }
}