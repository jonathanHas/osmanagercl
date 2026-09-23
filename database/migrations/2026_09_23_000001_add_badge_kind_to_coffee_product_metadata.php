<?php

use App\Models\CoffeeProductMetadata;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which badge kind an existing option row should get, keyed by the first
     * pattern its product_name matches. Order matters: "Espresso Decaf Extra
     * Shot" must land on decaf, not shot. `\bice\b` is a word-boundary match
     * so "Service" does not become an ice cube.
     */
    private const BACKFILL = [
        'decaf' => '/decaf/i',
        'shot' => '/extra\s+shot/i',
        'oat' => '/oat/i',
        'almond' => '/almond/i',
        'soy' => '/soya?/i',
        'coconut' => '/coconut/i',
        'caramel' => '/caramel/i',
        'vanilla' => '/vanilla/i',
        'hazelnut' => '/hazelnut/i',
        'mocha' => '/mocha/i',
        'ice' => '/\bice\b/i',
    ];

    public function up(): void
    {
        Schema::table('coffee_product_metadata', function (Blueprint $table) {
            $table->string('badge_kind', 20)->nullable()->after('group_name');
        });

        // Backfill in PHP rather than SQL: the primary connection is MySQL in
        // development and SQLite :memory: under PHPUnit.
        CoffeeProductMetadata::query()
            ->where('type', 'option')
            ->whereNull('badge_kind')
            ->get(['id', 'product_name'])
            ->each(function (CoffeeProductMetadata $row) {
                foreach (self::BACKFILL as $kind => $pattern) {
                    if (preg_match($pattern, (string) $row->product_name)) {
                        $row->forceFill(['badge_kind' => $kind])->save();

                        return;
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('coffee_product_metadata', function (Blueprint $table) {
            $table->dropColumn('badge_kind');
        });
    }
};
