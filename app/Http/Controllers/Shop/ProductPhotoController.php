<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Concerns\ServesProductThumbnails;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductThumbnailService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A small thumbnail of a product's till photo, for signed-in staff.
 *
 * The office `products.image` route serves the stored blob, which runs to a
 * megabyte and is drawn at 48 px on a Shop row. This one serves a cached 112 px
 * JPEG instead, and never the stored photo.
 *
 * Unlike the customer-requests photo route it is not public and needs no product
 * gate: it sits inside the Shop's auth group, and any member of staff who can open
 * a Shop screen may already see the product's name and code on it.
 */
class ProductPhotoController extends Controller
{
    use ServesProductThumbnails;

    /** The Shop draws these at 48 px; 112 covers a 2x screen. */
    private const SIZE = 112;

    public function show(string $code, Request $request, ProductThumbnailService $thumbnails): Response
    {
        $product = Product::where('CODE', $code)->first(['ID', 'CODE', 'IMAGE']);

        abort_if($product === null || $product->IMAGE === null || $product->IMAGE === '', 404);

        return $this->thumbnailResponse($product, self::SIZE, $request, $thumbnails);
    }
}
