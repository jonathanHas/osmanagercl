@php
    $product = $kitchenProduct->product;
    $supplierData = $supplierInfo[$kitchenProduct->product_id] ?? ['name' => null, 'code' => null];
    $supplierName = $supplierData['name'] ?? null;
    $supplierCode = $supplierData['code'] ?? null;
    $profileId = $profiledProductIds[$kitchenProduct->product_id] ?? null;
    $shopStock = $product?->stockCurrent?->UNITS ?? 0;
@endphp
<tr class="hover:bg-gray-50">
    <td class="px-6 py-4 whitespace-nowrap">
        <div class="font-medium text-gray-900">{{ $product?->NAME ?? 'Unknown Product' }}</div>
        <div class="text-xs text-gray-500">{{ $product?->CODE ?? $kitchenProduct->product_id }}</div>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
        {{ $supplierName ?? '-' }}
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm">
        @if($supplierCode)
            <button type="button"
                    onclick="copyToClipboard('{{ $supplierCode }}', this)"
                    class="group flex items-center gap-1.5 px-2 py-1 -mx-2 -my-1 rounded hover:bg-gray-100 transition-colors cursor-pointer"
                    title="Click to copy">
                <span class="font-mono text-gray-700 group-hover:text-gray-900">{{ $supplierCode }}</span>
                <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </button>
        @else
            <span class="text-gray-400">-</span>
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-center">
        @if($shopStock > 0)
            <span class="text-sm font-medium text-gray-700">{{ number_format($shopStock, 0) }}</span>
        @elseif($shopStock < 0)
            <span class="text-sm font-medium text-red-600">{{ number_format($shopStock, 0) }}</span>
        @else
            <span class="text-sm text-gray-400">0</span>
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-center">
        <span class="text-sm text-gray-400">-</span>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-center">
        @if($profileId)
            <a href="{{ route('kitchen.profiles.edit', $profileId) }}"
               class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Has Profile
            </a>
        @else
            <a href="{{ route('kitchen.profiles.create', ['product_id' => $kitchenProduct->product_id]) }}"
               class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 hover:bg-amber-200">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Profile
            </a>
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
        <form action="{{ route('kitchen.products.destroy', $kitchenProduct) }}" method="POST" class="inline"
              onsubmit="return confirm('Remove this product from the kitchen list?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 hover:text-red-900">Remove</button>
        </form>
    </td>
</tr>
