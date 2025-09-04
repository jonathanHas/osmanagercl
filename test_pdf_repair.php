<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PdfRepairService;

$testFile = '/home/jon/Documents/Invoices_in_limbo/Transaction_WS057370.pdf';
$tempTestFile = '/tmp/test_klee_paper.pdf';

// Copy file for testing
if (!copy($testFile, $tempTestFile)) {
    die("Failed to copy test file\n");
}

echo "Testing PDF Repair Service with Klee Paper invoice\n";
echo "================================================\n\n";

$service = new PdfRepairService();

// Check if file needs repair
echo "1. Checking if file needs repair...\n";
$needsRepair = $service->needsRepair($tempTestFile);
echo "   Needs repair: " . ($needsRepair ? "YES" : "NO") . "\n\n";

if ($needsRepair) {
    // Get repair statistics
    echo "2. Getting repair statistics...\n";
    $stats = $service->getRepairStats($tempTestFile);
    echo "   File size: " . $stats['file_size'] . " bytes\n";
    echo "   PDF offset: " . $stats['pdf_offset'] . " bytes\n";
    echo "   Corrupted bytes: " . $stats['corrupted_bytes'] . " bytes\n";
    echo "   Corrupted data preview: " . ($stats['corrupted_data_preview'] ?? 'N/A') . "\n";
    echo "   Supplier detected: " . ($stats['supplier_detected'] ?? 'Unknown') . "\n\n";
    
    // Attempt repair
    echo "3. Attempting to repair PDF...\n";
    $repairSuccess = $service->repair($tempTestFile);
    echo "   Repair result: " . ($repairSuccess ? "SUCCESS" : "FAILED") . "\n\n";
    
    if ($repairSuccess) {
        // Verify repair
        echo "4. Verifying repaired file...\n";
        $stillNeedsRepair = $service->needsRepair($tempTestFile);
        echo "   Still needs repair: " . ($stillNeedsRepair ? "YES (PROBLEM!)" : "NO (Good!)") . "\n";
        
        // Check file type
        $mimeType = mime_content_type($tempTestFile);
        echo "   MIME type: " . $mimeType . "\n";
        
        // Check with file command
        $fileType = shell_exec("file -b " . escapeshellarg($tempTestFile));
        echo "   File type: " . trim($fileType) . "\n";
        
        // Check supplier detection
        $supplier = $service->detectSupplier($tempTestFile);
        echo "   Supplier detected: " . ($supplier ?? 'Unknown') . "\n\n";
        
        echo "5. SUCCESS! The PDF has been repaired and should now upload correctly.\n";
    } else {
        echo "4. FAILED! The repair was unsuccessful.\n";
    }
} else {
    echo "2. File does not need repair - it appears to be a valid PDF.\n";
}

// Clean up
unlink($tempTestFile);

echo "\nTest complete.\n";