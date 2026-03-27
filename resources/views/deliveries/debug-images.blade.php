<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Debug Images - Delivery #{{ $delivery->id }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Supplier: {{ $delivery->supplier?->Name }} (ID: {{ $delivery->supplier_id }})
                    | Items: {{ $debugData->count() }}
                    | With images: {{ $debugData->where('image_url', '!=', null)->count() }}
                    | Without: {{ $debugData->where('image_url', null)->count() }}
                </p>
            </div>
            <a href="{{ route('deliveries.show', $delivery) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                &larr; Back to Delivery
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Summary + Resolve Button --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold text-lg dark:text-gray-200">Summary</h3>
                    <div x-data="{ resolving: false, result: null }">
                        <button
                            x-on:click="
                                resolving = true; result = null;
                                fetch('{{ route('deliveries.resolve-images', $delivery) }}', {
                                    method: 'POST',
                                    headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
                                })
                                .then(r => r.json())
                                .then(data => { result = data; resolving = false; })
                                .catch(() => { result = {error: true}; resolving = false; })
                            "
                            :disabled="resolving"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 text-sm"
                        >
                            <span x-show="!resolving">Resolve All Images (scrape IIH)</span>
                            <span x-show="resolving">Resolving... (this takes a while)</span>
                        </button>
                        <div x-show="result && !result.error" class="mt-2 text-sm text-green-700">
                            Done! Found: <span x-text="result?.found"></span> | Not found: <span x-text="result?.not_found"></span>
                            <a href="" class="text-blue-600 underline ml-2">Refresh page</a>
                        </div>
                        <div x-show="result?.error" class="mt-2 text-sm text-red-600">Error resolving images</div>
                    </div>
                </div>
                @php
                    $statuses = $debugData->groupBy('image_status')->map->count()->sortDesc();
                @endphp
                <div class="flex flex-wrap gap-3">
                    @foreach($statuses as $status => $count)
                        <span class="px-3 py-1 rounded-full text-sm font-medium
                            {{ $status === 'url generated' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $status }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Items --}}
            @foreach($debugData as $item)
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 {{ $item['image_url'] ? '' : 'border-l-4 border-red-400' }}">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-bold dark:text-gray-200">{{ $item['description'] }}</h4>
                            @if($item['product_name'] && $item['product_name'] !== $item['description'])
                                <div class="text-xs text-gray-500">POS: {{ $item['product_name'] }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($item['supplier_website_link'] ?? null)
                                <a href="{{ $item['supplier_website_link'] }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 underline">View on IIH</a>
                            @endif
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $item['image_status'] === 'url generated' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $item['image_status'] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs mb-3 dark:text-gray-300">
                        <div><strong>Barcode:</strong> <span class="font-mono">{{ $item['barcode'] ?? 'none' }}</span></div>
                        <div><strong>Has Product:</strong> {{ $item['has_product'] ? 'Yes' : 'No' }}</div>
                        <div><strong>Supplier ID:</strong> {{ $item['supplier_id'] ?? 'n/a' }}</div>
                        <div><strong>Is New:</strong> {{ $item['is_new_product'] ? 'Yes' : 'No' }}</div>
                    </div>

                    {{-- Cache Status --}}
                    @if($item['cached'] ?? null)
                        <div class="text-xs mb-3 px-2 py-1 rounded {{ $item['cached']['not_found'] ? 'bg-orange-50 border border-orange-200' : 'bg-green-50 border border-green-200' }}">
                            <strong>Cached</strong> ({{ $item['cached']['updated_at'] }}):
                            @if($item['cached']['not_found'])
                                <span class="text-orange-700">not found on IIH</span>
                            @else
                                <span class="text-green-700">{{ $item['cached']['image_url'] }}</span>
                            @endif
                        </div>
                    @endif

                    {{-- Supplier Links --}}
                    @if(count($item['supplier_links'] ?? []) > 0)
                        <div class="text-xs mb-3 dark:text-gray-300">
                            <strong>Supplier Links:</strong>
                            @foreach($item['supplier_links'] as $link)
                                <span class="inline-block px-2 py-0.5 rounded mr-1 {{ (int)$link['supplier_id'] === (int)$delivery->supplier_id ? 'bg-green-100 text-green-800 font-bold' : 'bg-gray-100 text-gray-600' }}">
                                    SuppID={{ $link['supplier_id'] }} Code={{ $link['supplier_code'] ?? 'NULL' }}
                                </span>
                            @endforeach
                        </div>
                        <div class="text-xs mb-3 dark:text-gray-300">
                            <strong>hasOne returns:</strong>
                            @if($item['default_supplier_link'] ?? null)
                                SuppID={{ $item['default_supplier_link']['supplier_id'] }} Code={{ $item['default_supplier_link']['supplier_code'] ?? 'NULL' }}
                                @if((int)($item['default_supplier_link']['supplier_id'] ?? 0) !== (int)$delivery->supplier_id)
                                    <span class="text-red-600 font-bold">WRONG SUPPLIER!</span>
                                @endif
                            @else
                                <span class="text-red-500">null</span>
                            @endif
                        </div>
                    @endif

                    {{-- Generated URLs --}}
                    @if($item['image_url'])
                        <div class="text-xs mb-2 dark:text-gray-300">
                            <strong>Primary URL:</strong>
                            <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded break-all">{{ $item['image_url'] }}</code>
                        </div>
                    @endif
                    @if(!empty($item['fallback_urls'] ?? []))
                        <div class="text-xs mb-2 dark:text-gray-300">
                            <strong>Fallback URLs:</strong>
                            @foreach($item['fallback_urls'] as $i => $url)
                                <div class="ml-2"><code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded break-all">{{ $i }}: {{ $url }}</code></div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Live image test for all URL variants --}}
                    @if($item['matching_supplier_link'] ?? null)
                        @php
                            $code = $item['matching_supplier_link']['supplier_code'] ?? '';
                            $testUrls = [
                                'files/*.webp' => "https://iihealthfoods.com/cdn/shop/files/{$code}_1.webp?width=533",
                                'files/*.png' => "https://iihealthfoods.com/cdn/shop/files/{$code}_1.png?width=533",
                                'files/*.jpg' => "https://iihealthfoods.com/cdn/shop/files/{$code}_1.jpg?width=533",
                                'products/*.jpg' => "https://iihealthfoods.com/cdn/shop/products/{$code}_1.jpg?width=533",
                            ];
                        @endphp
                        <div class="mt-3 border-t pt-3">
                            <strong class="text-xs dark:text-gray-300">Live URL Test (code: {{ $code }}):</strong>
                            <div class="grid grid-cols-4 gap-3 mt-2">
                                @foreach($testUrls as $label => $url)
                                    <div class="text-center" x-data="{ status: 'loading' }">
                                        <div class="text-xs font-mono mb-1 dark:text-gray-400">{{ $label }}</div>
                                        <div class="w-20 h-20 mx-auto border rounded overflow-hidden bg-gray-50 dark:bg-gray-700 flex items-center justify-center">
                                            <img
                                                src="{{ $url }}"
                                                class="w-full h-full object-cover"
                                                x-on:load="status = 'ok'"
                                                x-on:error="status = 'fail'"
                                            >
                                        </div>
                                        <div class="mt-1 text-xs font-bold"
                                             :class="status === 'ok' ? 'text-green-600' : status === 'fail' ? 'text-red-600' : 'text-gray-400'"
                                             x-text="status"></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

        </div>
    </div>
</x-admin-layout>
