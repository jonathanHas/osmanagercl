@php
    $product = $kitchenProduct->product;
    $sales = $kitchenSales[$kitchenProduct->product_id] ?? ['avg_weekly' => 0, 'total_6_months' => 0];
    $supplier = $supplierInfo[$kitchenProduct->product_id] ?? null;
    $profileId = $profiledProductIds[$kitchenProduct->product_id] ?? null;
@endphp
<tr class="hover:bg-gray-50">
    <td class="px-6 py-4 whitespace-nowrap">
        <div class="font-medium text-gray-900">{{ $product?->NAME ?? 'Unknown Product' }}</div>
        <div class="text-xs text-gray-500">{{ $product?->CODE ?? $kitchenProduct->product_id }}</div>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
        {{ $supplier ?? '-' }}
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-center">
        @if($sales['avg_weekly'] > 0)
            <span class="text-sm font-medium text-orange-600">{{ number_format($sales['avg_weekly'], 1) }}</span>
        @else
            <span class="text-sm text-gray-400">-</span>
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-center">
        @if($sales['total_6_months'] > 0)
            <span class="text-sm font-medium text-gray-700">{{ number_format($sales['total_6_months'], 0) }}</span>
        @else
            <span class="text-sm text-gray-400">-</span>
        @endif
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
