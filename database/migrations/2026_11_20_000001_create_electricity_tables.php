<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Electricity section (docs/features/electricity.md): supply points keyed
     * by MPRN, their meters, and each bill in a provider-neutral shape (charge
     * lines in fixed categories, meter reading rows with read types).
     */
    public function up(): void
    {
        Schema::create('electricity_supply_points', function (Blueprint $table) {
            $table->id();
            $table->string('mprn', 20)->unique();
            $table->string('name')->nullable();
            $table->unsignedInteger('mic_kva')->nullable();
            $table->string('dg_group', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('electricity_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electricity_supply_point_id')->constrained()->cascadeOnDelete();
            $table->string('serial', 40);
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['electricity_supply_point_id', 'serial'], 'electricity_meters_point_serial_unique');
        });

        Schema::create('electricity_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electricity_supply_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('provider', 60);
            $table->string('account_number', 40)->nullable();
            $table->string('bill_number', 60)->nullable();
            $table->string('document_type', 20)->default('invoice'); // invoice | credit_note
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('tariff')->nullable();
            $table->decimal('net_total', 12, 2)->nullable();
            $table->decimal('vat_total', 12, 2)->nullable();
            $table->decimal('export_credit_total', 12, 2)->default(0);
            $table->decimal('gross_total', 12, 2)->nullable();
            $table->decimal('import_kwh', 12, 3)->default(0);
            $table->decimal('export_kwh', 12, 3)->default(0);
            $table->string('status', 20)->default('needs_review'); // needs_review | confirmed
            $table->string('source', 20)->default('parser'); // parser | ai | manual
            $table->json('checks')->nullable();
            $table->json('warnings')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable(); // on the private disk; null when the PDF is the invoice attachment
            $table->string('file_hash', 64)->nullable()->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // A provider may credit a bill under the bill's own number.
            $table->unique(['provider', 'bill_number', 'document_type'], 'electricity_bills_provider_number_unique');
            $table->index(['status', 'issue_date']);
        });

        Schema::create('electricity_bill_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electricity_bill_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('band', 10)->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('rate', 14, 6)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('net_amount', 12, 2);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('electricity_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electricity_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('electricity_meter_id')->constrained()->cascadeOnDelete();
            $table->string('band', 10)->default('24h');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('previous_reading', 14, 2);
            $table->string('previous_type', 10);
            $table->decimal('present_reading', 14, 2);
            $table->string('present_type', 10);
            $table->decimal('multiplier', 8, 3)->default(1);
            $table->decimal('kwh', 12, 3);
            $table->boolean('is_reversal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electricity_meter_readings');
        Schema::dropIfExists('electricity_bill_charges');
        Schema::dropIfExists('electricity_bills');
        Schema::dropIfExists('electricity_meters');
        Schema::dropIfExists('electricity_supply_points');
    }
};
