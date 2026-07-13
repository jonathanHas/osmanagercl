<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->boolean('auto_print')->default(true)->after('zpl_content')
                ->comment('Include this translated label in delivery auto-printing');
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->dropColumn('auto_print');
        });
    }
};
