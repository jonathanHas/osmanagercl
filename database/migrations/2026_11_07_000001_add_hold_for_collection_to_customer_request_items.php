<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests cycle 2: not every request is held for the customer.
 *
 * A "general" request (a customer asking us to get something in) goes on the
 * shelf when it arrives and is marked done, with nothing to put aside and
 * nobody to wait for. `hold_for_collection` says which kind a line is; it
 * defaults to true so every existing line keeps the put-aside / collected
 * lifecycle it was taken with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_request_items', function (Blueprint $table) {
            $table->boolean('hold_for_collection')->default(true)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('customer_request_items', function (Blueprint $table) {
            $table->dropColumn('hold_for_collection');
        });
    }
};
