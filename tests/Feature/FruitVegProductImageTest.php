<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductThumbnailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The fruit & veg product image route.
 *
 * It had no coverage before cycle 17d, which is awkward because the Shop screens
 * now depend on both of its branches: the full image the office pages ask for, and
 * the `?w=` thumbnail. The first test here is the one that matters most — it pins
 * the no-size response byte-for-byte, so the office pages cannot be changed by
 * accident.
 */
class FruitVegProductImageTest extends TestCase
{
    use RefreshDatabase;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        DB::connection('pos')->getSchemaBuilder()->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('CATEGORY')->nullable();
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->string('TAXCAT')->nullable();
            $table->binary('IMAGE')->nullable();
        });

        // The manage page and currentPhotoProducts() reach past PRODUCTS.
        $pos = DB::connection('pos')->getSchemaBuilder();

        $pos->create('PRODUCTS_CAT', function (Blueprint $table) {
            $table->string('PRODUCT')->primary();
        });

        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        $pos->create('units', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('name')->nullable();
        });

        $pos->create('class', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->integer('classNum')->nullable();
            $table->string('name')->nullable();
        });

        $pos->create('vegDetails', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('product')->nullable();
            $table->integer('countryCode')->nullable();
            $table->string('classId')->nullable();
            $table->string('unitId')->nullable();
        });

        $pos->create('supplier_link', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
        });

        $im = imagecreatetruecolor(300, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 60));
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $this->png = ob_get_clean();

        $this->product('P1', $this->png);
        $this->product('P2', null);
    }

    private function product(string $code, ?string $image): void
    {
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'ID-'.$code,
            'NAME' => 'Product '.$code,
            'CODE' => $code,
            'CATEGORY' => 'SUB1',
            'PRICESELL' => 1.0,
            'IMAGE' => $image,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Fruit & Veg']
            ));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function staff(): User
    {
        return $this->userWith('employee', ['fruit_veg.operate']);
    }

    private function url(string $code, array $query = []): string
    {
        return route('fruit-veg.product-image', array_merge(['code' => $code], $query));
    }

    public function test_without_a_size_it_serves_the_stored_blob_unchanged(): void
    {
        $response = $this->actingAs($this->staff())->get($this->url('P1'));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        // Byte-for-byte: this is what the office pages get and must keep getting.
        $this->assertSame($this->png, $response->getContent());
        $this->assertSame('"'.md5($this->png).'"', $response->headers->get('ETag'));

        // And nothing was encoded or cached on this path.
        $this->assertSame([], Storage::disk('local')->allFiles('fv-thumbs'));
    }

    public function test_a_whitelisted_size_returns_a_square_jpeg(): void
    {
        $response = $this->actingAs($this->staff())->get($this->url('P1', ['w' => 112]));

        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $body = $response->getContent();
        $info = getimagesizefromstring($body);

        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame([112, 112], [$info[0], $info[1]]);
        // Not "smaller than the source": the fixture is a flat-colour PNG that
        // compresses to a few hundred bytes, smaller than any JPEG of it. What
        // holds for every input is that a 112 px JPEG is small in absolute terms,
        // which is the point of the feature. Real-photo sizes are measured in the
        // manual step.
        $this->assertLessThan(20 * 1024, strlen($body));
        $this->assertCount(1, Storage::disk('local')->allFiles('fv-thumbs'));
    }

    public function test_the_second_whitelisted_size_works_too(): void
    {
        $body = $this->actingAs($this->staff())->get($this->url('P1', ['w' => 224]))
            ->assertOk()->getContent();

        $this->assertSame([224, 224], array_slice(getimagesizefromstring($body), 0, 2));
    }

    public function test_an_unknown_size_is_ignored_not_refused(): void
    {
        foreach (['999', '0', '-1', 'abc', ''] as $w) {
            $response = $this->actingAs($this->staff())->get($this->url('P1', ['w' => $w]));

            $response->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->assertSame($this->png, $response->getContent(), "w={$w}");
        }

        $this->assertSame([], Storage::disk('local')->allFiles('fv-thumbs'));
    }

    public function test_a_product_without_an_image_is_unaffected_by_the_size(): void
    {
        foreach ([[], ['w' => 112]] as $query) {
            $response = $this->actingAs($this->staff())->get($this->url('P2', $query));

            $response->assertOk()->assertHeader('Content-Type', 'image/png');
            // The 1x1 transparent PNG, with the long cache header it has always had.
            $this->assertSame(70, strlen($response->getContent()));
            $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        }
    }

    public function test_the_etag_covers_the_bytes_actually_served(): void
    {
        $user = $this->staff();

        $full = $this->actingAs($user)->get($this->url('P1'))->headers->get('ETag');
        $thumb = $this->actingAs($user)->get($this->url('P1', ['w' => 112]))->headers->get('ETag');

        // Different bodies must not share a tag, or a client that has one cached
        // would be told the other is unchanged.
        $this->assertNotSame($full, $thumb);

        $this->actingAs($user)
            ->get($this->url('P1', ['w' => 112]), ['If-None-Match' => $thumb])
            ->assertStatus(304);

        $this->actingAs($user)
            ->get($this->url('P1'), ['If-None-Match' => $full])
            ->assertStatus(304);
    }

    public function test_an_undecodable_blob_falls_back_to_the_full_image(): void
    {
        $this->product('P3', 'this is not an image');

        $response = $this->actingAs($this->staff())->get($this->url('P3', ['w' => 112]));

        // Never a 500: the screen keeps working, just heavier.
        $response->assertOk();
        $this->assertSame('this is not an image', $response->getContent());
        $this->assertSame([], Storage::disk('local')->allFiles('fv-thumbs'));
    }

    public function test_a_barista_is_forbidden(): void
    {
        $user = $this->userWith('barista', ['coffee.kds']);

        $this->actingAs($user)->get($this->url('P1'))->assertForbidden();
        $this->actingAs($user)->get($this->url('P1', ['w' => 112]))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get($this->url('P1'))->assertRedirect('/login');
    }

    // --- cycle 17e: versioned thumbnail URLs -------------------------------

    private function version(?string $blob = null): string
    {
        return substr(md5($blob ?? $this->png), 0, 8);
    }

    public function test_a_matching_version_on_a_thumbnail_caches_for_a_week(): void
    {
        $response = $this->actingAs($this->staff())
            ->get($this->url('P1', ['w' => 112, 'v' => $this->version()]));

        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=604800', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringNotContainsString('must-revalidate', $cacheControl);

        // Still a real 112 px thumbnail, not a different response.
        $this->assertSame([112, 112], array_slice(
            getimagesizefromstring($response->getContent()), 0, 2
        ));
    }

    public function test_a_wrong_version_is_treated_as_absent(): void
    {
        $user = $this->staff();

        foreach (['deadbeef', 'x', str_repeat('0', 8), strtoupper($this->version())] as $bad) {
            $response = $this->actingAs($user)->get($this->url('P1', ['w' => 112, 'v' => $bad]));

            // The current thumbnail, with today's short-lived headers: a stale or
            // guessed link must never pin a picture for a week.
            $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
            $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'), "v={$bad}");
            $this->assertStringNotContainsString('604800', $response->headers->get('Cache-Control'), "v={$bad}");
        }
    }

    public function test_a_version_without_a_size_changes_nothing(): void
    {
        $response = $this->actingAs($this->staff())
            ->get($this->url('P1', ['v' => $this->version()]));

        // Versioning is for thumbnails only. The full-size URL the office pages use
        // has no hash in it, so a long lifetime there could pin a replaced photo.
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($this->png, $response->getContent());
        $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'));
    }

    public function test_a_product_without_an_image_ignores_the_version(): void
    {
        $response = $this->actingAs($this->staff())
            ->get($this->url('P2', ['w' => 112, 'v' => $this->version()]));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(70, strlen($response->getContent()));
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
    }

    public function test_a_revalidated_versioned_thumbnail_keeps_the_long_lifetime(): void
    {
        $user = $this->staff();
        $query = ['w' => 112, 'v' => $this->version()];

        $etag = $this->actingAs($user)->get($this->url('P1', $query))->headers->get('ETag');

        $response = $this->actingAs($user)
            ->get($this->url('P1', $query), ['If-None-Match' => $etag]);

        $response->assertStatus(304);

        // A client that revalidates once must not be dropped back to asking every
        // time — that is the whole cost this cycle removes.
        $this->assertStringContainsString('max-age=604800', $response->headers->get('Cache-Control'));
    }

    public function test_replacing_the_photo_changes_the_version_and_the_old_one_stops_matching(): void
    {
        $user = $this->staff();
        $old = $this->version();

        $im = imagecreatetruecolor(300, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 10, 200));
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $replacement = ob_get_clean();

        $this->assertNotSame($this->png, $replacement);

        // What the browser holds before the photo is swapped.
        $before = $this->actingAs($user)->get($this->url('P1', ['w' => 112, 'v' => $old]));
        $this->assertStringContainsString('max-age=604800', $before->headers->get('Cache-Control'));

        DB::connection('pos')->table('PRODUCTS')->where('CODE', 'P1')->update(['IMAGE' => $replacement]);

        // The link the browser already holds no longer matches, so it is served
        // short-lived rather than being confirmed for a week.
        $stale = $this->actingAs($user)->get($this->url('P1', ['w' => 112, 'v' => $old]));
        $stale->assertOk();
        $this->assertStringContainsString('must-revalidate', $stale->headers->get('Cache-Control'));

        // And the new version does match.
        $fresh = $this->actingAs($user)
            ->get($this->url('P1', ['w' => 112, 'v' => $this->version($replacement)]));
        $fresh->assertOk();
        $this->assertStringContainsString('max-age=604800', $fresh->headers->get('Cache-Control'));

        // A new photo really is a new picture, not the cached old one: the cache key
        // carries the blob's hash, so the bytes and the ETag both change.
        $this->assertNotSame($before->getContent(), $fresh->getContent());
        $this->assertNotSame($before->headers->get('ETag'), $fresh->headers->get('ETag'));

        // And the stale link, though served short-lived, still shows the CURRENT
        // photo rather than the one it was made for.
        $this->assertSame($fresh->getContent(), $stale->getContent());
    }

    // --- cycle 17g: the manage-page prune button ---------------------------

    public function test_a_manager_can_tidy_the_thumbnail_cache(): void
    {
        $manager = $this->userWith('manager', ['fruit_veg.operate', 'fruit_veg.manage']);
        $disk = Storage::disk('local');

        // One thumbnail for P1's current photo, and one orphan.
        $this->actingAs($manager)->get($this->url('P1', ['w' => 112]))->assertOk();
        $this->assertCount(1, $disk->allFiles('fv-thumbs'));
        $disk->put('fv-thumbs/'.str_repeat('a', 12).'-112-'.str_repeat('b', 12).'.jpg', 'orphan');

        $response = $this->actingAs($manager)
            ->from(route('fruit-veg.manage'))
            ->post(route('fruit-veg.thumbnails.prune'));

        $response->assertRedirect(route('fruit-veg.manage'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('deleted 1', session('success'));

        // The orphan is gone and P1's current thumbnail is not.
        $files = $disk->allFiles('fv-thumbs');
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString(str_repeat('a', 12), $files[0]);
    }

    public function test_the_prune_button_needs_manage_not_just_operate(): void
    {
        $this->actingAs($this->staff())
            ->post(route('fruit-veg.thumbnails.prune'))
            ->assertForbidden();
    }

    public function test_a_guest_cannot_prune(): void
    {
        // Its own test: actingAs() persists for the rest of a test method, so a
        // "guest" request after one would still be the signed-in user.
        $this->post(route('fruit-veg.thumbnails.prune'))->assertRedirect('/login');
    }

    public function test_the_manage_page_offers_the_button(): void
    {
        $manager = $this->userWith('manager', ['fruit_veg.operate', 'fruit_veg.manage']);

        $this->actingAs($manager)->get(route('fruit-veg.manage'))
            ->assertOk()
            ->assertSee(route('fruit-veg.thumbnails.prune'), false)
            ->assertSee('Tidy thumbnail cache', false);
    }

    /**
     * Pre-existing (before cycle 17d): the office upload path resizes what it
     * stores to 128 px on the longest side. Kept verbatim.
     */
    public function test_upload_resizes_image_to_maximum_128_pixels(): void
    {
        $user = User::factory()->withRole('admin')->create();

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

        $this->assertLessThanOrEqual(128, $width);
        $this->assertLessThanOrEqual(128, $height);
        $this->assertSame(128, max($width, $height));
    }

    public function test_the_whitelist_is_what_the_service_publishes(): void
    {
        $this->assertSame([112, 224], ProductThumbnailService::SIZES);
    }
}
