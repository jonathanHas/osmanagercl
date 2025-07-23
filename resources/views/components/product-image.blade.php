@props([
    'product',                 // Product model (required)
    'supplierService' => null, // SupplierService instance
    'size' => 'md',           // xs, sm, md, lg, xl
    'fallback' => true,       // Show fallback when no image
    'lazy' => true,           // Lazy loading
    'rounded' => true,        // Rounded corners
    'border' => true,         // Border styling
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
    $imageClasses = $sizeClass . ' object-cover';
    
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
        if (method_exists($supplierService, 'getExternalImageUrl')) {
            $imageUrl = $supplierService->getExternalImageUrl($product);
        } elseif (method_exists($supplierService, 'hasExternalIntegration') && 
                  $supplierService->hasExternalIntegration($product->supplier->SupplierID ?? null)) {
            $imageUrl = $supplierService->getExternalImageUrl($product);
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
    <div class="relative {{ $sizeClass }}">
        <img 
            src="{{ $imageUrl }}" 
            alt="{{ $productName }}"
            class="{{ $imageClasses }}"
            @if($lazy) 
                loading="lazy"
                onload="this.classList.remove('animate-pulse')"
            @endif
            onerror="this.style.display='none'; this.parentElement.style.display='{{ $fallback ? 'block' : 'none' }}'; @if($fallback) this.parentElement.querySelector('.fallback-icon').style.display='flex'; @endif"
            {{ $attributes->except(['product', 'supplierService', 'size', 'fallback', 'lazy', 'rounded', 'border']) }}
        >
        @if($fallback)
            <div class="fallback-icon absolute inset-0 bg-gray-100 dark:bg-gray-700 {{ $rounded ? 'rounded' : '' }} {{ $border ? 'border border-gray-200 dark:border-gray-700' : '' }} flex items-center justify-center" style="display: none;">
                <svg class="w-1/2 h-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        @endif
    </div>
@elseif($fallback)
    <!-- No image available - show fallback icon -->
    <div class="{{ $sizeClass }} bg-gray-100 dark:bg-gray-700 {{ $rounded ? 'rounded' : '' }} {{ $border ? 'border border-gray-200 dark:border-gray-700' : '' }} flex items-center justify-center">
        <svg class="w-1/2 h-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    </div>
@endif