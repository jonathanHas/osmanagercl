<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * vouchers:retire-fixed-products (vouchers cycle 4): the three old fixed voucher
 * products come off the till and can be put back.
 */
class RetireFixedVoucherProductsTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    private const FIXED = ['6012' => 'Voucher 50 Euro', '6013' => 'Voucher 10 Euro', '6014' => 'Voucher 20 Euro'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createVoucherPosTables();

        $pos = DB::connection('pos');
        foreach (self::FIXED as $code => $name) {
            $pos->table('PRODUCTS')->insert([
                'ID' => 'fixed-'.$code, 'NAME' => $name, 'CODE' => $code, 'REFERENCE' => $code,
                'CATEGORY' => '033', 'TAXCAT' => '000', 'PRICESELL' => (int) filter_var($name, FILTER_SANITIZE_NUMBER_INT),
            ]);
            $pos->table('PRODUCTS_CAT')->insert(['PRODUCT' => 'fixed-'.$code, 'CATORDER' => null]);
        }

        // An unrelated product with a button, which must never change.
        $pos->table('PRODUCTS')->insert([
            'ID' => 'other', 'NAME' => 'Oat Milk 1L', 'CODE' => '5000000000001', 'REFERENCE' => '5000000000001',
            'CATEGORY' => '010', 'TAXCAT' => '001', 'PRICESELL' => 2,
        ]);
        $pos->table('PRODUCTS_CAT')->insert(['PRODUCT' => 'other', 'CATORDER' => 3]);
    }

    private function product(string $id): object
    {
        return DB::connection('pos')->table('PRODUCTS')->where('ID', $id)->first();
    }

    private function hasButton(string $id): bool
    {
        return DB::connection('pos')->table('PRODUCTS_CAT')->where('PRODUCT', $id)->exists();
    }

    private function assertUntouched(): void
    {
        foreach (self::FIXED as $code => $name) {
            $p = $this->product('fixed-'.$code);
            $this->assertSame($name, $p->NAME);
            // Array keys like '6012' become integers in PHP.
            $this->assertSame((string) $code, $p->CODE);
            $this->assertSame((string) $code, $p->REFERENCE);
            $this->assertTrue($this->hasButton('fixed-'.$code));
        }
        $this->assertOtherUntouched();
    }

    private function assertOtherUntouched(): void
    {
        $other = $this->product('other');
        $this->assertSame('Oat Milk 1L', $other->NAME);
        $this->assertSame('5000000000001', $other->CODE);
        $this->assertSame(3, (int) DB::connection('pos')->table('PRODUCTS_CAT')->where('PRODUCT', 'other')->value('CATORDER'));
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->artisan('vouchers:retire-fixed-products --dry-run')
            ->expectsOutputToContain('would retire → RET6012')
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertExitCode(0);

        $this->assertUntouched();
    }

    public function test_retire_removes_the_buttons_and_renames_and_recodes(): void
    {
        $this->artisan('vouchers:retire-fixed-products')
            ->expectsOutputToContain('restart uniCenta on each till')
            ->assertExitCode(0);

        foreach (self::FIXED as $code => $name) {
            $p = $this->product('fixed-'.$code);
            $this->assertSame($name.' (retired)', $p->NAME);
            $this->assertSame('RET'.$code, $p->CODE);
            $this->assertSame('RET'.$code, $p->REFERENCE);
            $this->assertFalse($this->hasButton('fixed-'.$code));
        }
        $this->assertOtherUntouched();
        $this->assertTrue($this->hasButton('other'));
    }

    public function test_a_second_run_reports_already_retired(): void
    {
        $this->artisan('vouchers:retire-fixed-products')->assertExitCode(0);

        $this->artisan('vouchers:retire-fixed-products')
            ->expectsOutputToContain('already retired')
            ->assertExitCode(0);

        $this->assertSame('Voucher 50 Euro (retired)', $this->product('fixed-6012')->NAME);
    }

    public function test_restore_puts_everything_back(): void
    {
        $this->artisan('vouchers:retire-fixed-products')->assertExitCode(0);

        $this->artisan('vouchers:retire-fixed-products --restore')
            ->expectsOutputToContain('restored')
            ->assertExitCode(0);

        $this->assertUntouched();
    }

    public function test_a_missing_product_is_reported_not_found(): void
    {
        DB::connection('pos')->table('PRODUCTS_CAT')->where('PRODUCT', 'fixed-6013')->delete();
        DB::connection('pos')->table('PRODUCTS')->where('ID', 'fixed-6013')->delete();

        $this->artisan('vouchers:retire-fixed-products')
            ->expectsOutputToContain('not found')
            ->assertExitCode(0);

        $this->assertSame('RET6012', $this->product('fixed-6012')->CODE);
    }

    public function test_with_the_switch_off_the_command_fails_and_changes_nothing(): void
    {
        Config::set('vouchers.admin_tools', false);

        $this->artisan('vouchers:retire-fixed-products')
            ->expectsOutputToContain('switched off')
            ->assertExitCode(1);

        $this->assertUntouched();
    }
}
