<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger of translated Zebra labels actually sent to the printer for a delivery.
     *
     * Without this, DeliveryLegacyController::printTranslations() re-derived its print
     * set from the cumulative scanned quantities on every press, so each press reprinted
     * every product in the delivery again. Rows here are written *before* the job is sent
     * to CUPS, so a lost/timed-out response can never cause a duplicate print.
     *
     * delivery_id is the POS deliveriesScan.ID (a uuid on the `pos` connection), so it
     * deliberately carries no foreign key.
     */
    public function up(): void
    {
        Schema::create('delivery_label_prints', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id', 64)->index();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->string('barcode', 64);
            $table->foreignId('product_translation_id')->nullable()
                ->constrained('product_translations')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->comment('Labels sent for this barcode in this batch');
            $table->uuid('batch_uuid')->index()->comment('One modal press = one lp job = one batch');
            $table->string('idempotency_key', 64)->index()->comment('Client-generated per submit attempt');
            $table->string('cups_job_id', 64)->nullable()->comment('Parsed from "request id is ..."');
            $table->text('lp_output')->nullable();
            $table->timestamp('printed_at')->useCurrent()->index();
            $table->timestamp('failed_at')->nullable()->comment('Set when lp definitively refused the job');
            $table->boolean('forced')->default(false)->comment('Printed via the "reprint anyway" override');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Guards a double-click race: the same submit attempt can only land once per barcode.
            $table->unique(['idempotency_key', 'barcode']);
            $table->index(['delivery_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_label_prints');
    }
};
