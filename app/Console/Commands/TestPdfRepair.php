<?php

namespace App\Console\Commands;

use App\Services\PdfRepairService;
use Illuminate\Console\Command;

class TestPdfRepair extends Command
{
    protected $signature = 'test:pdf-repair {file}';

    protected $description = 'Test PDF repair service with a file';

    public function handle()
    {
        $originalFile = $this->argument('file');

        if (! file_exists($originalFile)) {
            $this->error("File not found: $originalFile");

            return 1;
        }

        // Create a temp copy for testing
        $tempFile = '/tmp/'.basename($originalFile).'.test';
        if (! copy($originalFile, $tempFile)) {
            $this->error('Failed to create temp copy');

            return 1;
        }

        $this->info('Testing PDF Repair Service');
        $this->info('File: '.basename($originalFile));
        $this->line('');

        $service = new PdfRepairService;

        // Check if needs repair
        $needsRepair = $service->needsRepair($tempFile);
        $this->line('Needs repair: '.($needsRepair ? 'YES' : 'NO'));

        if ($needsRepair) {
            // Get stats
            $stats = $service->getRepairStats($tempFile);
            $this->line('PDF offset: '.$stats['pdf_offset'].' bytes');
            $this->line('Corrupted bytes: '.$stats['corrupted_bytes'].' bytes');
            $this->line('Corrupted preview: '.($stats['corrupted_data_preview'] ?? 'N/A'));
            $this->line('Supplier: '.($stats['supplier_detected'] ?? 'Unknown'));
            $this->line('');

            // Attempt repair
            $this->info('Attempting repair...');
            $repairSuccess = $service->repair($tempFile);

            if ($repairSuccess) {
                $this->info('✓ Repair successful!');

                // Verify
                $stillNeedsRepair = $service->needsRepair($tempFile);
                $this->line('Still needs repair: '.($stillNeedsRepair ? 'YES' : 'NO'));

                $mimeType = mime_content_type($tempFile);
                $this->line('MIME type: '.$mimeType);

                $fileType = trim(shell_exec('file -b '.escapeshellarg($tempFile)));
                $this->line('File type: '.$fileType);

                $this->info('');
                $this->info('✓ The PDF has been repaired and should upload correctly!');
            } else {
                $this->error('✗ Repair failed!');
            }
        } else {
            $this->info('File is already a valid PDF');
        }

        // Cleanup
        unlink($tempFile);

        return 0;
    }
}
