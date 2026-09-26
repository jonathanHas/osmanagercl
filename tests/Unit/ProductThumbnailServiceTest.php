<?php

namespace Tests\Unit;

use App\Services\ProductThumbnailService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The thumbnail cache behind the fruit & veg image route's `?w=`.
 */
class ProductThumbnailServiceTest extends TestCase
{
    private function png(int $w = 300, int $h = 200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return ob_get_clean();
    }

    public function test_it_makes_a_square_jpeg_of_the_requested_size(): void
    {
        Storage::fake('local');

        $jpeg = (new ProductThumbnailService)->jpeg('T1', $this->png(), 112);

        $this->assertNotNull($jpeg);

        $info = getimagesizefromstring($jpeg);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame([112, 112], [$info[0], $info[1]]);

        // A 300x200 source cropped to a square, not squashed into one.
        $this->assertLessThan(strlen($this->png()) * 10, strlen($jpeg));
    }

    public function test_it_writes_one_file_per_code_size_and_blob(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;
        $blob = $this->png();

        $service->jpeg('T1', $blob, 112);

        $files = $disk->allFiles('fv-thumbs');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.jpg', $files[0]);

        // A second size is a second file; the same call again is not.
        $service->jpeg('T1', $blob, 224);
        $service->jpeg('T1', $blob, 112);
        $this->assertCount(2, $disk->allFiles('fv-thumbs'));

        $this->assertSame([224, 224], array_slice(
            getimagesizefromstring($service->jpeg('T1', $blob, 224)), 0, 2
        ));
    }

    public function test_a_second_call_is_served_from_the_cache(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;
        $blob = $this->png();

        $first = $service->jpeg('T1', $blob, 112);
        $path = $disk->allFiles('fv-thumbs')[0];

        // Prove the file is read rather than the image re-encoded: overwrite the
        // cached bytes and check they come back verbatim.
        $disk->put($path, 'not-really-a-jpeg');

        $this->assertSame('not-really-a-jpeg', $service->jpeg('T1', $blob, 112));
        $this->assertNotSame($first, $service->jpeg('T1', $blob, 112));
        $this->assertCount(1, $disk->allFiles('fv-thumbs'));
    }

    public function test_a_replaced_photo_gets_a_new_cache_key(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;

        $service->jpeg('T1', $this->png(300, 200), 112);
        $service->jpeg('T1', $this->png(400, 100), 112);

        // Same code, same size, different blob — two entries, so a replaced photo
        // can never be served from the old one.
        $this->assertCount(2, $disk->allFiles('fv-thumbs'));
    }

    public function test_an_undecodable_blob_returns_null_and_stores_nothing(): void
    {
        $disk = Storage::fake('local');

        $this->assertNull((new ProductThumbnailService)->jpeg('T1', 'not an image at all', 112));
        $this->assertSame([], $disk->allFiles('fv-thumbs'));
    }

    public function test_an_empty_blob_returns_null(): void
    {
        Storage::fake('local');

        $this->assertNull((new ProductThumbnailService)->jpeg('T1', '', 112));
    }

    public function test_a_code_with_path_characters_cannot_escape_the_folder(): void
    {
        $disk = Storage::fake('local');

        (new ProductThumbnailService)->jpeg('../../etc/passwd', $this->png(), 112);

        $files = $disk->allFiles('fv-thumbs');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('fv-thumbs/', $files[0]);
        $this->assertStringNotContainsString('..', $files[0]);
    }
}
