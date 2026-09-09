<?php

namespace Tests\Feature;

use App\Models\ProductTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\TestCase;

/**
 * save() used to omit auto_print entirely, so a new translation fell back to the column
 * default (true) — silently re-enabling delivery auto-printing for a product the user
 * had switched off.
 */
class LabelTranslationSaveTest extends TestCase
{
    use AliasesMysqlConnection, RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required.');
        }

        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();

        $this->actingAs(User::factory()->create());
    }

    private function save(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('labels.translate.save'), array_merge([
            'label_data' => ['product_name' => 'Olives'],
            'label_size' => 'large',
            'zpl_content' => '^XA^FDOlives^FS^XZ',
            'product_code' => '111',
        ], $overrides));
    }

    public function test_a_first_translation_defaults_to_auto_print_on(): void
    {
        $this->save()->assertOk();

        $this->assertTrue(ProductTranslation::latestForProduct('111')->auto_print);
    }

    public function test_a_new_translation_inherits_a_disabled_auto_print(): void
    {
        ProductTranslation::create([
            'product_code' => '111',
            'label_data' => ['product_name' => 'Olives'],
            'label_size' => 'large',
            'zpl_content' => '^XA^FDold^FS^XZ',
            'auto_print' => false,
        ]);

        $this->save()->assertOk();

        $this->assertFalse(ProductTranslation::latestForProduct('111')->auto_print);
        $this->assertSame(2, ProductTranslation::where('product_code', '111')->count());
    }

    public function test_an_explicit_auto_print_value_wins(): void
    {
        ProductTranslation::create([
            'product_code' => '111',
            'label_data' => ['product_name' => 'Olives'],
            'label_size' => 'large',
            'zpl_content' => '^XA^FDold^FS^XZ',
            'auto_print' => false,
        ]);

        $this->save(['auto_print' => true])->assertOk();

        $this->assertTrue(ProductTranslation::latestForProduct('111')->auto_print);
    }

    public function test_updating_an_existing_translation_leaves_auto_print_untouched(): void
    {
        $existing = ProductTranslation::create([
            'product_code' => '111',
            'label_data' => ['product_name' => 'Olives'],
            'label_size' => 'large',
            'zpl_content' => '^XA^FDold^FS^XZ',
            'auto_print' => false,
        ]);

        $this->save(['translation_id' => $existing->id, 'zpl_content' => '^XA^FDnew^FS^XZ'])
            ->assertOk();

        $existing->refresh();

        $this->assertFalse($existing->auto_print);
        $this->assertSame('^XA^FDnew^FS^XZ', $existing->zpl_content);
    }
}
