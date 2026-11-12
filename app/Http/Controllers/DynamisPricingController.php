<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DynamisPricingLine;
use App\Services\DeliveryDocumentService;
use App\Services\DynamisInvoicePdfParserService;
use App\Services\DynamisPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Dynamis invoice pricing (managers and admins): upload the invoice PDF, get a
 * delivery docket and a page to price each fruit & veg line for the till.
 * See docs/features/dynamis-pricing.md.
 */
class DynamisPricingController extends Controller
{
    public function __construct(
        private DynamisInvoicePdfParserService $parser,
        private DynamisPricingService $pricing,
    ) {}

    /** Upload an invoice PDF: creates the docket and opens its pricing page. */
    public function store(Request $request, DeliveryDocumentService $documents): RedirectResponse
    {
        $request->validate([
            'invoice_pdf' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('invoice_pdf');
        $path = Storage::disk('local')->path($file->store('temp'));

        try {
            $parsed = $this->parser->parseFile($path);

            if (! $parsed['success']) {
                return back()->withErrors(['invoice_pdf' => $parsed['errors'][0] ?? 'Could not read the invoice.']);
            }

            $number = $parsed['data']['metadata']['invoice_number'];
            if ($existing = $this->pricing->findByInvoiceNumber($number)) {
                return redirect()
                    ->route('deliveries.dynamis-pricing.show', $existing)
                    ->with('success', "Invoice {$number} was already imported. This is its pricing page.");
            }

            $delivery = $this->pricing->createDocket($parsed, $file->getClientOriginalName());

            $documents->store(
                $delivery,
                $path,
                $file->getClientOriginalName(),
                'invoice_pdf',
                ['item_count' => count($parsed['data']['items'])] + $parsed['data']['totals'],
                $number
            );

            if ($parsed['warnings']) {
                app(\App\Services\DeliveryService::class)->recordParserWarnings($delivery, $parsed['warnings']);
            }

            return redirect()
                ->route('deliveries.dynamis-pricing.show', $delivery)
                ->with('success', 'Dynamis invoice '.($number ?? '').' imported: '.count($parsed['data']['items']).' lines.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['invoice_pdf' => 'Failed to import the invoice: '.$e->getMessage()]);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function show(Delivery $delivery): View
    {
        abort_unless($this->pricing->hasPricing($delivery), 404);

        // Till visibility changes through the week; unlinked lines follow it.
        $this->pricing->refreshGuesses($delivery);

        return view('deliveries.dynamis-pricing', [
            'delivery' => $delivery,
            'invoice' => $delivery->import_data['dynamis'] ?? [],
            'settings' => $this->pricing->settings($delivery),
            'defaults' => $this->pricing->defaults(),
            'lines' => $this->pricing->lines($delivery),
            'warnings' => $delivery->import_data['parser_warnings'] ?? [],
        ]);
    }

    /** Transport and markup for this invoice (optionally also the default for new ones). */
    public function updateSettings(Request $request, Delivery $delivery): JsonResponse
    {
        $data = $request->validate([
            'transport' => 'required|numeric|min:1|max:3',
            'markup' => 'required|numeric|min:1|max:5',
            'as_default' => 'sometimes|boolean',
        ]);

        $this->pricing->saveSettings($delivery, (float) $data['transport'], (float) $data['markup'], (bool) ($data['as_default'] ?? false));

        return response()->json(['success' => true, 'settings' => $this->pricing->settings($delivery->refresh())]);
    }

    /** Choose (or clear) the till product for a line. */
    public function link(Request $request, Delivery $delivery, DynamisPricingLine $line): JsonResponse
    {
        $this->guard($delivery, $line);

        $data = $request->validate([
            'product_id' => ['nullable', 'string', Rule::exists(\App\Models\Product::class, 'ID')],
        ]);

        $this->pricing->linkProduct($line, $data['product_id'] ?? null, $request->user()->id);

        return response()->json(['success' => true, 'line' => $this->pricing->presentLine($line->refresh())]);
    }

    /** Write the settled price to the till. */
    public function apply(Request $request, Delivery $delivery, DynamisPricingLine $line): JsonResponse
    {
        $this->guard($delivery, $line);

        $data = $request->validate([
            'mode' => ['required', Rule::in([DynamisPricingLine::MODE_PER_KG, DynamisPricingLine::MODE_EACH])],
            'units_per_box' => 'nullable|numeric|gt:0|max:9999',
            'kg_per_box' => 'nullable|numeric|gt:0|max:9999',
            'price' => 'required|numeric|gt:0|max:9999',
        ]);

        try {
            $changed = $this->pricing->apply(
                $line,
                $data['mode'],
                isset($data['units_per_box']) ? (float) $data['units_per_box'] : null,
                isset($data['kg_per_box']) ? (float) $data['kg_per_box'] : null,
                (float) $data['price'],
                $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'The till price could not be updated. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'changed' => $changed,
            'line' => $this->pricing->presentLine($line->refresh()),
        ]);
    }

    /** Skip a line this week, or bring it back. */
    public function skip(Request $request, Delivery $delivery, DynamisPricingLine $line): JsonResponse
    {
        $this->guard($delivery, $line);

        $this->pricing->skip($line, $request->boolean('skip', true));

        return response()->json(['success' => true, 'line' => $this->pricing->presentLine($line->refresh())]);
    }

    /** Put the line's till product on the till buttons. */
    public function showOnTill(Delivery $delivery, DynamisPricingLine $line): JsonResponse
    {
        $this->guard($delivery, $line);

        try {
            $this->pricing->showOnTill($line);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'line' => $this->pricing->presentLine($line->refresh())]);
    }

    /** Set the line's till product to the invoice's country of origin. */
    public function country(Delivery $delivery, DynamisPricingLine $line): JsonResponse
    {
        $this->guard($delivery, $line);

        try {
            $this->pricing->useInvoiceCountry($line);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'line' => $this->pricing->presentLine($line->refresh())]);
    }

    private function guard(Delivery $delivery, DynamisPricingLine $line): void
    {
        abort_unless($line->delivery_id === $delivery->id && ! $line->is_barcoded, 404);
    }
}
