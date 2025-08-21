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
            $table->integer('page_count')->nullable()->after('file_hash');
            $table->boolean('is_split')->default(false)->after('page_count');
            $table->foreignId('parent_file_id')->nullable()->constrained('invoice_upload_files')->onDelete('cascade')->after('is_split');
            $table->string('page_range')->nullable()->after('parent_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_upload_files', function (Blueprint $table) {
            $table->dropForeign(['parent_file_id']);
            $table->dropColumn(['page_count', 'is_split', 'parent_file_id', 'page_range']);
        });
    }
};
