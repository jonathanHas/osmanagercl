<?php

namespace App\Services;

use App\Models\LabelLog;
use App\Models\PosUnit;
use App\Models\Product;
use App\Models\ProductMetadata;
use App\Models\StockCurrent;
use App\Models\Stocking;
use App\Models\VegDetails;
use App\Models\VegPrintQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Creates a fruit & veg till product in one go: the POS product, its origin /
 * class / unit, the till button, stock rows, the label queue and (optionally)
 * the till picture.
 *
 * It follows the same steps as ProductController@store for the parts they
 * share (UUID id, ex-VAT sell price, STOCKCURRENT, stocking, label log,
 * metadata, 128px image) and adds the fruit & veg details that page does not
 * set. Used by Dynamis invoice pricing.
 */
class FruitVegProductService
{
    public const CATEGORIES = ['SUB1', 'SUB2', 'SUB3'];

    /** Fruit & veg is zero-rated; every existing F&V product uses this tax category. */
    public const TAX_CATEGORY = '000';

    private const IMAGE_SIZE = 128;

    public function __construct(
        private BarcodeGeneratorService $barcodes,
        private TillVisibilityService $tillVisibility,
    ) {}

    /**
     * @param array{
     *     name: string, display?: ?string, category: string, price: float, cost?: ?float,
     *     unit: string, country_id: int, class_id: int, show_on_till?: bool, source?: string,
     * } $data  `unit` is 'kg' or 'each'; `price` is the shelf price
     *
     * @throws InvalidArgumentException when the name is taken or the unit is unknown
     */
    public function create(array $data, ?UploadedFile $image = null, ?int $userId = null): Product
    {
        $name = trim($data['name']);
        if (! in_array($data['category'], self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Choose Fruit or Vegetables.');
        }
        if (Product::where('NAME', $name)->exists()) {
            throw new InvalidArgumentException("There is already a product called \"{$name}\". Choose it instead, or use a different name.");
        }

        $unitId = PosUnit::where('units', $data['unit'])->value('ID');
        if ($unitId === null) {
            throw new InvalidArgumentException('Choose per kg or each.');
        }

        // Read the picture before anything is written, so a bad file creates nothing.
        $imageData = $image ? $this->encodeImage($image) : null;

        $product = DB::connection('pos')->transaction(function () use ($data, $name, $unitId, $imageData) {
            $code = $this->barcodes->nextForCategory($data['category']);

            $product = Product::create([
                'ID' => (string) Str::uuid(),
                'NAME' => $name,
                'CODE' => $code,
                'REFERENCE' => $code,
                'CATEGORY' => $data['category'],
                'TAXCAT' => self::TAX_CATEGORY,
                'PRICEBUY' => round((float) ($data['cost'] ?? 0), 4),
                'PRICESELL' => round((float) $data['price'], 2), // zero-rated: shelf price = net price
                'DISPLAY' => trim((string) ($data['display'] ?? '')) ?: null,
            ]);

            if ($imageData !== null) {
                DB::connection('pos')->table('PRODUCTS')->where('ID', $product->ID)->update(['IMAGE' => $imageData]);
            }

            VegDetails::upsertForProduct($code, [
                'countryCode' => (int) $data['country_id'],
                'classId' => (int) $data['class_id'],
                'unitId' => (int) $unitId,
            ]);

            return $product;
        });

        // The rest must not undo a product the till can already sell.
        $this->quietly('STOCKCURRENT', $product, fn () => StockCurrent::create([
            'LOCATION' => '0', 'PRODUCT' => $product->ID, 'ATTRIBUTESETINSTANCE_ID' => null, 'UNITS' => 0.0,
        ]));
        $this->quietly('stocking', $product, fn () => Stocking::create(['Barcode' => $product->CODE]));
        $this->quietly('label log', $product, fn () => LabelLog::logNewProduct($product->CODE));
        $this->quietly('metadata', $product, fn () => ProductMetadata::createForProduct(
            $product->ID, $product->CODE, $userId,
            ['source' => $data['source'] ?? 'fruit_veg', 'has_display_name' => $product->DISPLAY !== null],
        ));
        $this->quietly('label queue', $product, fn () => VegPrintQueue::addToQueue($product->CODE, 'new_product'));

        if ($data['show_on_till'] ?? true) {
            $this->quietly('till button', $product, fn () => $this->tillVisibility->setVisibility($product->ID, true, 'fruit_veg'));
        }

        return $product;
    }

    /** The till shows small pictures: scale to fit 128px, keep the format. */
    private function encodeImage(UploadedFile $file): string
    {
        $image = (new ImageManager(new GdDriver))->read($file->getRealPath());
        $image->scaleDown(self::IMAGE_SIZE, self::IMAGE_SIZE);

        $extension = match (strtolower($file->getClientOriginalExtension() ?: '')) {
            'png' => 'png',
            'gif' => 'gif',
            default => 'jpg',
        };

        return $image->encodeByExtension($extension)->toString();
    }

    private function quietly(string $step, Product $product, callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            Log::warning("New fruit & veg product: {$step} step failed", [
                'product_id' => $product->ID,
                'product_code' => $product->CODE,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
