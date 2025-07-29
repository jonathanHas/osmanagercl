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
        Schema::create('veg_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 10)->unique();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        // Insert class data - maintaining compatibility with existing IDs where possible
        $classes = [
            ['id' => 1, 'name' => 'Extra', 'description' => 'Superior quality', 'sort_order' => 1],
            ['id' => 2, 'name' => 'I', 'description' => 'Good quality', 'sort_order' => 2],
            ['id' => 3, 'name' => 'II', 'description' => 'Standard quality', 'sort_order' => 3],
            ['id' => 4, 'name' => 'III', 'description' => 'Basic quality', 'sort_order' => 4],
        ];

        $timestamp = now();
        foreach ($classes as &$class) {
            $class['created_at'] = $timestamp;
            $class['updated_at'] = $timestamp;
        }

        DB::table('veg_classes')->insert($classes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veg_classes');
    }
};
