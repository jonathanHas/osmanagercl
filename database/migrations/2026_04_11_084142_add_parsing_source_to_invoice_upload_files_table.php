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
            $table->string('parsing_source', 20)->default('python')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_upload_files', function (Blueprint $table) {
            $table->dropColumn('parsing_source');
        });
    }
};
