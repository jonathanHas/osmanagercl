<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FruitVegProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('pos');

        $schema = DB::connection('pos')->getSchemaBuilder();

        $schema->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('CATEGORY')->nullable();
            $table->binary('IMAGE')->nullable();
        });
    }

    public function test_upload_resizes_image_to_maximum_64_pixels(): void
    {
        $user = User::factory()->create();

        $product = Product::query()->create([
            'ID' => 'PRODUCT-2308',
            'NAME' => 'Demo Fruit & Veg Item',
            'CODE' => '2308',
            'CATEGORY' => 'SUB1',
        ]);

        $this->actingAs($user);

        $wideImage = UploadedFile::fake()->image('wide.jpg', 256, 128);

        $response = $this->post(route('fruit-veg.product.update-image', $product->CODE), [
            'image' => $wideImage,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $storedImage = DB::connection('pos')->table('PRODUCTS')
            ->where('ID', $product->ID)
            ->value('IMAGE');

        $this->assertNotEmpty($storedImage);

        $details = getimagesizefromstring($storedImage);
        $this->assertNotFalse($details, 'Stored blob is not a valid image');

        [$width, $height] = $details;

        $this->assertLessThanOrEqual(64, $width);
        $this->assertLessThanOrEqual(64, $height);
        $this->assertSame(64, max($width, $height));
    }
}
