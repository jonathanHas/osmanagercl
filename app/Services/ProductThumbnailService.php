<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Small, cached JPEGs of POS product photos.
 *
 * The photos in `PRODUCTS.IMAGE` are full-size packshots — up to ~145 kB — and the
 * Shop screens draw them at 48–56 px. Opening the waste log pulled 3.15 MB of them
 * (cycle 17c, measured), so the image route can now be asked for a size instead.
 *
 * The cache key carries a prefix of the blob's md5, so replacing a product photo
 * produces a different key and the old thumbnail is simply never asked for again.
 * Nothing here is authoritative: the whole `fv-thumbs/` folder can be deleted at
 * any time and rebuilds itself on demand.
 */
class ProductThumbnailService
{
    /**
     * Sizes the route will honour. 112 is a 56 px tile on a 2× screen.
     *
     * @var array<int, int>
     */
    public const SIZES = [112, 224];

    private const DISK = 'local';

    private const FOLDER = 'fv-thumbs';

    private const QUALITY = 80;

    /**
     * A square JPEG of the blob at `$size`, or null when it cannot be made.
     *
     * Null is a normal answer, not an error: a blob GD cannot decode, or a host
     * without GD, must leave the caller serving the full image rather than failing.
     */
    public function jpeg(string $code, string $blob, int $size): ?string
    {
        $path = $this->path($code, $blob, $size);
        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        try {
            // cover(), not scaleDown(): the tiles are square, and packshots vary
            // enough in aspect that a mixed-height grid looks like a fault.
            $jpeg = ImageManager::gd()
                ->read($blob)
                ->cover($size, $size)
                ->toJpeg(quality: self::QUALITY)
                ->toString();
        } catch (\Throwable $e) {
            return null;
        }

        // A replaced photo writes a new key, so without this the old thumbnail would
        // sit in the folder forever. Only on a miss, and only this product's files
        // at this size, so it is one directory read on a rare path.
        $prefix = $this->prefix($code, $size);

        foreach ($disk->files(self::FOLDER) as $existing) {
            if ($existing !== $path && str_starts_with(basename($existing), $prefix)) {
                $disk->delete($existing);
            }
        }

        $disk->put($path, $jpeg);

        return $jpeg;
    }

    /**
     * Delete every cached file that does not belong to a current product photo.
     *
     * The sweep in jpeg() only runs when a thumbnail is written, so a product whose
     * photo never changes again keeps any orphan it already has. This is the
     * catch-all: give it every product that currently has a photo and anything else
     * in the folder goes.
     *
     * Deletion can fail on permissions — the folder is created by the web server,
     * so a shell run as another user cannot remove from it. That is reported, never
     * thrown: a prune that cannot delete should say so, not blow up a cron job.
     *
     * @param  iterable<\App\Models\Product>  $productsWithPhotos  each with IMAGE loaded
     * @return array{kept: int, deleted: int, failed: array<int, string>}
     */
    public function prune(iterable $productsWithPhotos): array
    {
        $expected = [];

        foreach ($productsWithPhotos as $product) {
            if ($product->IMAGE === null) {
                continue;
            }

            foreach (self::SIZES as $size) {
                $expected[basename($this->path($product->CODE, $product->IMAGE, $size))] = true;
            }
        }

        $disk = Storage::disk(self::DISK);
        $kept = 0;
        $deleted = 0;
        $failed = [];

        foreach ($disk->files(self::FOLDER) as $file) {
            if (isset($expected[basename($file)])) {
                $kept++;

                continue;
            }

            try {
                if ($disk->delete($file)) {
                    $deleted++;
                } else {
                    $failed[] = $file;
                }
            } catch (\Throwable $e) {
                $failed[] = $file;
            }
        }

        return ['kept' => $kept, 'deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Every product whose photo could legitimately be in the cache: the fruit & veg
     * range, plus Jon's own produce, which the harvest screen lists and which is not
     * always in an F&V category.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Product>
     */
    public static function currentPhotoProducts(): Collection
    {
        $jonCodes = SupplierLink::where('SupplierID', (string) config('suppliers.jon'))
            ->pluck('Barcode')
            ->unique()
            ->all();

        return Product::query()
            ->whereNotNull('IMAGE')
            ->where(function ($q) use ($jonCodes) {
                $q->whereIn('CATEGORY', TillVisibilityService::CATEGORY_MAPPINGS['fruit_veg']);

                if ($jonCodes !== []) {
                    $q->orWhereIn('CODE', $jonCodes);
                }
            })
            ->get()
            ->unique('CODE')
            ->values();
    }

    private function path(string $code, string $blob, int $size): string
    {
        return sprintf(
            '%s/%s%s.jpg',
            self::FOLDER,
            $this->prefix($code, $size),
            substr(md5($blob), 0, 12)
        );
    }

    /**
     * Everything in the file name that identifies the product and size, without the
     * blob's hash — so one product's thumbnails at one size can be found and
     * replaced. The code can contain anything the POS allows, so it is hashed
     * rather than trusted as a path segment.
     */
    private function prefix(string $code, int $size): string
    {
        return substr(md5($code), 0, 12).'-'.$size.'-';
    }
}
