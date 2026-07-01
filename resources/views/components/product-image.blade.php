@props([
    'product',                 // Product model (required)
    'supplierService' => null, // SupplierService instance
    'size' => 'md',           // xs, sm, md, lg, xl
    'fallback' => true,       // Show fallback when no image
    'lazy' => true,           // Lazy loading
    'rounded' => true,        // Rounded corners
    'border' => true,         // Border styling
    'hover' => false,         // Enable hover preview
    'hoverSize' => 'w-64 h-64', // Size of hover preview
    'fit' => 'cover',         // object-fit for the thumbnail: 'cover' (crop) or 'contain' (full image)
])

@php
    // Size configurations
    $sizeClasses = [
        'xs' => 'w-6 h-6',
        'sm' => 'w-8 h-8',
        'md' => 'w-10 h-10',
        'lg' => 'w-16 h-16',
        'xl' => 'w-24 h-24',
    ];

    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

    // Build CSS classes
    $objectFit = in_array($fit, ['cover', 'contain'], true) ? $fit : 'cover';
    $imageClasses = $sizeClass . ' object-' . $objectFit;

    if ($rounded) {
        $imageClasses .= ' rounded';
    }

    if ($border) {
        $imageClasses .= ' border border-gray-200 dark:border-gray-700';
    }

    if ($lazy) {
        $imageClasses .= ' animate-pulse';
    }

    // Generate image URL
    $imageUrl = null;
    if ($supplierService && $product) {
        // Handle real Product models
        if ($product instanceof \App\Models\Product) {
            if (method_exists($supplierService, 'getExternalImageUrl')) {
                $imageUrl = $supplierService->getExternalImageUrl($product);
            } elseif (method_exists($supplierService, 'hasExternalIntegration') &&
                      $supplierService->hasExternalIntegration($product->supplier->SupplierID ?? null)) {
                $imageUrl = $supplierService->getExternalImageUrl($product);
            }
        }
        // Handle temporary product objects (for new products / legacy deliveries)
        elseif (isset($product->supplier->SupplierID) &&
                method_exists($supplierService, 'hasExternalIntegration') &&
                $supplierService->hasExternalIntegration($product->supplier->SupplierID)) {
            $sid = $product->supplier->SupplierID;
            $barcodeKeyed = method_exists($supplierService, 'usesSupplierCodeImages')
                && ! $supplierService->usesSupplierCodeImages($sid);

            // Barcode-keyed suppliers (e.g. Udea): trust the barcode we already have — matches the new
            // deliveries page and avoids the supplier_link re-derivation that diverges between the dev
            // snapshot and the live POS (duplicate/stale rows can resolve to an imageless barcode).
            if ($barcodeKeyed && !empty($product->barcode) && method_exists($supplierService, 'getExternalImageUrlByBarcode')) {
                $imageUrl = $supplierService->getExternalImageUrlByBarcode($sid, $product->barcode);
            }
            // {SUPPLIER_CODE} suppliers (e.g. Independent) keep using the supplier-code path (cache + JS resolver).
            if (!$imageUrl && !empty($product->supplier_code) && method_exists($supplierService, 'getExternalImageUrlBySupplierCode')) {
                $imageUrl = $supplierService->getExternalImageUrlBySupplierCode($sid, $product->supplier_code);
            }
            if (!$imageUrl && !empty($product->barcode) && method_exists($supplierService, 'getExternalImageUrlByBarcode')) {
                $imageUrl = $supplierService->getExternalImageUrlByBarcode($sid, $product->barcode);
            }
        }
    }

    // Alternative: try to get image from product directly
    if (!$imageUrl && $product) {
        if (isset($product->image_url)) {
            $imageUrl = $product->image_url;
        }
    }

    $productName = $product->NAME ?? ($product->name ?? 'Product');
    $hasImage = !empty($imageUrl);
@endphp

