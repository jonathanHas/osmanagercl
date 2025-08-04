<?php

use App\Models\LabelTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if template already exists (might have been created by seeder)
        if (! LabelTemplate::where('name', 'Grid 4x9 (47x31mm)')->exists()) {
            // This migration is now just a safety check
            // Actual template data should come from LabelTemplateSeeder
            \Log::info('Grid 4x9 template not found, will be created by seeder');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LabelTemplate::where('name', 'Grid 4x9 (47x31mm)')->delete();
    }
};
