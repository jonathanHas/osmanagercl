{{--
    One product row for the kitchen order pages.

    Expects:
      $row         array: product (Product|null), supplier_code, case_units, orderable (optional)
      $standing    array<product_id, int>
      $lastOrders  array<product_id, [['quantity' => int, 'date' => Carbon], ...]> (optional)
      $showHistory bool (default true)
      $imageSize   'xl' | 'lg' (default 'xl')
--}}
@php
    $product = $row['product'] ?? null;
    $showHistory = $showHistory ?? true;
    $imageSize = $imageSize ?? 'xl';
    $orderable = $row['orderable'] ?? true;
    $productId = $product->ID ?? null;
    $supplierCode = $row['supplier_code'] ?? null;
    $caseUnits = (int) ($row['case_units'] ?? 1);
    $standingQty = $productId !== null ? (int) ($standing[$productId] ?? 0) : 0;
    $currentQty = $productId !== null ? (int) old('qty.'.$productId, $standingQty) : 0;
    $history = ($showHistory && $productId !== null) ? ($lastOrders[$productId] ?? []) : [];
    $safeProductName = $product ? strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product')) : 'Missing POS product';
    $inputId = 'qty-'.$productId;
@endphp
<tr class="qty-row {{ $currentQty > 0 ? 'bg-indigo-50' : '' }} {{ $orderable ? '' : 'opacity-60' }}">
    {{-- Image --}}
    <td class="px-4 py-3 align-middle">
        @if($product)
            <x-product-image :product="$product" :supplierService="null" :size="$imageSize" fit="contain" :hover="true" />
        @else
            <div class="{{ $imageSize === 'xl' ? 'w-24 h-24' : 'w-16 h-16' }} bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                <svg class="w-1/2 h-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        @endif
    </td>

    {{-- Product --}}
    <td class="px-4 py-3 align-middle">
        <div class="font-semibold text-gray-900">{{ $safeProductName }}</div>
        @if($product)
            <div class="text-xs text-gray-500 mt-0.5">
                {{ $product->CODE ?? 'N/A' }}
                @if(isset($row['stock']))
                    &middot; shop stock {{ rtrim(rtrim(number_format((float) $row['stock'], 2), '0'), '.') }}
                @endif
            </div>
        @else
            <div class="text-xs text-gray-500 mt-0.5 font-mono">{{ $row['kitchen_product']->product_id ?? '' }}</div>
        @endif
        @if($product && $supplierCode === null)
            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-800">
                no supplier code
            </span>
        @endif
    </td>

    {{-- Supplier code --}}
    <td class="px-4 py-3 align-middle">
        @if($supplierCode !== null)
            <span class="font-mono text-sm text-slate-700">{{ $supplierCode }}</span>
        @else
            <span class="text-gray-400">&mdash;</span>
        @endif
    </td>

    {{-- Case size --}}
    <td class="px-4 py-3 align-middle text-sm text-gray-700 whitespace-nowrap">
        @if($caseUnits > 1)
            {{ $caseUnits }} units/case
        @else
            single
        @endif
    </td>

    {{-- Last 3 orders --}}
    @if($showHistory)
        <td class="px-4 py-3 align-middle">
            @if(count($history))
                <div class="flex flex-wrap gap-1">
                    @foreach($history as $h)
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs whitespace-nowrap"
                              title="{{ $h['date']->format('D d M Y H:i') }}">
                            {{ $h['quantity'] }} &times; {{ $h['date']->format('d M') }}
                        </span>
                    @endforeach
                </div>
            @else
                <span class="text-gray-400">&mdash;</span>
            @endif
        </td>
    @endif

    {{-- Qty (cases) --}}
    <td class="px-4 py-3 align-middle">
        @if($orderable && $productId !== null)
            <div class="flex items-center justify-center gap-1">
                <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                        type="button"
                        data-target="{{ $inputId }}"
                        aria-label="Decrease">&minus;</button>
                <input type="number"
                       class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1"
                       id="{{ $inputId }}"
                       name="qty[{{ $productId }}]"
                       min="0"
                       max="999"
                       step="1"
                       value="{{ $currentQty }}">
                <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                        type="button"
                        data-target="{{ $inputId }}"
                        aria-label="Increase">+</button>
            </div>
            @if($standingQty > 0)
                <div class="mt-1 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-100 text-indigo-800"
                          title="Standing weekly quantity: {{ $standingQty }}">
                        standing
                    </span>
                </div>
            @endif
        @else
            <div class="text-center text-xs text-gray-400">not orderable</div>
        @endif
    </td>
</tr>