@if($hasImage)
    @if($hover)
        {{-- Hover/tap-enabled version with Alpine.js --}}
        <div
            class="relative {{ $sizeClass }}"
            x-data="{ show: false, tapped: false, pos: { x: 0, y: 0 } }"
            @mouseenter="
                if (tapped) return;
                const rect = $el.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;
                const previewHeight = 320;

                pos.x = rect.left;
                if (spaceBelow >= previewHeight || spaceBelow > spaceAbove) {
                    pos.y = rect.bottom + 8;
                } else {
                    pos.y = rect.top - previewHeight - 8;
                }
                show = true;
            "
            @mouseleave="if (!tapped) show = false"
            @click.prevent.stop="tapped = true; show = true"
        >
            <img
                src="{{ $imageUrl }}"
                alt="{{ $productName }}"
                class="{{ $imageClasses }} cursor-pointer"
                @if($lazy)
                    loading="lazy"
                    onload="this.classList.remove('animate-pulse')"
                @endif
                onerror="this.style.display='none'; @if($fallback) this.parentElement.querySelector('.fallback-icon')?.style.display='flex'; @endif"
                {{ $attributes->except(['product', 'supplierService', 'size', 'fallback', 'lazy', 'rounded', 'border', 'hover', 'hoverSize', 'fit']) }}
            >

            {{-- Desktop: positioned hover preview --}}
            <template x-teleport="body">
                <div
                    x-show="show && !tapped"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed z-[99999] pointer-events-none w-64"
                    :style="'left: ' + pos.x + 'px; top: ' + pos.y + 'px;'"
                >
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $productName }}"
                        class="w-64 h-auto max-h-80 object-contain rounded-lg border-2 border-white dark:border-gray-600 shadow-2xl bg-white"
                    >
                    @if($productName)
                        <div class="bg-black bg-opacity-75 text-white text-xs p-2 rounded-b-lg truncate max-w-64">
                            {{ $productName }}
                        </div>
                    @endif
                </div>
            </template>

            {{-- Tap overlay: centered with backdrop and close button --}}
            <template x-teleport="body">
                <div
                    x-show="show && tapped"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/50"
                    @click.self="show = false; tapped = false"
                >
                    <div class="relative max-w-sm mx-4">
                        <button @click.stop="show = false; tapped = false"
                                class="absolute -top-3 -right-3 z-10 w-8 h-8 bg-white rounded-full shadow-lg flex items-center justify-center text-gray-600 hover:text-gray-900 touch-manipulation">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <img
                            src="{{ $imageUrl }}"
                            alt="{{ $productName }}"
                            class="w-full h-auto max-h-[70vh] object-contain rounded-lg border-2 border-white shadow-2xl bg-white"
                        >
                        @if($productName)
                            <div class="bg-black bg-opacity-75 text-white text-sm p-2.5 rounded-b-lg truncate">
                                {{ $productName }}
                            </div>
                        @endif
                    </div>
                </div>
            </template>

            @if($fallback)
                <div class="fallback-icon absolute inset-0 bg-gray-100 dark:bg-gray-700 {{ $rounded ? 'rounded' : '' }} {{ $border ? 'border border-gray-200 dark:border-gray-700' : '' }} flex items-center justify-center" style="display: none;">
                    <svg class="w-1/2 h-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            @endif
        </div>
    @else
        {{-- Non-hover version --}}
        <div class="relative {{ $sizeClass }}">
            <img
                src="{{ $imageUrl }}"
                alt="{{ $productName }}"
                class="{{ $imageClasses }}"
                @if($lazy)
                    loading="lazy"
                    onload="this.classList.remove('animate-pulse')"
                @endif
                onerror="this.style.display='none'; @if($fallback) this.parentElement.querySelector('.fallback-icon')?.style.display='flex'; @endif"
                {{ $attributes->except(['product', 'supplierService', 'size', 'fallback', 'lazy', 'rounded', 'border', 'hover', 'hoverSize', 'fit']) }}
            >

            @if($fallback)
                <div class="fallback-icon absolute inset-0 bg-gray-100 dark:bg-gray-700 {{ $rounded ? 'rounded' : '' }} {{ $border ? 'border border-gray-200 dark:border-gray-700' : '' }} flex items-center justify-center" style="display: none;">
                    <svg class="w-1/2 h-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            @endif
        </div>
    @endif
@elseif($fallback)
    <!-- No image available - show fallback icon -->
    <div class="{{ $sizeClass }} bg-gray-100 dark:bg-gray-700 {{ $rounded ? 'rounded' : '' }} {{ $border ? 'border border-gray-200 dark:border-gray-700' : '' }} flex items-center justify-center">
        <svg class="w-1/2 h-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    </div>
@endif