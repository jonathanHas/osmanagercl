<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests cycle 1: a request line can be taken by the case or by the unit.
 *
 * `quantity` keeps meaning "how many of the chosen unit"; "2 cases of 6" is
 * quantity 2, unit case, case_units 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_request_items', function (Blueprint $table) {
            // 'unit' | 'case'
            $table->string('unit', 8)->default('unit')->after('quantity');
            // Supplier case size snapshotted when the line was taken by the case.
            $table->unsignedSmallInteger('case_units')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('customer_request_items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'case_units']);
        });
    }
};
