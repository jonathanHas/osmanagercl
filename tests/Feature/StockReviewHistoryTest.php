<?php

namespace Tests\Feature;

use App\Models\StockZeroAudit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The set-to-zero history merges the old system's catSetZero rows with this
 * app's stock_zero_audits. A set-to-zero made here writes both, so the mirror
 * catSetZero row must not be listed as a second "Legacy" entry.
 */
class StockReviewHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $pos = Schema::connection('pos');
        $pos->dropIfExists('catSetZero');
        $pos->dropIfExists('CATEGORIES');
        $pos->create('catSetZero', function ($table) {
            $table->string('ID')->primary();
            $table->string('catID');
            $table->dateTime('dateUpdated');
        });
        $pos->create('CATEGORIES', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME');
        });

        DB::connection('pos')->table('CATEGORIES')->insert([
            ['ID' => '001', 'NAME' => 'Frozen'],
            ['ID' => '002', 'NAME' => 'Refrigerated'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('pos')->dropIfExists('catSetZero');
        Schema::connection('pos')->dropIfExists('CATEGORIES');

        parent::tearDown();
    }

    private function audit(string $categoryId, string $name, string $at): StockZeroAudit
    {
        $audit = new StockZeroAudit([
            'category_id' => $categoryId,
            'category_name' => $name,
            'reference_date' => substr($at, 0, 10),
            'products_zeroed' => 3,
            'total_stock_value_zeroed' => 12.50,
            'product_details' => [],
            'user_id' => User::factory()->create()->id,
        ]);
        $audit->created_at = $at;
        $audit->updated_at = $at;
        $audit->save();

        return $audit;
    }

    private function legacy(string $id, string $categoryId, string $at): void
    {
        DB::connection('pos')->table('catSetZero')->insert([
            'ID' => $id,
            'catID' => $categoryId,
            'dateUpdated' => $at,
        ]);
    }

    public function test_a_set_to_zero_made_in_the_app_is_listed_once(): void
    {
        $this->audit('001', 'Frozen', '2026-10-06 17:55:03');
        $this->legacy('mirror', '001', '2026-10-06 17:55:03');

        $rows = $this->actingAs(User::factory()->withRole('admin')->create())
            ->getJson(route('stock-review.history'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $rows);
        $this->assertSame('new', $rows[0]['source']);
        $this->assertSame('Frozen', $rows[0]['category_name']);
    }

    public function test_mirror_row_written_a_second_apart_is_still_dropped(): void
    {
        $this->audit('001', 'Frozen', '2026-10-06 17:55:04');
        $this->legacy('mirror', '001', '2026-10-06 17:55:03');

        $rows = $this->actingAs(User::factory()->withRole('admin')->create())
            ->getJson(route('stock-review.history'))
            ->json();

        $this->assertCount(1, $rows);
    }

    public function test_old_system_rows_and_other_categories_are_kept(): void
    {
        $this->audit('001', 'Frozen', '2026-10-06 17:55:03');
        $this->legacy('mirror', '001', '2026-10-06 17:55:03');
        // Same category, earlier date: made on the old system.
        $this->legacy('old-same-cat', '001', '2025-03-01 10:00:00');
        // Same moment, different category: not a mirror of the Frozen audit.
        $this->legacy('old-other-cat', '002', '2026-10-06 17:55:03');

        $rows = collect($this->actingAs(User::factory()->withRole('admin')->create())
            ->getJson(route('stock-review.history'))
            ->json());

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(
            ['old-same-cat', 'old-other-cat'],
            $rows->where('source', 'old')->pluck('id')->all()
        );
    }
}
