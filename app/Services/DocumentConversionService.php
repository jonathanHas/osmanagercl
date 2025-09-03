<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DocumentConversionService
{
    /**
     * Convert a document to PDF using LibreOffice.
     *
     * @param  string  $inputPath  Full path to the input file
     * @param  string  $outputDir  Directory where the converted PDF should be saved
     * @param  string|null  $outputFilename  Optional custom filename for the PDF
     * @return string|null Path to the converted PDF file, or null on failure
     */
    public function convertToPdf(string $inputPath, string $outputDir, ?string $outputFilename = null): ?string
    {
        // Validate input file exists
        if (! file_exists($inputPath)) {
            Log::error('Document conversion failed: Input file does not exist', [
                'input_path' => $inputPath,
            ]);

            return null;
        }

        // Get file extension to determine if conversion is needed
        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $convertibleExtensions = ['doc', 'docx', 'xls', 'xlsx', 'odt', 'ods'];

        if (! in_array($extension, $convertibleExtensions)) {
            Log::error('Document conversion failed: Unsupported file type', [
                'input_path' => $inputPath,
                'extension' => $extension,
            ]);

            return null;
        }

        // Ensure output directory exists with proper permissions
        if (! is_dir($outputDir)) {
            if (! mkdir($outputDir, 0775, true)) {
                Log::error('Document conversion failed: Could not create output directory', [
                    'output_dir' => $outputDir,
                ]);

                return null;
            }
        }

        // Ensure directory has proper permissions
        if (is_dir($outputDir)) {
            chmod($outputDir, 0775);
        }

        // Generate output filename if not provided
        if (! $outputFilename) {
            $basename = pathinfo($inputPath, PATHINFO_FILENAME);
            $outputFilename = $basename.'.pdf';
        }

        $outputPath = $outputDir.'/'.$outputFilename;

        // Skip conversion if PDF already exists and is newer than source
        if (file_exists($outputPath) && filemtime($outputPath) >= filemtime($inputPath)) {
            return $outputPath;
        }

        try {
            // Create a temporary home directory for LibreOffice
            $tempHome = sys_get_temp_dir().'/libreoffice_'.uniqid();
            if (! mkdir($tempHome, 0755, true)) {
                Log::error('Could not create temporary home directory for LibreOffice', [
                    'temp_home' => $tempHome,
                ]);

                return null;
            }

            // Use LibreOffice to convert the document
            $command = [
                'soffice',
                '--headless',
                '--invisible',
                '--nodefault',
                '--nolockcheck',
                '--nologo',
                '--norestore',
                '--accept=pipe,name=test'.uniqid().';urp;',
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDir,
                $inputPath,
            ];

            $process = new Process($command);
            $process->setTimeout(60); // 60 second timeout

            // Set environment variables for LibreOffice
            $process->setEnv([
                'HOME' => $tempHome,
                'TMPDIR' => $tempHome,
                'XDG_CONFIG_HOME' => $tempHome.'/.config',
                'XDG_DATA_HOME' => $tempHome.'/.local/share',
                'XDG_CACHE_HOME' => $tempHome.'/.cache',
            ]);

            $process->run();

            // Clean up temporary home directory
            $this->removeDirectory($tempHome);

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // LibreOffice generates the PDF with the same basename as the input
            $defaultOutputPath = $outputDir.'/'.pathinfo($inputPath, PATHINFO_FILENAME).'.pdf';

            // If we need a custom filename, rename the file
            if ($outputFilename !== pathinfo($inputPath, PATHINFO_FILENAME).'.pdf' && file_exists($defaultOutputPath)) {
                rename($defaultOutputPath, $outputPath);
            } elseif (file_exists($defaultOutputPath)) {
                $outputPath = $defaultOutputPath;
            }

            // Verify the converted file was created
            if (! file_exists($outputPath)) {
                Log::error('Document conversion failed: Output file was not created', [
                    'input_path' => $inputPath,
                    'expected_output' => $outputPath,
                    'process_output' => $process->getOutput(),
                    'process_error' => $process->getErrorOutput(),
                ]);

                return null;
            }

            // Set proper permissions
            chmod($outputPath, 0644);

            Log::info('Document converted successfully', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return $outputPath;

        } catch (ProcessFailedException $e) {
            Log::error('Document conversion failed: LibreOffice process failed', [
                'input_path' => $inputPath,
                'output_dir' => $outputDir,
                'error' => $e->getMessage(),
                'process_output' => $e->getProcess()->getOutput(),
                'process_error' => $e->getProcess()->getErrorOutput(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Document conversion failed: Unexpected error', [
                'input_path' => $inputPath,
                'output_dir' => $outputDir,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if a file type can be converted to PDF.
     */
    public function canConvert(string $mimeType): bool
    {
        $convertibleMimes = [
            'application/msword', // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.ms-excel', // .xls
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.oasis.opendocument.text', // .odt
            'application/vnd.oasis.opendocument.spreadsheet', // .ods
        ];

        return in_array($mimeType, $convertibleMimes);
    }

    /**
     * Get the expected PDF filename for a given source file.
     */
    public function getConvertedPdfFilename(string $originalFilename): string
    {
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);

        return $basename.'.pdf';
    }

    /**
     * Clean up old converted PDF files that are no longer needed.
     * This can be called periodically to free up disk space.
     */
    public function cleanupOldConversions(int $daysOld = 30): int
    {
        $conversionDir = storage_path('app/private/conversions');

        if (! is_dir($conversionDir)) {
            return 0;
        }

        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        $deletedCount = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($conversionDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                try {
                    unlink($file->getPathname());
                    $deletedCount++;
                    Log::info('Cleaned up old converted file', [
                        'file' => $file->getPathname(),
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old converted file', [
                        'file' => $file->getPathname(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Recursively remove a directory and its contents.
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dir.'/'.$file;
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($dir);
    }
}
