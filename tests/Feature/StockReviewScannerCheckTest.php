<?php

namespace Tests\Feature;

use App\Models\StockAdjustment;
use App\Models\StockCheck;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

/**
 * The /stock-review scanner: a scan only looks the product up; the stock
 * check is recorded when a count is confirmed. Fixture product P1
 * (8721325594341) has 12 in stock.
 */
class StockReviewScannerCheckTest extends TestCase
{
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    private const BARCODE = '8721325594341';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->dropProductSearchPosTables();
        Schema::connection('pos')->dropIfExists('stockLastChecked');
        $this->createProductSearchPosTables();
        Schema::connection('pos')->create('stockLastChecked', function (Blueprint $table) {
            $table->string('Barcode')->primary();
            $table->dateTime('Date');
        });
        $this->seedProductSearchFixture();

        $this->user = User::factory()->withRole('admin')->create();
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();
        Schema::connection('pos')->dropIfExists('stockLastChecked');
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scan(array $extra = [])
    {
        return $this->actingAs($this->user)
            ->postJson(route('stock-review.stock-check'), ['barcode' => self::BARCODE] + $extra);
    }

    private function lastCheckedDate(): ?string
    {
        return DB::connection('pos')->table('stockLastChecked')->where('Barcode', self::BARCODE)->value('Date');
    }

    public function test_a_scan_alone_does_not_mark_the_product_checked(): void
    {
        $this->scan()
            ->assertOk()
            ->assertJson(['success' => true, 'checked' => false, 'stock' => 12, 'last_check' => null]);

        $this->assertNull($this->lastCheckedDate());
        $this->assertSame(0, StockCheck::count());
        $this->assertSame(0, StockAdjustment::count());
    }

    public function test_confirming_an_unchanged_count_records_the_check_without_an_adjustment(): void
    {
        $this->scan(['stock_count' => 12])
            ->assertOk()
            ->assertJson(['checked' => true, 'stock_updated' => false, 'message' => 'Stock checked']);

        $this->assertNotNull($this->lastCheckedDate());
        $this->assertSame(0, StockAdjustment::count());

        $check = StockCheck::sole();
        $this->assertEquals(12, $check->previous_stock);
        $this->assertEquals(12, $check->counted_stock);
        $this->assertSame($this->user->id, $check->user_id);
    }

    public function test_a_different_count_updates_the_stock_and_logs_the_adjustment(): void
    {
        $this->scan(['stock_count' => 10])
            ->assertOk()
            ->assertJson(['checked' => true, 'stock_updated' => true, 'stock' => 10]);

        $this->assertEquals(10, DB::connection('pos')->table('STOCKCURRENT')->where('PRODUCT', 'p1')->value('UNITS'));

        $adjustment = StockAdjustment::sole();
        $this->assertEquals(-2, $adjustment->adjustment);
        $this->assertSame('stock_check', $adjustment->source);
        $this->assertEquals(10, StockCheck::sole()->counted_stock);
    }

    public function test_a_count_of_zero_is_a_check(): void
    {
        $this->scan(['stock_count' => 0])->assertJson(['checked' => true, 'stock' => 0]);

        $this->assertEquals(0, StockCheck::sole()->counted_stock);
    }

    public function test_the_next_scan_shows_the_last_check_date_and_quantity(): void
    {
        Carbon::setTestNow('2026-03-12 10:00:00');
        $this->scan(['stock_count' => 9]);

        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->scan()
            ->assertJson([
                'checked' => false,
                'stock' => 9,
                'last_check' => ['date' => '12 Mar 2026', 'ago' => '8 months ago', 'quantity' => 9],
            ]);
    }

    public function test_a_check_made_outside_this_app_shows_the_date_without_a_quantity(): void
    {
        Carbon::setTestNow('2026-03-12 10:00:00');
        $this->scan(['stock_count' => 9]);

        // The old system stamps a later date; we never saw that count.
        DB::connection('pos')->table('stockLastChecked')->where('Barcode', self::BARCODE)->update(['Date' => '2026-06-01 09:00:00']);

        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->scan()->assertJson(['last_check' => ['date' => '1 Jun 2026', 'quantity' => null]]);
    }

    public function test_unknown_barcode_is_not_found(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('stock-review.stock-check'), ['barcode' => 'nope'])
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }
}
