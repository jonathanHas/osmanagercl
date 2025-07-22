<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the enum column to add the new value
        DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print', 'requeue_label')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the requeue_label from the enum
        DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print')");
    }
};