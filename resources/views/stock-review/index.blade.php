<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
                Stock Check Review
            </h2>
            <button onclick="document.dispatchEvent(new CustomEvent('toggle-history'))" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                History
            </button>
        </div>
    </x-slot>

    <div class="py-2 sm:py-4" x-data="stockPage()">
        <div class="max-w-7xl mx-auto px-1 sm:px-4 lg:px-6">
            {{-- Success/Error Messages --}}
            @if(session('success'))
                <div class="mb-3 bg-green-50 border border-green-200 text-green-800 px-3 py-2 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ===== CONTROLS: shown once a category is selected (review view) ===== --}}
            @unless($overview)
            <div class="bg-white shadow-sm sm:rounded-lg mb-3 overflow-hidden">
                {{-- Mobile: compact header showing category + toggle --}}
                <div class="md:hidden">
                    <button @click="filtersOpen = !filtersOpen" class="w-full flex items-center justify-between px-3 py-2.5">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            @if($selectedCategory)
                                @php $selectedCatName = $categories->firstWhere('ID', $selectedCategory)?->NAME ?? 'Unknown'; @endphp
                                <span class="text-sm font-medium text-gray-900 truncate">{{ $selectedCatName }}</span>
                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($referenceDate)->format('d/m') }}</span>
                            @else
                                <span class="text-sm text-gray-500">Select category...</span>
                            @endif
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="filtersOpen" x-transition x-cloak class="border-t border-gray-100 px-3 pb-3 pt-2">
                        <form method="GET" action="{{ route('stock-review.index') }}" class="grid grid-cols-2 gap-2 items-end">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                                <select name="category" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" onchange="this.form.submit()">
                                    <option value="">-- Select --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->ID }}" {{ $selectedCategory === $cat->ID ? 'selected' : '' }}>{{ $cat->NAME }} ({{ $cat->products_count }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                                <input type="date" name="reference_date" value="{{ $referenceDate }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Show</label>
                                <select name="filter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All</option>
                                    <option value="stocked" {{ $filter === 'stocked' ? 'selected' : '' }}>Stocked</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Sort</label>
                                <select name="sort" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>Name</option>
                                    <option value="checked_desc" {{ $sortBy === 'checked_desc' ? 'selected' : '' }}>Newest</option>
                                    <option value="checked_asc" {{ $sortBy === 'checked_asc' ? 'selected' : '' }}>Oldest</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 text-sm font-medium">Review</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Desktop: always visible --}}
                <div class="hidden md:block p-4">
                    <form method="GET" action="{{ route('stock-review.index') }}" class="grid grid-cols-5 gap-3 items-end">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category" id="category" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" onchange="this.form.submit()">
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->ID }}" {{ $selectedCategory === $cat->ID ? 'selected' : '' }}>{{ $cat->NAME }} ({{ $cat->products_count }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="reference_date" class="block text-sm font-medium text-gray-700 mb-1">Reference Date</label>
                            <input type="date" name="reference_date" id="reference_date" value="{{ $referenceDate }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        </div>
                        <div>
                            <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Show</label>
                            <select name="filter" id="filter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Products</option>
                                <option value="stocked" {{ $filter === 'stocked' ? 'selected' : '' }}>Stocked Only</option>
                            </select>
                        </div>
                        <div>
                            <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select name="sort" id="sort" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>Product Name</option>
                                <option value="checked_desc" {{ $sortBy === 'checked_desc' ? 'selected' : '' }}>Checked (Newest)</option>
                                <option value="checked_asc" {{ $sortBy === 'checked_asc' ? 'selected' : '' }}>Checked (Oldest)</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm font-medium">Review</button>
                        </div>
                    </form>
                </div>
            </div>

            @endunless

            {{-- ===== OVERVIEW LANDING (no category selected) ===== --}}
            @if($overview)
                @php
                    $included = $overview['included'];
                    $excluded = $overview['excluded'];
                @endphp

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden mb-3">
                    <div class="px-3 sm:px-4 py-2.5 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">Categories to review</h3>
                        <span class="text-xs text-gray-400">Oldest checked first</span>
                    </div>

                    @if($included->isEmpty())
                        <div class="px-4 py-6 text-center text-sm text-gray-400">No categories to review.</div>
                    @else
                        <ul class="divide-y divide-gray-100" id="included-list">
                            @foreach($included as $cat)
                                <li class="flex items-center gap-3 px-3 sm:px-4 py-2.5 hover:bg-gray-50" data-category-id="{{ $cat->id }}">
                                    <a href="{{ route('stock-review.index', ['category' => $cat->id]) }}" class="flex-1 min-w-0 flex items-center gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 truncate">{{ $cat->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $cat->product_count }} products</div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            @if($cat->last_checked)
                                                <div class="text-xs text-gray-600">{{ $cat->last_checked_human }}</div>
                                                <div class="text-[11px] text-gray-400">{{ $cat->last_checked->format('d/m/Y') }}</div>
                                            @else
                                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700">Never checked</span>
                                            @endif
                                        </div>
                                    </a>
                                    @if($canToggle)
                                        <button type="button" title="Exclude from list"
                                                onclick="toggleStockCategory('{{ $cat->id }}', this)"
                                                class="flex-shrink-0 text-gray-300 hover:text-red-500 p-1 rounded">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Excluded categories (muted, collapsible) --}}
                @if($excluded->isNotEmpty() || $canToggle)
                    <div class="bg-gray-50 border border-gray-200 sm:rounded-lg overflow-hidden mb-3" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-3 sm:px-4 py-2.5 text-left">
                            <span class="text-sm font-medium text-gray-500">Excluded categories ({{ $excluded->count() }})</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition x-cloak class="border-t border-gray-200">
                            @if($excluded->isEmpty())
                                <div class="px-4 py-4 text-center text-xs text-gray-400">No excluded categories.</div>
                            @else
                                <ul class="divide-y divide-gray-100" id="excluded-list">
                                    @foreach($excluded as $cat)
                                        <li class="flex items-center gap-3 px-3 sm:px-4 py-2 hover:bg-white" data-category-id="{{ $cat->id }}">
                                            <a href="{{ route('stock-review.index', ['category' => $cat->id]) }}" class="flex-1 min-w-0 flex items-center gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm text-gray-600 truncate">{{ $cat->name }}</div>
                                                    <div class="text-xs text-gray-400">{{ $cat->product_count }} products</div>
                                                </div>
                                                <div class="text-xs text-gray-400 flex-shrink-0">{{ $cat->last_checked_human }}</div>
                                            </a>
                                            @if($canToggle)
                                                <button type="button" title="Add back to list"
                                                        onclick="toggleStockCategory('{{ $cat->id }}', this)"
                                                        class="flex-shrink-0 text-gray-300 hover:text-green-600 p-1 rounded">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                @if($canToggle)
                    <script>
                        function toggleStockCategory(categoryId, btn) {
                            btn.disabled = true;
                            fetch('{{ route('stock-review.toggle-category') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ category: categoryId }),
                            }).then(function (res) {
                                if (!res.ok) throw new Error('Toggle failed');
                                // Re-partition on the server for a consistent, correctly-sorted list.
                                window.location.reload();
                            }).catch(function () {
                                btn.disabled = false;
                                alert('Could not update the list. Please try again.');
                            });
                        }
                    </script>
                @endif
            @endif

            @if($reviewData)
                @php
                    $summary = $reviewData['summary'];
                    $products = $reviewData['products'];
                    $dangerProducts = $products->where('status', 'danger');
                    $warningProducts = $products->where('status', 'warning');
                    $needsAttention = $products->whereIn('status', ['danger', 'warning']);
                    $verified = $products->whereIn('status', ['verified', 'ok']);
                @endphp

                {{-- Back to overview --}}
                <a href="{{ route('stock-review.index') }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 mb-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    All categories
                </a>

                {{-- Summary Cards + Scan Button --}}
                <div class="grid grid-cols-4 gap-2 sm:gap-3 mb-3">
                    <div class="bg-white shadow-sm rounded-lg p-2.5 sm:p-4 text-center sm:text-left">
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-900">{{ $summary['total_products'] }}</div>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg p-2.5 sm:p-4 text-center sm:text-left">
                        <div class="text-xs text-gray-500">Checked</div>
                        <div class="text-lg sm:text-2xl font-bold text-green-600">{{ $summary['checked_count'] }}</div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $summary['progress_percentage'] }}%"></div>
                        </div>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg p-2.5 sm:p-4 text-center sm:text-left {{ $summary['unchecked_with_stock'] > 0 ? 'ring-2 ring-red-300' : '' }}">
                        <div class="text-xs text-gray-500">Needs Check</div>
                        <div class="text-lg sm:text-2xl font-bold {{ $summary['unchecked_with_stock'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ $summary['unchecked_with_stock'] + $summary['negative_stock_count'] }}
                        </div>
                    </div>

                    {{-- Scan button as 4th card --}}
                    <button @click="openScanner()"
                            class="bg-blue-600 hover:bg-blue-700 shadow-sm rounded-lg p-2.5 sm:p-4 text-white flex flex-col items-center justify-center gap-1 touch-manipulation">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium">Scan</span>
                    </button>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="stockReview()">

                    {{-- ===== MOBILE CARD LAYOUT ===== --}}
                    <div class="md:hidden">
                        {{-- Needs attention section --}}
                        @if($needsAttention->count() > 0)
                            <div class="px-3 py-2 bg-red-50 border-b border-red-200 flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <span class="text-xs font-semibold text-red-700 uppercase">Needs Attention ({{ $needsAttention->count() }})</span>
                            </div>
                        @endif

                        <div class="divide-y divide-gray-200">
                        @foreach($products as $index => $item)
                            @php
                                $borderColor = match($item->status) {
                                    'danger' => 'border-l-red-500 bg-red-50/50',
                                    'warning' => 'border-l-yellow-500 bg-yellow-50/50',
                                    'ok' => 'border-l-green-500 bg-green-50/30',
                                    default => 'border-l-gray-200',
                                };
                                // Show section divider when transitioning from attention items to verified
                                $showVerifiedDivider = $index > 0 && in_array($item->status, ['verified', 'ok']) && in_array($products[$index - 1]->status ?? '', ['danger', 'warning']);
                            @endphp

                            @if($showVerifiedDivider)
                                <div class="px-3 py-2 bg-green-50 border-b border-green-200 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span class="text-xs font-semibold text-green-700 uppercase">Verified / Clear ({{ $verified->count() }})</span>
                                </div>
                            @endif

                            <div class="border-l-4 {{ $borderColor }}" x-data="{ expanded: false }">
                                <button @click="expanded = !expanded" class="w-full text-left px-3 py-2.5 flex items-center gap-2">
                                    @if($item->image_url)
                                        <img src="{{ $item->image_url }}" alt="" class="w-10 h-10 object-cover rounded border border-gray-200 flex-shrink-0 cursor-pointer" loading="lazy" data-img-url="{{ $item->image_url }}" data-img-name="{{ $item->product->NAME }}" @click.stop="$dispatch('open-image', { url: $el.dataset.imgUrl, name: $el.dataset.imgName })" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="w-10 h-10 bg-gray-100 rounded border border-gray-200 items-center justify-center flex-shrink-0" style="display:none">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @else
                                        <div class="w-10 h-10 bg-gray-100 rounded border border-gray-200 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ $item->product->NAME }}</div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            @if($item->checked_date) {{ $item->checked_date->format('d/m/y') }} @else Never @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="text-lg font-bold {{ $item->stock < 0 ? 'text-yellow-700' : ($item->stock > 0 ? 'text-gray-900' : 'text-gray-400') }}">{{ number_format($item->stock, 0) }}</span>
                                        @if($item->status === 'verified')
                                            <span class="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center"><svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>
                                        @elseif($item->status === 'danger')
                                            <span class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center"><svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></span>
                                        @elseif($item->status === 'warning')
                                            <span class="w-5 h-5 rounded-full bg-yellow-100 flex items-center justify-center"><svg class="w-3 h-3 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg></span>
                                        @endif
                                    </div>
                                </button>
                                <div x-show="expanded" x-transition x-cloak class="px-3 pb-2.5 grid grid-cols-2 gap-x-4 gap-y-1 text-xs border-t border-gray-100 pt-2 ml-12">
                                    <div><span class="text-gray-400">Barcode:</span> <span class="text-gray-700 font-mono">{{ $item->product->CODE }}</span></div>
                                    <div><span class="text-gray-400">Cost:</span> <span class="text-gray-700">&euro;{{ number_format($item->product->PRICEBUY, 2) }}</span></div>
                                    @if($item->supplier_name)<div><span class="text-gray-400">Supplier:</span> <span class="text-gray-700">{{ $item->supplier_name }}</span></div>@endif
                                    <div><span class="text-gray-400">Value:</span> <span class="text-gray-700">&euro;{{ number_format($item->cost_value, 2) }}</span></div>
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>

                    {{-- ===== DESKTOP TABLE ===== --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Checked</th>
                                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <template x-if="salesLoaded">
                                        <template x-for="month in salesMonths" :key="month.key">
                                            <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase" x-text="month.label"></th>
                                        </template>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($products as $index => $item)
                                    @php
                                        $rowClass = match($item->status) {
                                            'danger' => 'bg-red-50',
                                            'warning' => 'bg-yellow-50',
                                            'ok' => 'bg-green-50',
                                            default => '',
                                        };
                                    @endphp
                                    <tr class="{{ $rowClass }} hover:bg-opacity-70">
                                        <td class="px-2 py-2">
                                            @if($item->image_url)
                                                <div class="relative w-8 h-8 cursor-pointer" x-data="{ show: false, pos: { x: 0, y: 0 } }" data-img-url="{{ $item->image_url }}" data-img-name="{{ $item->product->NAME }}" @mouseenter="const r=$el.getBoundingClientRect(); pos.x=r.left; pos.y=r.bottom+8; show=true;" @mouseleave="show=false" @click="$event.stopPropagation(); $dispatch('open-image', { url: $el.dataset.imgUrl, name: $el.dataset.imgName })">
                                                    <img src="{{ $item->image_url }}" alt="" class="w-8 h-8 object-cover rounded border border-gray-200" loading="lazy" onerror="this.style.display='none'">
                                                    <template x-teleport="body">
                                                        <div x-show="show" x-transition.opacity class="fixed z-[99999] pointer-events-none" :style="'left:'+pos.x+'px;top:'+pos.y+'px;'">
                                                            <img src="{{ $item->image_url }}" alt="" class="w-48 h-auto max-h-60 object-contain rounded-lg border-2 border-white shadow-2xl bg-white">
                                                        </div>
                                                    </template>
                                                </div>
                                            @else
                                                <div class="w-8 h-8 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-sm font-medium text-gray-900">{{ $item->product->NAME }}@if($item->is_stocked)<span class="inline-block w-2 h-2 bg-blue-500 rounded-full ml-1"></span>@endif</td>
                                        <td class="px-2 py-2 text-sm text-gray-500 font-mono">{{ $item->product->CODE }}</td>
                                        <td class="px-2 py-2 text-sm text-gray-500">{{ $item->supplier_name }}</td>
                                        <td class="px-2 py-2 text-sm text-gray-500 text-right">&euro;{{ number_format($item->product->PRICEBUY, 2) }}</td>
                                        <td class="px-2 py-2 text-sm text-right font-medium {{ $item->stock < 0 ? 'text-yellow-700' : ($item->stock > 0 ? 'text-gray-900' : 'text-gray-400') }}">{{ number_format($item->stock, 1) }}</td>
                                        <td class="px-2 py-2 text-sm text-right {{ $item->cost_value != 0 ? 'text-gray-700' : 'text-gray-400' }}">&euro;{{ number_format($item->cost_value, 2) }}</td>
                                        <td class="px-2 py-2 text-sm text-gray-500">@if($item->checked_date){{ $item->checked_date->format('d M Y') }}@else<span class="text-gray-300">Never</span>@endif</td>
                                        <td class="px-2 py-2 text-center">
                                            @if($item->status === 'verified')<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>OK</span>
                                            @elseif($item->status === 'danger')<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>Check</span>
                                            @elseif($item->status === 'warning')<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>Neg</span>
                                            @else<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Clear</span>
                                            @endif
                                        </td>
                                        <template x-if="salesLoaded">
                                            <template x-for="month in salesMonths" :key="month.key">
                                                <td class="px-2 py-2 text-sm text-right text-gray-500" x-text="getSales('{{ $item->product->CODE }}', month.key)"></td>
                                            </template>
                                        </template>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Action Bar --}}
                    <div class="border-t border-gray-200 px-3 sm:px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="toggleSales()" class="hidden md:inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50" :class="salesLoaded ? 'bg-blue-50 border-blue-300 text-blue-700' : ''">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <span x-text="salesLoading ? 'Loading...' : (salesLoaded ? 'Hide Sales' : 'Show Sales')"></span>
                            </button>
                            <span class="text-xs sm:text-sm text-gray-500">{{ $products->count() }} products | &euro;{{ number_format($summary['total_stock_value'], 2) }}</span>
                        </div>

                        @if($summary['unchecked_with_stock'] > 0 || $summary['negative_stock_count'] > 0)
                            <button @click="showConfirmModal = true" class="inline-flex items-center px-3 sm:px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium touch-manipulation">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Set to Zero ({{ $summary['unchecked_with_stock'] + $summary['negative_stock_count'] }})
                            </button>
                        @else
                            <form method="POST" action="{{ route('stock-review.mark-checked') }}">
                                @csrf
                                <input type="hidden" name="category" value="{{ $selectedCategory }}">
                                <input type="hidden" name="reference_date" value="{{ $referenceDate }}">
                                <button type="submit" class="inline-flex items-center px-3 sm:px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium touch-manipulation">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Mark as Checked
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Confirmation Modal --}}
                    <div x-show="showConfirmModal" x-transition.opacity class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black bg-opacity-50" @click.self="showConfirmModal = false" style="display:none;">
                        <div class="bg-white rounded-t-xl sm:rounded-lg shadow-xl max-w-md w-full mx-0 sm:mx-4 p-5 sm:p-6" @click.stop>
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Confirm Set to Zero</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">Set stock to <strong>0</strong> for <strong class="text-red-600">{{ $summary['unchecked_with_stock'] + $summary['negative_stock_count'] }}</strong> unchecked products since <strong>{{ \Carbon\Carbon::parse($referenceDate)->format('d M Y') }}</strong>.</p>
                            <p class="text-sm text-gray-600 mb-4">Value: <strong class="text-red-600">&euro;{{ number_format(abs($summary['at_risk_value']), 2) }}</strong></p>
                            @if($dangerProducts->count() > 0 || $warningProducts->count() > 0)
                                <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-md mb-4">
                                    <ul class="divide-y divide-gray-100">
                                        @foreach($dangerProducts->merge($warningProducts)->take(15) as $item)
                                            <li class="px-3 py-1.5 text-xs flex justify-between">
                                                <span class="text-gray-700 truncate mr-2">{{ $item->product->NAME }}</span>
                                                <span class="font-mono {{ $item->stock < 0 ? 'text-yellow-600' : 'text-red-600' }} whitespace-nowrap">{{ number_format($item->stock, 1) }}</span>
                                            </li>
                                        @endforeach
                                        @if($dangerProducts->count() + $warningProducts->count() > 15)
                                            <li class="px-3 py-1.5 text-xs text-gray-400 text-center">... and {{ $dangerProducts->count() + $warningProducts->count() - 15 }} more</li>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                            <div class="flex gap-3">
                                <button @click="showConfirmModal = false" class="flex-1 px-4 py-2.5 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 touch-manipulation">Cancel</button>
                                <form method="POST" action="{{ route('stock-review.set-to-zero') }}" class="flex-1">
                                    @csrf
                                    <input type="hidden" name="category" value="{{ $selectedCategory }}">
                                    <input type="hidden" name="reference_date" value="{{ $referenceDate }}">
                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                    <button type="submit" class="w-full px-4 py-2.5 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 font-medium touch-manipulation">Yes, Set to Zero</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($selectedCategory === null)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 sm:p-8 text-center text-gray-500">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <p class="text-base font-medium">Select a category to review</p>
                    <p class="text-sm mt-1">Choose a category above to start reviewing stock checks.</p>
                </div>
            @endif
        </div>

        {{-- ===== SCANNER FULLSCREEN MODAL ===== --}}
        <div x-show="scannerOpen" x-transition.opacity class="fixed inset-0 z-50 bg-gray-900" style="display:none;">
            <div class="h-full flex flex-col">
                {{-- Modal header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gray-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        <span class="text-white font-medium">Stock Check</span>
                        <template x-if="scanner.checksCount > 0">
                            <span class="bg-green-500 text-white text-xs font-medium px-2 py-0.5 rounded-full" x-text="scanner.checksCount + ' done'"></span>
                        </template>
                    </div>
                    <button @click="closeScanner()" class="text-gray-400 hover:text-white p-1 touch-manipulation">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Scanner content --}}
                <div class="flex-1 overflow-y-auto px-4 py-3">
                    <div class="max-w-lg mx-auto">
                        {{-- Barcode input --}}
                        <div class="flex gap-2 mb-3">
                            <input type="text"
                                   x-model="scanner.barcode"
                                   x-ref="scannerInput"
                                   @keydown.enter="scanBarcode()"
                                   placeholder="Scan or type barcode..."
                                   :inputmode="scanner.keyboardEnabled ? 'text' : 'none'"
                                   autocomplete="off"
                                   class="flex-1 text-lg py-3 px-4 rounded-lg border-2 border-gray-600 bg-gray-800 text-white placeholder-gray-500 focus:border-blue-500 focus:ring-blue-500 touch-manipulation">
                            <button @click="scanner.keyboardEnabled = !scanner.keyboardEnabled; $nextTick(() => $refs.scannerInput.focus())"
                                    class="px-3 py-2 rounded-lg border-2 touch-manipulation"
                                    :class="scanner.keyboardEnabled ? 'bg-blue-900 border-blue-500 text-blue-300' : 'bg-gray-800 border-gray-600 text-gray-500'">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm3 4h2m2 0h2m2 0h2m2 0h2M6 12h2m2 0h2m2 0h2m2 0h2M8 16h8"/></svg>
                            </button>
                            <button @click="toggleScannerCamera()"
                                    class="px-3 py-2 rounded-lg border-2 touch-manipulation"
                                    :class="scanner.cameraActive ? 'bg-green-900 border-green-500 text-green-300' : 'bg-gray-800 border-gray-600 text-gray-500'">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Camera view --}}
                        <div x-show="scanner.cameraVisible" x-transition class="mb-3">
                            <div id="stock-check-scanner" class="w-full rounded-lg overflow-hidden" style="min-height: 220px;"></div>
                            <p class="text-xs text-gray-400 mt-1 text-center" x-text="scanner.cameraStatus"></p>
                        </div>

                        {{-- Loading --}}
                        <div x-show="scanner.processing" class="text-center py-6">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-400 mx-auto"></div>
                            <p class="text-gray-400 mt-2 text-sm">Looking up product...</p>
                        </div>

                        {{-- Result --}}
                        <div x-show="scanner.lastResult && !scanner.processing" x-transition class="rounded-lg border-2 p-4 mb-3"
                             :class="scanner.lastResult?.success ? 'border-green-500 bg-green-900/30' : 'border-red-500 bg-red-900/30'">
                            <template x-if="scanner.lastResult?.success">
                                <div>
                                    <div class="flex items-start gap-3">
                                        <template x-if="scanner.lastResult.product?.image_url">
                                            <img :src="scanner.lastResult.product.image_url" class="w-16 h-16 object-cover rounded border border-gray-600 cursor-pointer" @click="$dispatch('open-image', { url: scanner.lastResult.product.image_url, name: scanner.lastResult.product.name })" onerror="this.style.display='none'">
                                        </template>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-white leading-tight" x-text="scanner.lastResult.product?.name"></div>
                                            <div class="text-sm text-gray-400" x-text="scanner.lastResult.product?.code"></div>
                                            <div class="text-xs text-gray-500" x-text="scanner.lastResult.product?.category + (scanner.lastResult.product?.supplier ? ' - ' + scanner.lastResult.product.supplier : '')"></div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="text-3xl font-bold text-white" x-text="Math.floor(scanner.lastResult.stock)"></div>
                                            <div class="text-xs text-gray-400">in stock</div>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <label class="text-sm text-gray-300">Update:</label>
                                        <input type="number" x-model="scanner.stockCount" @keydown.enter="updateScannerStock()" min="0" max="9999" step="1"
                                               class="w-24 text-center py-2 px-2 rounded border border-gray-600 bg-gray-800 text-white focus:border-blue-500 focus:ring-blue-500 text-lg touch-manipulation" placeholder="qty">
                                        <button @click="updateScannerStock()" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 touch-manipulation" :disabled="scanner.stockCount === '' || scanner.stockCount === null">Update</button>
                                        <span x-show="scanner.stockUpdated" x-transition class="text-green-400 text-sm font-medium">Done!</span>
                                    </div>
                                    <div class="mt-2 flex items-center gap-1">
                                        <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        <span class="text-sm text-green-400" x-text="scanner.lastResult.message"></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="scanner.lastResult && !scanner.lastResult.success">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    <span class="text-sm text-red-400" x-text="scanner.lastResult.message"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Scan history --}}
                        <template x-if="scanner.history.length > 0">
                            <div>
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">Recent scans</div>
                                <div class="space-y-1 max-h-48 overflow-y-auto">
                                    <template x-for="(scan, i) in scanner.history" :key="i">
                                        <div class="flex items-center justify-between text-sm py-1.5 px-3 rounded"
                                             :class="scan.success ? 'bg-green-900/30 text-green-300' : 'bg-red-900/30 text-red-300'">
                                            <span class="truncate" x-text="scan.name || scan.barcode"></span>
                                            <span class="text-xs text-gray-500 whitespace-nowrap ml-2" x-text="scan.time"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== IMAGE LIGHTBOX MODAL (independent Alpine component) ===== --}}
    <div x-data="imageLightbox()" @open-image.window="open($event.detail.url, $event.detail.name)" x-show="showing" x-transition.opacity
         class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-80 p-4"
         @click="showing = false" @keydown.escape.window="showing = false"
         style="display:none;">
        <div class="relative max-w-lg w-full" @click.stop>
            <button @click="showing = false" class="absolute -top-10 right-0 text-white hover:text-gray-300 touch-manipulation">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <img :src="url" :alt="name" class="w-full h-auto max-h-[80vh] object-contain rounded-lg bg-white">
            <div x-show="name" class="mt-2 text-center text-white text-sm" x-text="name"></div>
        </div>
    </div>

    {{-- ===== SET TO ZERO HISTORY MODAL (independent Alpine component) ===== --}}
    <div x-data="zeroHistory()" @toggle-history.document="toggle()" x-show="open" x-transition.opacity
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black bg-opacity-50"
         @click="open = false" @keydown.escape.window="open = false"
         style="display:none;">
        <div class="bg-white rounded-t-xl sm:rounded-lg shadow-xl w-full max-w-2xl mx-0 sm:mx-4 max-h-[85vh] flex flex-col" @click.stop>
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h3 class="text-lg font-semibold text-gray-900">Set to Zero History</h3>
                </div>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 touch-manipulation">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto">
                {{-- Loading --}}
                <div x-show="loading" class="py-8 text-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                    <p class="text-gray-500 mt-2 text-sm">Loading history...</p>
                </div>

                {{-- Empty --}}
                <div x-show="!loading && items.length === 0" class="py-8 text-center text-gray-400">
                    <p>No set-to-zero records found.</p>
                </div>

                {{-- History list --}}
                <div x-show="!loading && items.length > 0" class="divide-y divide-gray-100">
                    <template x-for="(item, idx) in items" :key="item.id">
                        <div>
                            <div class="px-4 py-3 flex items-start gap-3 cursor-pointer hover:bg-gray-50"
                                 :class="item.product_details ? 'cursor-pointer' : ''"
                                 @click="item.product_details ? (expanded === item.id ? expanded = null : expanded = item.id) : null">
                                {{-- Date --}}
                                <div class="flex-shrink-0 text-center w-12">
                                    <div class="text-xs text-gray-400" x-text="formatDate(item.date).day"></div>
                                    <div class="text-sm font-semibold text-gray-700" x-text="formatDate(item.date).month"></div>
                                    <div class="text-xs text-gray-400" x-text="formatDate(item.date).year"></div>
                                </div>

                                {{-- Details --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-sm text-gray-900" x-text="item.category_name"></span>
                                        <span class="text-xs px-1.5 py-0.5 rounded-full"
                                              :class="item.source === 'new' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'"
                                              x-text="item.source === 'new' ? 'New' : 'Legacy'"></span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        <span x-text="formatDate(item.date).time"></span>
                                        <template x-if="item.user">
                                            <span> &middot; <span x-text="item.user"></span></span>
                                        </template>
                                    </div>
                                    <template x-if="item.products_zeroed !== null">
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <span x-text="item.products_zeroed"></span> products zeroed
                                            <template x-if="item.total_value">
                                                <span> &middot; &euro;<span x-text="parseFloat(item.total_value).toFixed(2)"></span></span>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                {{-- Expand icon (only for new records with details) --}}
                                <template x-if="item.product_details">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-1 transition-transform" :class="expanded === item.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </template>
                            </div>

                            {{-- Expanded product details --}}
                            <template x-if="item.product_details && expanded === item.id">
                                <div class="px-4 pb-3 ml-15">
                                    <div class="bg-gray-50 rounded-md p-2 max-h-40 overflow-y-auto">
                                        <template x-for="(p, pi) in item.product_details" :key="pi">
                                            <div class="flex justify-between text-xs py-1 border-b border-gray-100 last:border-0">
                                                <span class="text-gray-700 truncate mr-2" x-text="p.name"></span>
                                                <div class="flex gap-3 flex-shrink-0 text-gray-500">
                                                    <span x-text="parseFloat(p.old_stock).toFixed(1)"></span>
                                                    <span class="text-red-600" x-text="'€' + parseFloat(p.cost_value).toFixed(2)"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/barcode-scanner.js'])

    <script>
        function zeroHistory() {
            return {
                open: false,
                loading: false,
                items: [],
                expanded: null,

                async toggle() {
                    this.open = !this.open;
                    if (this.open && this.items.length === 0) {
                        await this.load();
                    }
                },

                async load() {
                    this.loading = true;
                    try {
                        const res = await fetch('{{ route("stock-review.history") }}');
                        this.items = await res.json();
                    } catch (e) {
                        this.items = [];
                    } finally {
                        this.loading = false;
                    }
                },

                formatDate(dateStr) {
                    const d = new Date(dateStr);
                    return {
                        day: d.getDate(),
                        month: d.toLocaleString('en-GB', { month: 'short' }),
                        year: d.getFullYear(),
                        time: d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
                    };
                }
            };
        }

        function imageLightbox() {
            return {
                showing: false,
                url: '',
                name: '',
                open(url, name) {
                    this.url = url;
                    this.name = name || '';
                    this.showing = true;
                }
            };
        }

        function stockPage() {
            return {
                filtersOpen: false,
                scannerOpen: false,
                scanner: {
                    barcode: '',
                    keyboardEnabled: false,
                    processing: false,
                    lastResult: null,
                    checksCount: 0,
                    history: [],
                    stockCount: '',
                    stockUpdated: false,
                    cameraActive: false,
                    cameraVisible: false,
                    cameraStatus: '',
                },

                openScanner() {
                    this.scannerOpen = true;
                    this.$nextTick(() => this.$refs.scannerInput?.focus());
                },


                closeScanner() {
                    this.stopScannerCamera();
                    this.scannerOpen = false;
                },

                async toggleScannerCamera() {
                    if (this.scanner.cameraActive) {
                        await this.stopScannerCamera();
                        return;
                    }
                    if (!window.BarcodeScanner) {
                        this.scanner.cameraStatus = 'Camera not available. Use HTTPS.';
                        return;
                    }
                    this.scanner.cameraVisible = true;
                    this.scanner.cameraStatus = 'Starting camera...';
                    await this.$nextTick();
                    try {
                        await window.BarcodeScanner.startScanner('stock-check-scanner', (text) => this.onScannerDetected(text), () => {});
                        this.scanner.cameraActive = true;
                        this.scanner.cameraStatus = 'Point camera at barcode...';
                    } catch (err) {
                        this.scanner.cameraVisible = false;
                        this.scanner.cameraStatus = 'Camera error: ' + (err?.message || String(err));
                    }
                },

                async stopScannerCamera() {
                    if (window.BarcodeScanner && this.scanner.cameraActive) {
                        await window.BarcodeScanner.stopScanner();
                    }
                    this.scanner.cameraActive = false;
                    this.scanner.cameraVisible = false;
                    this.scanner.cameraStatus = '';
                },

                onScannerDetected(text) {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        osc.frequency.value = 1000;
                        osc.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.1);
                    } catch (e) {}
                    this.scanner.cameraStatus = 'Detected: ' + text;
                    this.stopScannerCamera();
                    this.scanner.barcode = text;
                    this.scanBarcode();
                },

                async scanBarcode() {
                    if (!this.scanner.barcode.trim() || this.scanner.processing) return;
                    this.scanner.processing = true;
                    this.scanner.lastResult = null;
                    this.scanner.stockCount = '';
                    this.scanner.stockUpdated = false;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                    try {
                        const res = await fetch('{{ route("stock-review.stock-check") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({ barcode: this.scanner.barcode.trim() })
                        });
                        const data = await res.json();
                        this.scanner.lastResult = data;
                        const time = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                        if (data.success) {
                            this.scanner.checksCount++;
                            this.scanner.stockCount = Math.floor(data.stock);
                            this.scanner.history.unshift({ success: true, barcode: data.product.code, name: data.product.name, time });
                        } else {
                            this.scanner.history.unshift({ success: false, barcode: this.scanner.barcode, name: data.message || 'Not found', time });
                        }
                        if (this.scanner.history.length > 20) this.scanner.history.pop();
                    } catch (err) {
                        this.scanner.lastResult = { success: false, message: 'Network error' };
                    } finally {
                        this.scanner.processing = false;
                        this.scanner.barcode = '';
                        this.$nextTick(() => this.$refs.scannerInput?.focus());
                    }
                },

                async updateScannerStock() {
                    if (this.scanner.stockCount === '' || this.scanner.stockCount === null || !this.scanner.lastResult?.product) return;
                    this.scanner.stockUpdated = false;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                    try {
                        const res = await fetch('{{ route("stock-review.stock-check") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({ barcode: this.scanner.lastResult.product.code, stock_count: parseFloat(this.scanner.stockCount) })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.scanner.lastResult.stock = data.stock;
                            this.scanner.lastResult.message = data.message;
                            this.scanner.stockUpdated = true;
                            setTimeout(() => this.scanner.stockUpdated = false, 2000);
                        }
                    } catch (err) {
                    } finally {
                        this.$nextTick(() => this.$refs.scannerInput?.focus());
                    }
                }
            };
        }
    </script>

    @if($reviewData)
    <script>
        function stockReview() {
            return {
                showConfirmModal: false,
                salesLoaded: false,
                salesLoading: false,
                salesData: {},
                salesMonths: [],

                toggleSales() {
                    if (this.salesLoaded) { this.salesLoaded = false; return; }
                    if (this.salesLoading) return;
                    this.salesLoading = true;
                    fetch(`{{ route('stock-review.sales-data') }}?category={{ $selectedCategory }}`)
                        .then(r => r.json())
                        .then(data => { this.salesData = data.sales; this.salesMonths = data.months; this.salesLoaded = true; this.salesLoading = false; })
                        .catch(() => { this.salesLoading = false; });
                },

                getSales(barcode, monthKey) {
                    const val = this.salesData[barcode]?.[monthKey];
                    return val ? val.toFixed(0) : '-';
                }
            };
        }
    </script>
    @endif
</x-admin-layout>
