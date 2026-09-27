<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Services\ProductThumbnailService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response half of a product thumbnail route.
 *
 * Two routes serve these now — the public customer-requests one and the Shop one —
 * and they differ only in who may ask and for which products. Everything after that
 * decision is identical, so it lives here: encode (or read from the cache), refuse
 * rather than fall back if the blob cannot be read, and set the caching headers.
 *
 * `?v=` is the first 8 hex of the photo's md5, as `ProductThumbnailService::versions()`
 * produces. When it matches, the answer can be cached for a week — replacing the
 * photo changes the URL, so a stale one is never confirmed. A wrong or missing `v`
 * is served short-lived, so a guessed link cannot pin an old picture.
 */
trait ServesProductThumbnails
{
    protected function thumbnailResponse(
        Product $product,
        int $size,
        Request $request,
        ProductThumbnailService $thumbnails
    ): Response {
        $jpeg = $thumbnails->jpeg($product->CODE, $product->IMAGE, $size);

        // Null means the encoder could not read the blob. The stored photo is not
        // an acceptable fallback: these routes exist so it is never served.
        abort_if($jpeg === null, 404);

        $versioned = ($version = (string) $request->query('v')) !== ''
            && hash_equals(substr(md5($product->IMAGE), 0, 8), $version);

        $cacheControl = $versioned
            ? 'public, max-age=604800, immutable'
            : 'public, max-age=0, must-revalidate';

        $etag = '"'.md5($jpeg).'"';

        // The same lifetime on the 304: a client that revalidates once must not be
        // dropped back to asking every time.
        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => $cacheControl]);
        }

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
        ]);
    }
}
