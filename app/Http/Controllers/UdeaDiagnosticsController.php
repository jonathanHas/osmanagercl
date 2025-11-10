<?php

namespace App\Http\Controllers;

use App\Services\UdeaScrapingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class UdeaDiagnosticsController extends Controller
{
    public function __invoke(Request $request, UdeaScrapingService $udeaService): View
    {
        $result = null;
        $error = null;
        $supplierCode = trim((string) $request->query('supplier_code'));

        if ($supplierCode !== '') {
            $cacheKey = "udea_product_{$supplierCode}";
            $forceRefresh = $request->boolean('fresh');

            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            $cacheHitBeforeCall = Cache::has($cacheKey);

            try {
                $data = $udeaService->getProductData($supplierCode);

                $result = [
                    'supplier_code' => $supplierCode,
                    'from_cache' => $cacheHitBeforeCall && ! $forceRefresh,
                    'fresh_request' => $forceRefresh,
                    'data' => $data,
                ];

                if (! $data) {
                    $error = 'No product data was returned by Udea.';
                }
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('tools.udea-debug', compact('result', 'error', 'supplierCode'));
    }
}
