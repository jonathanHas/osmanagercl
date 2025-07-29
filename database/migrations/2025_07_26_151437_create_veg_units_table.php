<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('veg_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->string('abbreviation', 10);
            $table->string('plural_name', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
            $table->index('abbreviation');
        });

        // Insert unit data - maintaining compatibility with existing IDs
        $units = [
            ['id' => 1, 'name' => 'kilogram', 'abbreviation' => 'kg', 'plural_name' => 'kilograms', 'sort_order' => 1],
            ['id' => 2, 'name' => 'each', 'abbreviation' => 'ea', 'plural_name' => 'each', 'sort_order' => 2],
            ['id' => 3, 'name' => 'bunch', 'abbreviation' => 'bn', 'plural_name' => 'bunches', 'sort_order' => 3],
            ['id' => 4, 'name' => 'punnet', 'abbreviation' => 'pn', 'plural_name' => 'punnets', 'sort_order' => 4],
            ['id' => 5, 'name' => 'bag', 'abbreviation' => 'bg', 'plural_name' => 'bags', 'sort_order' => 5],
        ];

        $timestamp = now();
        foreach ($units as &$unit) {
            $unit['created_at'] = $timestamp;
            $unit['updated_at'] = $timestamp;
        }

        DB::table('veg_units')->insert($units);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veg_units');
    }
};
