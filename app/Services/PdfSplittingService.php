<?php

namespace App\Services;

use App\Models\InvoiceUploadFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfSplittingService
{
    protected string $pythonPath;
    protected string $splitterScript;
    protected string $venvPath;
    protected int $timeout;

    public function __construct()
    {
        $this->pythonPath = config('invoices.parsing.python_executable', '/usr/bin/python3');
        $this->splitterScript = base_path('scripts/invoice-parser/pdf_splitter.py');
        $this->venvPath = config('invoices.parsing.python_venv_path', base_path('scripts/invoice-parser/venv'));
        $this->timeout = 60;
    }

    /**
     * Get the number of pages in a PDF file
     */
    public function getPageCount(InvoiceUploadFile $file): int
    {
        if (!$file->isPdf() || !$file->tempFileExists()) {
            return 0;
        }

        try {
            // Build the command to execute Python with virtual environment
            $venvPython = $this->venvPath.'/bin/python';
            $pythonExecutable = file_exists($venvPython) ? $venvPython : $this->pythonPath;

            $command = [
                $pythonExecutable,
                $this->splitterScript,
                '--action', 'count',
                '--file', $file->temp_file_path,
            ];

            Log::info('Getting PDF page count', [
                'file_id' => $file->id,
                'command' => implode(' ', $command),
            ]);

            $result = Process::timeout($this->timeout)->run($command);

            if (!$result->successful()) {
                Log::error('PDF page count failed', [
                    'file_id' => $file->id,
                    'command' => implode(' ', $command),
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                    'std_output' => $result->output(),
                    'python_executable' => $pythonExecutable,
                    'python_exists' => file_exists($pythonExecutable),
                    'script_exists' => file_exists($this->splitterScript),
                    'file_exists' => file_exists($file->temp_file_path),
                    'file_readable' => is_readable($file->temp_file_path),
                ]);
                return 0;
            }

            $output = json_decode($result->output(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from PDF page counter', [
                    'file_id' => $file->id,
                    'output' => $result->output(),
                ]);
                return 0;
            }

            return $output['page_count'] ?? 0;
        } catch (\Exception $e) {
            Log::error('Exception getting PDF page count', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Generate page thumbnails for preview
     */
    public function generateThumbnails(InvoiceUploadFile $file): array
    {
        if (!$file->isPdf() || !$file->tempFileExists()) {
            return [];
        }

        try {
            // Build the command to execute Python with virtual environment
            $venvPython = $this->venvPath.'/bin/python';
            $pythonExecutable = file_exists($venvPython) ? $venvPython : $this->pythonPath;

            $command = [
                $pythonExecutable,
                $this->splitterScript,
                '--action', 'thumbnails',
                '--file', $file->temp_file_path,
            ];

            Log::info('Generating PDF thumbnails', [
                'file_id' => $file->id,
                'command' => implode(' ', $command),
            ]);

            $result = Process::timeout($this->timeout)->run($command);

            if (!$result->successful()) {
                Log::error('PDF thumbnail generation failed', [
                    'file_id' => $file->id,
                    'command' => implode(' ', $command),
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                    'std_output' => $result->output(),
                    'python_executable' => $pythonExecutable,
                    'python_exists' => file_exists($pythonExecutable),
                    'script_exists' => file_exists($this->splitterScript),
                    'file_exists' => file_exists($file->temp_file_path),
                    'file_readable' => is_readable($file->temp_file_path),
                ]);
                return [];
            }

            $output = json_decode($result->output(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from PDF thumbnail generator', [
                    'file_id' => $file->id,
                    'output' => $result->output(),
                ]);
                return [];
            }

            return $output['thumbnails'] ?? [];
        } catch (\Exception $e) {
            Log::error('Exception generating PDF thumbnails', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Split a PDF file by page ranges
     * 
     * @param InvoiceUploadFile $file
     * @param array $pageRanges Array of page ranges, e.g., ['1', '2-3', '4']
     * @return array Array of new InvoiceUploadFile records
     */
    public function splitPdf(InvoiceUploadFile $file, array $pageRanges): array
    {
        if (!$file->canBeSplit()) {
            throw new \InvalidArgumentException('File cannot be split');
        }

        try {
            // Build the command to execute Python with virtual environment
            $venvPython = $this->venvPath.'/bin/python';
            $pythonExecutable = file_exists($venvPython) ? $venvPython : $this->pythonPath;

            $outputDir = dirname($file->temp_file_path).'/splits';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0775, true);
            }

            $command = [
                $pythonExecutable,
                $this->splitterScript,
                '--action', 'split',
                '--file', $file->temp_file_path,
                '--output-dir', $outputDir,
                '--ranges', implode(',', $pageRanges),
            ];

            Log::info('Splitting PDF', [
                'file_id' => $file->id,
                'ranges' => $pageRanges,
                'command' => implode(' ', $command),
            ]);

            $result = Process::timeout($this->timeout)->run($command);

            if (!$result->successful()) {
                $errorOutput = $result->errorOutput();
                $stdOutput = $result->output();
                $exitCode = $result->exitCode();
                
                Log::error('PDF splitting failed', [
                    'file_id' => $file->id,
                    'command' => implode(' ', $command),
                    'exit_code' => $exitCode,
                    'error_output' => $errorOutput,
                    'std_output' => $stdOutput,
                    'file_exists' => file_exists($file->temp_file_path),
                    'file_readable' => is_readable($file->temp_file_path),
                    'output_dir_exists' => is_dir($outputDir),
                    'output_dir_writable' => is_writable($outputDir),
                    'python_executable' => $pythonExecutable,
                    'python_exists' => file_exists($pythonExecutable),
                    'script_exists' => file_exists($this->splitterScript),
                    'script_readable' => is_readable($this->splitterScript),
                ]);
                
                // Provide more helpful error message
                $errorMessage = 'PDF splitting failed';
                if (!empty($errorOutput)) {
                    $errorMessage .= ': ' . $errorOutput;
                } elseif (!empty($stdOutput)) {
                    $errorMessage .= ': ' . $stdOutput;
                } else {
                    $errorMessage .= ' with exit code ' . $exitCode;
                }
                
                throw new \Exception($errorMessage);
            }

            $output = json_decode($result->output(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from PDF splitter', [
                    'file_id' => $file->id,
                    'output' => $result->output(),
                ]);
                throw new \Exception('Invalid JSON from PDF splitter');
            }

            $splitFiles = [];
            $splitFilePaths = $output['split_files'] ?? [];

            foreach ($splitFilePaths as $index => $splitPath) {
                if (!file_exists($splitPath)) {
                    Log::warning('Split file not found', [
                        'expected_path' => $splitPath,
                        'index' => $index,
                    ]);
                    continue;
                }

                $pageRange = $pageRanges[$index] ?? 'unknown';
                $originalExt = pathinfo($file->original_filename, PATHINFO_EXTENSION);
                $originalName = pathinfo($file->original_filename, PATHINFO_FILENAME);
                
                // Create a descriptive filename
                $newFilename = $originalName . '_page_' . str_replace('-', '_to_', $pageRange) . '.' . $originalExt;
                
                // Generate new stored filename
                $storedName = Str::uuid() . '.pdf';
                
                // Calculate path within batch folder
                $batchFolder = dirname($file->temp_path);
                $newTempPath = $batchFolder . '/' . $storedName;
                
                // Move file to batch folder
                $newFullPath = Storage::disk('local')->path($newTempPath);
                if (!rename($splitPath, $newFullPath)) {
                    Log::error('Failed to move split file', [
                        'from' => $splitPath,
                        'to' => $newFullPath,
                    ]);
                    continue;
                }

                // Set proper permissions
                chmod($newFullPath, 0664);
                
                // Calculate file hash and size
                $fileHash = hash_file('sha256', $newFullPath);
                $fileSize = filesize($newFullPath);
                
                // Create new file record
                $splitFile = InvoiceUploadFile::create([
                    'bulk_upload_id' => $file->bulk_upload_id,
                    'original_filename' => $newFilename,
                    'stored_filename' => $storedName,
                    'temp_path' => $newTempPath,
                    'mime_type' => 'application/pdf',
                    'file_size' => $fileSize,
                    'file_hash' => $fileHash,
                    'page_count' => $this->countPagesInRange($pageRange),
                    'is_split' => true,
                    'parent_file_id' => $file->id,
                    'page_range' => $pageRange,
                    'status' => 'uploaded',
                    'upload_progress' => 100,
                    'uploaded_at' => now(),
                ]);

                $splitFiles[] = $splitFile;
                
                Log::info('Created split file', [
                    'original_file_id' => $file->id,
                    'split_file_id' => $splitFile->id,
                    'page_range' => $pageRange,
                    'filename' => $newFilename,
                ]);
            }

            // Clean up splits directory
            if (is_dir($outputDir)) {
                $this->removeDirectory($outputDir);
            }

            // Update original file status to indicate it was split
            $file->update(['status' => 'split_processed']);

            // Update batch total file count
            $batch = $file->bulkUpload;
            $batch->increment('total_files', count($splitFiles));

            Log::info('PDF splitting completed', [
                'original_file_id' => $file->id,
                'split_count' => count($splitFiles),
                'new_batch_total' => $batch->total_files,
            ]);

            return $splitFiles;

        } catch (\Exception $e) {
            Log::error('Exception during PDF splitting', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Count pages in a page range string (e.g., "1" = 1, "2-4" = 3)
     */
    private function countPagesInRange(string $range): int
    {
        if (str_contains($range, '-')) {
            [$start, $end] = explode('-', $range, 2);
            return max(1, intval($end) - intval($start) + 1);
        }
        return 1;
    }

    /**
     * Recursively remove directory and contents
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }
}