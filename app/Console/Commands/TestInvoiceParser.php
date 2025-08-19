<?php

namespace App\Console\Commands;

use App\Services\InvoiceParsingService;
use Illuminate\Console\Command;

class TestInvoiceParser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:test-parser {file? : Path to invoice file to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the invoice parser configuration and optionally parse a file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Invoice Parser Configuration...');
        $this->newLine();

        $parsingService = new InvoiceParsingService;

        // Check configuration
        $checks = $parsingService->checkConfiguration();

        // Display configuration status
        $this->info('Configuration Check:');
        $this->table(
            ['Check', 'Status', 'Details'],
            [
                ['Python Exists', $checks['python_exists'] ? '✓' : '✗', $checks['python_version'] ?? 'Not found'],
                ['Parser Script', $checks['parser_script_exists'] ? '✓' : '✗', $checks['parser_script_exists'] ? 'Found' : 'Missing'],
                ['Virtual Env', $checks['venv_exists'] ? '✓' : '✗', $checks['venv_exists'] ? 'Found' : 'Not setup'],
                ['Tesseract OCR', $checks['tesseract_installed'] ? '✓' : '✗', $checks['tesseract_path'] ?? 'Not installed'],
            ]
        );

        if (! empty($checks['errors'])) {
            $this->newLine();
            $this->error('Configuration Issues:');
            foreach ($checks['errors'] as $error) {
                $this->line('  - '.$error);
            }
        }

        // If all checks pass and a file is provided, test parsing
        $file = $this->argument('file');
        if ($file && $checks['all_checks_passed']) {
            $this->newLine();
            $this->info('Testing parser with file: '.$file);

            if (! file_exists($file)) {
                $this->error('File not found: '.$file);

                return 1;
            }

            $this->line('Parsing file...');
            $result = $parsingService->testParser($file);

            if ($result['success']) {
                $this->info('✓ Parsing successful!');
                $this->newLine();

                $data = $result['result']['data'] ?? [];
                $metadata = $result['result']['metadata'] ?? [];

                // Display parsed data
                $this->info('Parsed Data:');
                $this->line('Supplier: '.($data['supplier_name'] ?? 'Unknown'));
                $this->line('Invoice Date: '.($data['invoice_date'] ?? 'Not found'));
                $this->line('Total Amount: €'.number_format($data['total_amount'] ?? 0, 2));
                $this->line('Tax Free: '.($data['is_tax_free'] ? 'Yes' : 'No'));
                $this->line('Credit Note: '.($data['is_credit_note'] ? 'Yes' : 'No'));

                if (! empty($data['vat_breakdown'])) {
                    $this->newLine();
                    $this->info('VAT Breakdown:');
                    $vat = $data['vat_breakdown'];
                    if ($vat['vat_0'] > 0) {
                        $this->line('  0%: €'.number_format($vat['vat_0'], 2));
                    }
                    if ($vat['vat_9'] > 0) {
                        $this->line('  9%: €'.number_format($vat['vat_9'], 2));
                    }
                    if ($vat['vat_13_5'] > 0) {
                        $this->line('  13.5%: €'.number_format($vat['vat_13_5'], 2));
                    }
                    if ($vat['vat_23'] > 0) {
                        $this->line('  23%: €'.number_format($vat['vat_23'], 2));
                    }
                }

                if (! empty($result['result']['warnings'])) {
                    $this->newLine();
                    $this->warn('Warnings:');
                    foreach ($result['result']['warnings'] as $warning) {
                        $this->line('  - '.$warning);
                    }
                }

                $this->newLine();
                $this->info('Metadata:');
                $this->line('Parsing Method: '.($metadata['parsing_method'] ?? 'unknown'));
                $this->line('OCR Used: '.($metadata['ocr_used'] ? 'Yes' : 'No'));
                $this->line('Supplier Detected: '.($metadata['supplier_detected'] ?? 'Unknown'));
                $this->line('Processing Time: '.number_format($metadata['processing_time'] ?? 0, 2).'s');
                $this->line('Confidence: '.number_format(($result['result']['confidence'] ?? 0) * 100, 0).'%');

            } else {
                $this->error('✗ Parsing failed: '.($result['error'] ?? 'Unknown error'));
            }
        } elseif ($file && ! $checks['all_checks_passed']) {
            $this->newLine();
            $this->error('Cannot test parsing - configuration issues detected. Please fix them first.');
        }

        if (! $checks['venv_exists']) {
            $this->newLine();
            $this->warn('Python virtual environment not set up. To set it up:');
            $this->line('1. cd '.base_path('scripts/invoice-parser'));
            $this->line('2. ./setup.sh');
            $this->line('3. Add environment variables to .env file as shown by setup script');
        }

        return $checks['all_checks_passed'] ? 0 : 1;
    }
}
