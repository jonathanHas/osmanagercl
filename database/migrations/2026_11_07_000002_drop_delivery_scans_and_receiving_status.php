<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The /deliveries scan → summary → complete path never wrote stock (its
 * stock update was a logging stub) and was last used in August 2025; stock is
 * received through Receiving (/delivery-legacy) and the Shop screens. This
 * removes its scan table and puts the deliveries it had moved to `receiving`
 * back to `draft`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('delivery_scans');

        DB::table('deliveries')->where('status', 'receiving')->update(['status' => 'draft']);
    }

    public function down(): void
    {
        Schema::create('delivery_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_item_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('barcode');
            $table->integer('quantity')->default(1);
            $table->boolean('matched')->default(false);
            $table->string('scanned_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['delivery_id', 'barcode']);
            $table->index('created_at');
        });
    }
};
