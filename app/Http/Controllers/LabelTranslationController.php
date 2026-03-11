<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Services\ZplGeneratorService;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LabelTranslationController extends Controller
{
    public function __construct(
        protected ZplGeneratorService $zplGenerator,
    ) {}

    /**
     * Show the single-page translation workflow.
     */
    public function index(Request $request): View
    {
        $labelSizes = $this->zplGenerator->getAvailableSizes();

        // If editing an existing translation, pass it
        $editTranslation = null;
        if ($request->has('edit')) {
            $editTranslation = ProductTranslation::find($request->input('edit'));
        }

        return view('labels.translate', [
            'labelSizes' => $labelSizes,
            'editTranslation' => $editTranslation,
        ]);
    }

    /**
     * Check if a product has an existing translation.
     */
    public function checkExisting(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);

        $translation = ProductTranslation::latestForProduct($request->input('barcode'));

        if ($translation) {
            return response()->json([
                'exists' => true,
                'translation' => [
                    'id' => $translation->id,
                    'label_data' => $translation->label_data,
                    'label_size' => $translation->label_size,
                    'font_scale' => $translation->font_scale,
                    'zpl_content' => $translation->zpl_content,
                    'original_photos' => $translation->original_photos,
                    'created_at' => $translation->created_at->format('M j, Y g:ia'),
                ],
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Upload photos, send to Gemini, return translation data + ZPL.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'label_images' => 'required|array|min:1|max:5',
            'label_images.*' => 'required|image|max:5120',
            'label_size' => 'required|in:'.$this->zplGenerator->getValidSizeKeys(),
            'product_code' => 'nullable|string|max:255',
            'product_id' => 'nullable|string|max:255',
        ]);

        $labelSize = $request->input('label_size', 'large');
        $storedPaths = [];
        $resizedImages = [];

        try {
            // Process each uploaded photo
            foreach ($request->file('label_images') as $file) {
                $path = $file->store('labels/translations', 'public');
                $storedPaths[] = $path;

                // Resize image to reduce Gemini API payload
                $fullPath = Storage::disk('public')->path($path);
                $img = @imagecreatefromjpeg($fullPath) ?: @imagecreatefrompng($fullPath);

                if (! $img) {
                    continue;
                }

                $origW = imagesx($img);
                $origH = imagesy($img);
                $maxDim = 1200;

                if (max($origW, $origH) > $maxDim) {
                    $scale = $maxDim / max($origW, $origH);
                    $newW = (int) ($origW * $scale);
                    $newH = (int) ($origH * $scale);
                    $resized = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagedestroy($img);
                    $img = $resized;
                }

                ob_start();
                imagejpeg($img, null, 80);
                $resizedImages[] = base64_encode(ob_get_clean());
                imagedestroy($img);
            }

            if (empty($resizedImages)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid images could be processed.',
                ], 422);
            }

            // Build Gemini prompt
            $prompt = 'You may receive multiple photos of the same product label (front, back, sides). '
                .'Combine information from ALL photos into a single JSON response. '
                ."If the same field appears in multiple photos, use the most complete version.\n\n"
                ."Extract data from this food label into JSON.\n\n"
                ."Translate to English.\n\n"
                ."STRICT VERBATIM: Only include information physically present on the label.\n\n"
                .'CONDITIONAL FIELDS: For fields like address, origin, or nutrition_inline, '
                .'if the information is NOT present on the label, set the value to null. '
                ."Do not guess or use external knowledge.\n\n"
                .'ALLERGENS: Format the ingredients string with EU allergens in ALL CAPS. '
                .'The 14 EU allergens: Cereals (GLUTEN), CRUSTACEANS, EGGS, FISH, PEANUTS, '
                ."SOYBEANS, MILK, NUTS, CELERY, MUSTARD, SESAME, SULPHITES, LUPIN, MOLLUSCS.\n\n"
                .'ANNOTATIONS: Preserve asterisk annotations (*, **) on ingredients and include '
                .'their explanations at the end of the ingredients string. '
                ."Example: 'tomatoes** 65%, olive oil*, salt. *from organic farming. **from biodynamic agriculture.'\n\n"
                .'JSON STRUCTURE: Return only the JSON object with keys: '
                ."product_name, ingredients, nutrition_inline, storage, address, origin, original_text.\n"
                ."Do NOT include net_weight — it is already on the packaging.\n\n"
                ."ORIGINAL TEXT: Include the original (untranslated) text from the label in the 'original_text' field. "
                ."This should be the raw text as it appears on the label, in the original language, "
                ."so the user can verify the translation is correct. Include product name, ingredients, "
                ."and any other text visible on the label.\n\n"
                ."Example output:\n"
                .'{"product_name":"Sun-Dried Tomatoes in Oil",'
                .'"ingredients":"Sun-dried tomatoes** 60%, sunflower oil*, SULPHITES (as preservative), salt, garlic, oregano. *from organic farming. **from organic and biodynamic agriculture.",'
                .'"nutrition_inline":"Energy 245kcal | Fat 18g | Sat 2.1g | Carbs 12g | Sugar 8g | Protein 5g | Salt 1.2g",'
                .'"storage":"Store in a cool, dry place. Once opened, refrigerate and use within 3 days.",'
                .'"address":"Via Roma 12, 80100 Naples, Italy",'
                .'"origin":null,'
                .'"original_text":"Pomodori Secchi sott\'olio. Ingredienti: pomodori secchi** 60%, olio di girasole*, SOLFITI (come conservante), sale, aglio, origano. *da agricoltura biologica. **da agricoltura biologica e biodinamica."}';

            // Build content array with prompt + all image blobs
            $contents = [$prompt];
            foreach ($resizedImages as $imageData) {
                $contents[] = new Blob(
                    mimeType: MimeType::IMAGE_JPEG,
                    data: $imageData,
                );
            }

            $result = Gemini::generativeModel(model: 'gemini-2.5-flash')
                ->generateContent($contents);

            $text = $result->text();

            // Strip markdown code fencing if Gemini wraps it
            $text = preg_replace('/^```(?:json)?\s*\n?/m', '', $text);
            $text = preg_replace('/\n?```\s*$/m', '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (! $data || ! array_key_exists('product_name', $data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned invalid data. Please try again.',
                    'raw_response' => $text,
                ], 422);
            }

            // Generate ZPL with auto-fit
            [$zpl, $effectiveScale] = $this->zplGenerator->generateZplWithScale($data, $labelSize, 2.0);

            return response()->json([
                'success' => true,
                'label_data' => $data,
                'zpl_content' => $zpl,
                'font_scale' => $effectiveScale,
                'label_size' => $labelSize,
                'original_photos' => $storedPaths,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI translation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save a translation to the database.
     */
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'label_data' => 'required|array',
            'label_size' => 'required|in:'.$this->zplGenerator->getValidSizeKeys(),
            'font_scale' => 'nullable|numeric|min:0.5|max:2.0',
            'zpl_content' => 'required|string',
            'original_photos' => 'nullable|array',
            'product_id' => 'nullable|string|max:255',
            'product_code' => 'nullable|string|max:255',
            'translation_id' => 'nullable|integer',
        ]);

        $translationData = [
            'product_id' => $request->input('product_id'),
            'product_code' => $request->input('product_code'),
            'label_data' => $request->input('label_data'),
            'label_size' => $request->input('label_size'),
            'font_scale' => (float) $request->input('font_scale', 1.0),
            'original_photos' => $request->input('original_photos'),
            'zpl_content' => $request->input('zpl_content'),
            'created_by' => Auth::id(),
        ];

        // Update existing or create new
        if ($request->input('translation_id')) {
            $translation = ProductTranslation::findOrFail($request->input('translation_id'));
            $translation->update($translationData);
        } else {
            $translation = ProductTranslation::create($translationData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Translation saved.',
            'translation_id' => $translation->id,
        ]);
    }

    /**
     * Translation history page.
     */
    public function history(): View
    {
        $translations = ProductTranslation::with('user')
            ->latest()
            ->paginate(20);

        return view('labels.translate-history', [
            'translations' => $translations,
            'labelSizes' => $this->zplGenerator->getAvailableSizes(),
        ]);
    }

    /**
     * Get a single translation as JSON.
     */
    public function show(ProductTranslation $translation): JsonResponse
    {
        return response()->json([
            'success' => true,
            'translation' => [
                'id' => $translation->id,
                'product_id' => $translation->product_id,
                'product_code' => $translation->product_code,
                'label_data' => $translation->label_data,
                'label_size' => $translation->label_size,
                'font_scale' => $translation->font_scale,
                'zpl_content' => $translation->zpl_content,
                'original_photos' => $translation->original_photos,
                'created_at' => $translation->created_at->format('M j, Y g:ia'),
            ],
        ]);
    }
}
