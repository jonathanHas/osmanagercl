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
        Schema::table('invoice_attachments', function (Blueprint $table) {
            $table->string('converted_pdf_path')->nullable()->after('file_path');
            $table->timestamp('converted_at')->nullable()->after('converted_pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_attachments', function (Blueprint $table) {
            $table->dropColumn(['converted_pdf_path', 'converted_at']);
        });
    }
};
