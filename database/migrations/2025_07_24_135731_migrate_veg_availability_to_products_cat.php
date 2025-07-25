<?php

use App\Services\TillVisibilityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $service = app(TillVisibilityService::class);
        
        try {
            // Perform the migration
            $result = $service->migrateFromVegAvailability();
            
            Log::info('Successfully migrated veg_availability to PRODUCTS_CAT', $result);
            
            echo "Migration completed successfully:\n";
            echo "- Migrated: {$result['migrated']} products\n";
            echo "- Errors: {$result['errors']}\n";
            echo "- Total: {$result['total']} products\n";
            
        } catch (\Exception $e) {
            Log::error('Failed to migrate veg_availability to PRODUCTS_CAT', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is designed to be non-destructive
        // The old veg_availability table is preserved
        // To reverse, you would need to manually remove entries from PRODUCTS_CAT
        
        Log::warning('Reverse migration not implemented for veg_availability to PRODUCTS_CAT migration. Manual cleanup required.');
    }
};