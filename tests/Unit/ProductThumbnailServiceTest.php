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

    public function test_a_replaced_photo_gets_a_new_cache_key_and_the_old_file_goes(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;

        $service->jpeg('T1', $this->png(300, 200), 112);
        $first = $disk->allFiles('fv-thumbs')[0];

        $service->jpeg('T1', $this->png(400, 100), 112);
        $files = $disk->allFiles('fv-thumbs');

        // A different blob is a different key, so the old thumbnail can never be
        // served — and cycle 17f removes it rather than leaving an orphan behind.
        $this->assertCount(1, $files);
        $this->assertNotSame($first, $files[0]);
        $this->assertFalse($disk->exists($first));
    }

    public function test_replacing_a_photo_leaves_other_sizes_and_other_products_alone(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;

        $blobA = $this->png(300, 200);
        $service->jpeg('T1', $blobA, 112);
        $service->jpeg('T1', $blobA, 224);
        $service->jpeg('T2', $blobA, 112);

        $this->assertCount(3, $disk->allFiles('fv-thumbs'));
        $other = $disk->allFiles('fv-thumbs');

        // Replace T1's photo: only T1's 112 file is swapped.
        $service->jpeg('T1', $this->png(400, 100), 112);
        $after = $disk->allFiles('fv-thumbs');

        $this->assertCount(3, $after);
        $this->assertCount(2, array_intersect($other, $after), 'T1@224 and T2@112 should be untouched');

        // Both of T1's sizes are still available, and both are the right shape.
        $this->assertSame([112, 112], array_slice(getimagesizefromstring($service->jpeg('T1', $this->png(400, 100), 112)), 0, 2));
        $this->assertSame([224, 224], array_slice(getimagesizefromstring($service->jpeg('T1', $blobA, 224)), 0, 2));
    }

    // --- cycle 17g: prune -------------------------------------------------

    /**
     * A stand-in for a Product: prune() reads only CODE and IMAGE, and this keeps
     * the unit test free of the POS connection.
     */
    private function productStub(string $code, ?string $image): object
    {
        return new class($code, $image)
        {
            public function __construct(public string $CODE, public ?string $IMAGE) {}
        };
    }

    public function test_prune_removes_orphans_and_keeps_current_thumbnails(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;

        $current = $this->png(300, 200);
        $old = $this->png(400, 100);

        // A's current photo at both sizes, A's previous photo, and a file for a
        // product that no longer has one.
        $service->jpeg('A', $current, 112);
        $service->jpeg('A', $current, 224);
        $keep = $disk->allFiles('fv-thumbs');
        $this->assertCount(2, $keep);

        $disk->put('fv-thumbs/'.substr(md5('A'), 0, 12).'-112-'.substr(md5($old), 0, 12).'.jpg', 'stale');
        $disk->put('fv-thumbs/'.substr(md5('Z'), 0, 12).'-112-'.substr(md5($current), 0, 12).'.jpg', 'gone');
        $this->assertCount(4, $disk->allFiles('fv-thumbs'));

        $result = $service->prune([$this->productStub('A', $current)]);

        $this->assertSame(2, $result['kept']);
        $this->assertSame(2, $result['deleted']);
        $this->assertSame([], $result['failed']);
        $this->assertEqualsCanonicalizing($keep, $disk->allFiles('fv-thumbs'));
    }

    public function test_prune_skips_a_product_whose_photo_has_gone(): void
    {
        $disk = Storage::fake('local');
        $service = new ProductThumbnailService;

        $service->jpeg('A', $this->png(), 112);

        // The product is still listed but its photo was cleared, so its cached
        // thumbnail is an orphan like any other.
        $result = $service->prune([$this->productStub('A', null)]);

        $this->assertSame(['kept' => 0, 'deleted' => 1, 'failed' => []], $result);
        $this->assertSame([], $disk->allFiles('fv-thumbs'));
    }

    public function test_prune_on_an_empty_or_missing_folder_does_nothing(): void
    {
        Storage::fake('local');

        $this->assertSame(
            ['kept' => 0, 'deleted' => 0, 'failed' => []],
            (new ProductThumbnailService)->prune([])
        );
    }

    public function test_prune_reports_a_file_it_cannot_delete(): void
    {
        $real = Storage::fake('local');
        $service = new ProductThumbnailService;
        $service->jpeg('A', $this->png(), 112);
        $orphan = $real->allFiles('fv-thumbs')[0];

        // The folder is created by the web server, so a shell run as another user
        // cannot delete from it. Report it; never throw inside a cron job.
        $disk = \Mockery::mock($real);
        $disk->shouldReceive('files')->andReturn([$orphan]);
        $disk->shouldReceive('delete')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $result = $service->prune([]);

        $this->assertSame(0, $result['deleted']);
        $this->assertSame([$orphan], $result['failed']);
    }

    public function test_prune_survives_a_disk_that_throws_on_delete(): void
    {
        $real = Storage::fake('local');
        $service = new ProductThumbnailService;
        $service->jpeg('A', $this->png(), 112);
        $orphan = $real->allFiles('fv-thumbs')[0];

        $disk = \Mockery::mock($real);
        $disk->shouldReceive('files')->andReturn([$orphan]);
        $disk->shouldReceive('delete')->andThrow(new \RuntimeException('permission denied'));
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $result = $service->prune([]);

        $this->assertSame([$orphan], $result['failed']);
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
