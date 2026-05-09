<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ZebraLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ZebraLabelController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $labels = ZebraLabel::active()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return view('zebra-labels.index', compact('labels', 'search'));
    }

    public function create(): View
    {
        return view('zebra-labels.create');
    }

    public function lookupProduct(Request $request)
    {
        $request->validate(['code' => 'required|string|max:255']);

        $code = $request->input('code');
        $product = Product::where('CODE', $code)->first();

        if (! $product) {
            return response()->json(['found' => false]);
        }

        $category = DB::connection('pos')
            ->table('CATEGORIES')
            ->where('ID', $product->CATEGORY)
            ->value('NAME');

        $existingLabel = ZebraLabel::active()
            ->where('product_code', $code)
            ->first(['id', 'name']);

        return response()->json([
            'found' => true,
            'product' => [
                'name' => $product->NAME,
                'code' => $product->CODE,
                'buy_price' => $product->PRICEBUY,
                'sell_price' => $product->PRICESELL,
                'category' => $category,
            ],
            'existing_label' => $existingLabel ? [
                'id' => $existingLabel->id,
                'name' => $existingLabel->name,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'zpl_file' => 'required_without:zpl_content|file|max:1024',
            'zpl_content' => 'required_without:zpl_file|nullable|string',
            'product_code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'label_width_mm' => 'nullable|numeric|min:1|max:999',
            'label_height_mm' => 'nullable|numeric|min:1|max:999',
        ]);

        // Get ZPL content from file or textarea
        $zplContent = $request->input('zpl_content');
        $originalFilename = null;

        if ($request->hasFile('zpl_file')) {
            $file = $request->file('zpl_file');
            $zplContent = file_get_contents($file->getRealPath());
            $originalFilename = $file->getClientOriginalName();
            // Strip BOM if present
            $zplContent = preg_replace('/^\xEF\xBB\xBF/', '', $zplContent);
        }

        // Validate ZPL contains required markers
        if (! str_contains($zplContent, '^XA') || ! str_contains($zplContent, '^XZ')) {
            return back()->withErrors(['zpl_content' => 'Invalid ZPL: must contain ^XA and ^XZ markers.'])->withInput();
        }

        // Auto-extract barcode if not provided
        $productCode = $request->input('product_code');
        if (empty($productCode)) {
            $productCode = ZebraLabel::extractBarcodeFromZpl($zplContent);
        }

        // Try to find product_id from barcode
        $productId = null;
        if ($productCode) {
            $product = Product::where('CODE', $productCode)->first();
            $productId = $product?->ID;
        }

        $label = ZebraLabel::create([
            'name' => $request->input('name'),
            'product_code' => $productCode,
            'product_id' => $productId,
            'description' => $request->input('description'),
            'zpl_content' => $zplContent,
            'original_filename' => $originalFilename,
            'label_width_mm' => $request->input('label_width_mm'),
            'label_height_mm' => $request->input('label_height_mm'),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('zebra-labels.show', $label)
            ->with('success', 'Label saved successfully.');
    }

    public function show(ZebraLabel $zebraLabel): View
    {
        $zplCopies = ZebraLabel::extractPrintQuantity($zebraLabel->zpl_content);
        $defaultCopies = $zebraLabel->default_copies ?? $zplCopies;
        $fields = ZebraLabel::extractTextFields($zebraLabel->zpl_content);

        // Use dimensions from ZPL, falling back to stored values
        $zplDims = ZebraLabel::extractDimensions($zebraLabel->zpl_content);
        $previewWidth = $zplDims['width_mm'] ?? $zebraLabel->label_width_mm ?? 76;
        $previewHeight = $zplDims['height_mm'] ?? $zebraLabel->label_height_mm ?? 50;

        // Load product details with category name from POS
        $productDetails = null;
        $product = $zebraLabel->product;
        if ($product) {
            $category = DB::connection('pos')
                ->table('CATEGORIES')
                ->where('ID', $product->CATEGORY)
                ->value('NAME');

            $productDetails = [
                'name' => $product->NAME,
                'code' => $product->CODE,
                'buy_price' => $product->PRICEBUY,
                'sell_price' => $product->PRICESELL,
                'category' => $category,
            ];
        }

        return view('zebra-labels.show', compact('zebraLabel', 'defaultCopies', 'zplCopies', 'fields', 'productDetails', 'previewWidth', 'previewHeight'));
    }

    public function updateCopies(Request $request, ZebraLabel $zebraLabel)
    {
        $request->validate([
            'default_copies' => 'required|integer|min:1|max:99',
        ]);

        $zebraLabel->update(['default_copies' => $request->input('default_copies')]);

        return response()->json(['success' => true, 'default_copies' => $zebraLabel->default_copies]);
    }

    public function updateFields(Request $request, ZebraLabel $zebraLabel)
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'string',
        ]);

        $zpl = ZebraLabel::replaceTextFields($zebraLabel->zpl_content, $request->input('fields'));

        $zebraLabel->update(['zpl_content' => $zpl]);

        // Re-extract fields from the updated ZPL to return the new canonical values
        $newFields = ZebraLabel::extractTextFields($zpl);

        return response()->json(['success' => true, 'fields' => $newFields]);
    }

    public function destroy(ZebraLabel $zebraLabel)
    {
        $zebraLabel->update(['is_active' => false]);

        return redirect()->route('zebra-labels.index')
            ->with('success', 'Label deleted.');
    }

    public function print(Request $request, ZebraLabel $zebraLabel)
    {
        $copies = max(1, min(99, (int) $request->input('copies', 1)));

        $zpl = $zebraLabel->zpl_content;

        // Apply field overrides if provided
        $fields = $request->input('fields');
        if (is_array($fields) && count($fields) > 0) {
            $zpl = ZebraLabel::replaceTextFields($zpl, $fields);
        }

        // Set ^PQ quantity in ZPL (instead of repeating the whole ZPL which re-downloads ~DG graphics)
        $zpl = ZebraLabel::setZplQuantity($zpl, $copies);

        // Write to temp file and send to printer (same pattern as LabelAreaController::printZpl)
        $tmpFile = tempnam(sys_get_temp_dir(), 'zpl_');
        file_put_contents($tmpFile, $zpl);

        $host = config('services.zebra.host', '10.42.1.71');
        $port = config('services.zebra.port', '631');
        $printer = config('services.zebra.name', 'ZTC-GX430t');

        $command = "lp -h {$host}:{$port}/version=1.1 -d {$printer} -o raw {$tmpFile} 2>&1";
        $output = shell_exec($command);

        unlink($tmpFile);

        $success = $output && str_contains($output, 'request id');

        return response()->json([
            'success' => $success,
            'message' => $success ? "Print job sent ({$copies} ".($copies === 1 ? 'copy' : 'copies').')' : 'Print failed',
            'output' => trim($output ?? 'No output'),
        ], $success ? 200 : 500);
    }
}
