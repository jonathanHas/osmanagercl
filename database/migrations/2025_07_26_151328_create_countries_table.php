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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 3)->nullable(); // ISO code, can be added later
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('name');
            $table->index('sort_order');
        });

        // Insert country data
        $countries = [
            ['name' => 'Ireland', 'sort_order' => 1],
            ['name' => 'France', 'sort_order' => 2],
            ['name' => 'Netherlands', 'sort_order' => 3],
            ['name' => 'Spain', 'sort_order' => 4],
            ['name' => 'Dominican Republic', 'sort_order' => 5],
            ['name' => 'Argentina', 'sort_order' => 6],
            ['name' => 'Portugal', 'sort_order' => 7],
            ['name' => 'Italy', 'sort_order' => 8],
            ['name' => 'Peru', 'sort_order' => 9],
            ['name' => 'Egypt', 'sort_order' => 10],
            ['name' => 'Brazil', 'sort_order' => 11],
            ['name' => 'Costa Rica', 'sort_order' => 12],
            ['name' => 'Burundi', 'sort_order' => 13],
            ['name' => 'Morocco', 'sort_order' => 14],
            ['name' => 'Chile', 'sort_order' => 15],
            ['name' => 'Ivory Coast', 'sort_order' => 16],
            ['name' => 'Colombia', 'sort_order' => 17],
            ['name' => 'South Africa', 'sort_order' => 18],
            ['name' => 'Burkina Faso', 'sort_order' => 19],
            ['name' => 'New Zealand', 'sort_order' => 20],
            ['name' => 'Senegal', 'sort_order' => 21],
            ['name' => 'America', 'sort_order' => 22], // Could be changed to "United States" later
            ['name' => 'Mexico', 'sort_order' => 23],
            ['name' => 'Togo', 'sort_order' => 24],
            ['name' => 'Austria', 'sort_order' => 25],
            ['name' => 'Corsica', 'sort_order' => 26],
            ['name' => 'Belgium', 'sort_order' => 27],
            ['name' => 'Réunion', 'sort_order' => 28],
            ['name' => 'Ethiopia', 'sort_order' => 29],
            ['name' => 'Greece', 'sort_order' => 30],
            ['name' => 'Ecuador', 'sort_order' => 31],
            ['name' => 'Germany', 'sort_order' => 32],
            ['name' => 'Jordan', 'sort_order' => 33],
            ['name' => 'Kenya', 'sort_order' => 34],
            ['name' => 'Poland', 'sort_order' => 35],
            ['name' => 'Madagascar', 'sort_order' => 36],
            ['name' => 'Turkey', 'sort_order' => 37],
        ];

        $timestamp = now();
        foreach ($countries as &$country) {
            $country['created_at'] = $timestamp;
            $country['updated_at'] = $timestamp;
        }

        DB::table('countries')->insert($countries);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
