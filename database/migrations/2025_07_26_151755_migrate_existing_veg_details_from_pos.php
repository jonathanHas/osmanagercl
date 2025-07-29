<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run if POS database is configured
        if (! config('database.connections.pos')) {
            Log::info('POS database not configured, skipping veg_details migration');

            return;
        }

        try {
            // Get existing data from POS database
            $posVegDetails = DB::connection('pos')->table('vegDetails')->get();

            if ($posVegDetails->isEmpty()) {
                Log::info('No vegDetails found in POS database');

                return;
            }

            // Create a mapping of POS country IDs to new country IDs
            $countryMapping = [];
            $posCountries = DB::connection('pos')->table('countries')->get();

            foreach ($posCountries as $posCountry) {
                $localCountry = DB::table('countries')
                    ->where('name', $posCountry->country)
                    ->first();

                if ($localCountry) {
                    $countryMapping[$posCountry->ID] = $localCountry->id;
                }
            }

            // Migrate veg_details data
            $timestamp = now();
            foreach ($posVegDetails as $detail) {
                // Skip if no country mapping found
                $countryId = $countryMapping[$detail->countryCode] ?? null;

                DB::table('veg_details')->updateOrInsert(
                    ['product_code' => $detail->product],
                    [
                        'country_id' => $countryId,
                        'class_id' => $detail->classId, // Assuming IDs match
                        'unit_id' => $detail->unitId,   // Assuming IDs match
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                );
            }

            Log::info('Successfully migrated '.$posVegDetails->count().' veg_details records');

        } catch (\Exception $e) {
            Log::error('Failed to migrate veg_details from POS: '.$e->getMessage());
            // Don't throw exception - this migration is optional
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data migration, so we don't reverse it
        // The data should remain in the local database
    }
};
