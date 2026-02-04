<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_upload_files', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['bulk_upload_id']);

            // Make the column nullable
            $table->unsignedBigInteger('bulk_upload_id')->nullable()->change();

            // Re-add the foreign key constraint with nullable support
            $table->foreign('bulk_upload_id')
                ->references('id')
                ->on('invoice_bulk_uploads')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_upload_files', function (Blueprint $table) {
            $table->dropForeign(['bulk_upload_id']);
            $table->unsignedBigInteger('bulk_upload_id')->nullable(false)->change();
            $table->foreign('bulk_upload_id')
                ->references('id')
                ->on('invoice_bulk_uploads')
                ->onDelete('cascade');
        });
    }
};
