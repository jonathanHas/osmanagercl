<?php

namespace App\Services;

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

        $disk->put($path, $jpeg);

        return $jpeg;
    }

    private function path(string $code, string $blob, int $size): string
    {
        // The code can contain anything the POS allows, so it is hashed too rather
        // than trusted as a path segment.
        return sprintf(
            '%s/%s-%d-%s.jpg',
            self::FOLDER,
            substr(md5($code), 0, 12),
            $size,
            substr(md5($blob), 0, 12)
        );
    }
}
