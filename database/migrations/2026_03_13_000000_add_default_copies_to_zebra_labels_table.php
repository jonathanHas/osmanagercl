<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zebra_labels', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_copies')->nullable()->after('label_height_mm');
        });
    }

    public function down(): void
    {
        Schema::table('zebra_labels', function (Blueprint $table) {
            $table->dropColumn('default_copies');
        });
    }
};
