<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ strip_tags(html_entity_decode($product->NAME)) }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Product Code: {{ $product->CODE }} 
                    @if($product->REFERENCE)
                        • REF: {{ $product->REFERENCE }}
                    @endif
                    • Category: {{ $product->category->NAME ?? 'Unknown' }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('fruit-veg.availability') }}" 
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    ← Back to Till Visibility
                </a>
                @if($product->is_available)
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Visible on Till</span>
                @else
                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Hidden from Till</span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Success/Error Messages -->
            <div id="alert-container" class="mb-6"></div>

            <x-tab-group :tabs="[
                ['id' => 'overview', 'label' => 'Overview'],
                ['id' => 'pricing', 'label' => 'Pricing & Details'],
                ['id' => 'sales', 'label' => 'Sales History']
            ]">
                
                <x-slot name="overview">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <!-- Product Image Section -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Product Image</h3>
                                
                                <!-- Current Image Display -->
                                <div class="aspect-square bg-gray-100 rounded-lg mb-4 overflow-hidden">
                                    <img id="current-image" 
                                         src="{{ route('fruit-veg.product-image', $product->CODE) }}" 
                                         alt="{{ $product->NAME }}"
                                         class="w-full h-full object-cover">
                                </div>

                                <!-- Image Upload Form -->
                                <form id="image-upload-form" enctype="multipart/form-data">
                                    @csrf
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Upload New Image
                                        </label>
                                        <input type="file" 
                                               id="image-input" 
                                               name="image" 
                                               accept="image/*"
                                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                                    </div>
                                    <button type="submit" 
                                            id="upload-btn"
                                            class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                                        Upload Image
                                    </button>
                                </form>

                                <!-- Image Preview -->
                                <div id="image-preview" class="mt-4 hidden">
                                    <p class="text-sm text-gray-600 mb-2">Preview:</p>
                                    <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden">
                                        <img id="preview-img" class="w-full h-full object-cover">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Details Section -->
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- Basic Info Card -->
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Product Information</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Product Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Product Name
                                        </label>
                                        <input type="text" 
                                               value="{{ $product->NAME }}" 
                                               readonly
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                                        <p class="text-xs text-gray-500 mt-1">Cannot be edited (POS system)</p>
                                    </div>

                                    <!-- Display Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Display Name
                                        </label>
                                        <input type="text" 
                                               id="display-name"
                                               value="{{ $product->DISPLAY ?: '' }}" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500"
                                               placeholder="Custom display name (optional)">
                                        <p class="text-xs text-gray-500 mt-1">Used on labels and displays</p>
                                    </div>

                                    <!-- Country of Origin -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Country of Origin
                                        </label>
                                        <select id="country-select" 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500">
                                            <option value="">Select Country</option>
                                            @foreach($countries as $country)
                                            <option value="{{ $country->ID }}" 
                                                    {{ ($product->vegDetails && $product->vegDetails->countryCode == $country->ID) ? 'selected' : '' }}>
                                                {{ $country->country }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Required for organic certification</p>
                                    </div>

                                    <!-- Current Price -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Price (€)
                                        </label>
                                        <input type="number" 
                                               id="current-price"
                                               value="{{ $product->current_price }}" 
                                               step="0.01"
                                               min="0"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500">
                                        <p class="text-xs text-gray-500 mt-1">Price including VAT</p>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-6 flex space-x-4">
                                    <button onclick="saveChanges()" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                        Save Changes
                                    </button>
                                    
                                    <button onclick="toggleAvailability()" 
                                            class="px-4 py-2 rounded-lg transition {{ $product->is_available ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white' }}">
                                        {{ $product->is_available ? 'Hide from Till' : 'Show on Till' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Display Name Preview -->
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Display Name Preview</h3>
                                <div class="bg-gray-50 border rounded-lg p-4">
                                    <p class="text-sm text-gray-600 mb-2">How it will appear on labels:</p>
                                    <div id="display-preview" class="text-lg font-medium">
                                        @if($product->DISPLAY)
                                            {!! nl2br(html_entity_decode($product->DISPLAY)) !!}
                                        @else
                                            {{ strip_tags(html_entity_decode($product->NAME)) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="pricing">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        
                        <!-- Price History -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Price History</h3>
                            
                            @if($priceHistory->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Old Price</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Price</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($priceHistory as $change)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ \Carbon\Carbon::parse($change->changed_at)->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                €{{ number_format($change->old_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                €{{ number_format($change->new_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @php
                                                    $diff = $change->new_price - $change->old_price;
                                                    $percent = $change->old_price > 0 ? ($diff / $change->old_price) * 100 : 0;
                                                @endphp
                                                <span class="{{ $diff > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                    {{ $diff > 0 ? '+' : '' }}€{{ number_format($diff, 2) }}
                                                    ({{ $diff > 0 ? '+' : '' }}{{ number_format($percent, 1) }}%)
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-gray-500 text-center py-8">No price changes recorded</p>
                            @endif
                        </div>

                        <!-- Product Details -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Product Details</h3>
                            
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Product Code</dt>
                                    <dd class="text-sm text-gray-900">{{ $product->CODE }}</dd>
                                </div>
                                
                                @if($product->REFERENCE)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Reference</dt>
                                    <dd class="text-sm text-gray-900">{{ $product->REFERENCE }}</dd>
                                </div>
                                @endif
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Category</dt>
                                    <dd class="text-sm text-gray-900">{{ $product->category->NAME ?? 'Unknown' }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">VAT Rate</dt>
                                    <dd class="text-sm text-gray-900">{{ $product->TAXCAT ?? '0' }}%</dd>
                                </div>
                                
                                @if($product->vegDetails)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Unit</dt>
                                    <dd class="text-sm text-gray-900">{{ $product->vegDetails->unit_name ?? 'Each' }}</dd>
                                </div>
                                @endif
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Till Visibility</dt>
                                    <dd class="text-sm">
                                        @if($product->is_available)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Visible on Till
                                        </span>
                                        @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Hidden from Till
                                        </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="sales">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Product Info Header -->
                        <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ strip_tags(html_entity_decode($product->NAME)) }}</h2>
                            <div class="flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400">
                                <span>Code: {{ $product->CODE }}</span>
                                <span>Current Price: <strong class="text-gray-900 dark:text-gray-100">€{{ number_format($product->current_price, 2) }}</strong></span>
                                <span>Category: <strong class="text-gray-900 dark:text-gray-100">{{ $product->category->NAME ?? 'Unknown' }}</strong></span>
                            </div>
                        </div>

                        <!-- Time Period Selection -->
                        @php
                            $timePeriodButtons = [
                                [
                                    'label' => 'Last 4 Months',
                                    'action' => 'onclick=loadSalesData(4)',
                                    'type' => 'button',
                                    'color' => 'indigo',
                                    'class' => 'period-btn active',
                                ],
                                [
                                    'label' => 'Last 6 Months',
                                    'action' => 'onclick=loadSalesData(6)',
                                    'type' => 'button',
                                    'color' => 'secondary',
                                    'class' => 'period-btn',
                                ],
                                [
                                    'label' => 'Last 12 Months',
                                    'action' => 'onclick=loadSalesData(12)',
                                    'type' => 'button',
                                    'color' => 'secondary',
                                    'class' => 'period-btn',
                                ],
                                [
                                    'label' => 'Year to Date',
                                    'action' => 'onclick=loadSalesData(\'ytd\')',
                                    'type' => 'button',
                                    'color' => 'secondary',
                                    'class' => 'period-btn',
                                ]
                            ];
                        @endphp
                        <div class="mb-6" id="timePeriodButtons">
                            <x-action-buttons :buttons="$timePeriodButtons" spacing="compact" />
                        </div>

                        <!-- Chart Container -->
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-6">
                            <div class="relative" style="height: 300px;">
                                <canvas id="salesChart"></canvas>
                                <div id="chartLoading" class="hidden absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 flex items-center justify-center">
                                    <div class="text-center">
                                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading sales data...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(isset($salesHistory) && count($salesHistory) > 0)
                            <!-- Sales Statistics Cards -->
                            @if(isset($salesStats))
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sales (12m)</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="total_sales_12m">{{ number_format($salesStats['total_sales_12m'], 0) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Monthly</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="avg_monthly_sales">{{ number_format($salesStats['avg_monthly_sales'], 1) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="this_month_sales">{{ number_format($salesStats['this_month_sales'], 0) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Trend</div>
                                        <div class="mt-1 text-2xl font-semibold" data-stat="trend">
                                            @if($salesStats['trend'] === 'up')
                                                <span class="text-green-600 dark:text-green-400">↑ Up</span>
                                            @elseif($salesStats['trend'] === 'down')
                                                <span class="text-red-600 dark:text-red-400">↓ Down</span>
                                            @else
                                                <span class="text-gray-600 dark:text-gray-400">→ Stable</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Sales by Month Table -->
                            <h3 class="text-lg font-semibold mb-4">Sales by Month</h3>
                            <div class="overflow-x-auto">
                                <table id="salesTable" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Month</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @php
                                            $previousUnits = null;
                                        @endphp
                                        @foreach($salesHistory as $monthData)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $monthData['month'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                    {{ number_format($monthData['units'], 1) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    @if($previousUnits !== null)
                                                        @if($monthData['units'] > $previousUnits)
                                                            <span class="text-green-600 dark:text-green-400">↑ {{ number_format((($monthData['units'] - $previousUnits) / max($previousUnits, 1)) * 100, 1) }}%</span>
                                                        @elseif($monthData['units'] < $previousUnits)
                                                            <span class="text-red-600 dark:text-red-400">↓ {{ number_format((($previousUnits - $monthData['units']) / max($previousUnits, 1)) * 100, 1) }}%</span>
                                                        @else
                                                            <span class="text-gray-600 dark:text-gray-400">→ 0%</span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @php
                                                $previousUnits = $monthData['units'];
                                            @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No sales data</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This product has no sales history.</p>
                            </div>
                        @endif
                    </div>
                </x-slot>
            </x-tab-group>
        </div>
    </div>

    <script>
        // Live Display Name Preview
        document.getElementById('display-name').addEventListener('input', function() {
            const value = this.value.trim();
            const preview = document.getElementById('display-preview');
            
            if (value) {
                // Convert HTML entities and newlines to proper HTML
                let htmlValue = value
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'");
                
                // Convert <br> tags and newlines to line breaks
                htmlValue = htmlValue
                    .replace(/\n/g, '<br>')
                    .replace(/<br\s*\/?>/gi, '<br>');
                
                preview.innerHTML = htmlValue;
            } else {
                preview.textContent = '{{ strip_tags(html_entity_decode($product->NAME)) }}';
            }
        });

        // Image Upload Preview
        document.getElementById('image-input').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('image-preview').classList.add('hidden');
            }
        });

        // Image Upload Form Submission
        document.getElementById('image-upload-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('upload-btn');
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            fetch('{{ route("fruit-veg.product.update-image", $product->CODE) }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Image updated successfully!', 'success');
                    // Refresh current image
                    document.getElementById('current-image').src = '{{ route("fruit-veg.product-image", $product->CODE) }}?' + new Date().getTime();
                    // Hide preview
                    document.getElementById('image-preview').classList.add('hidden');
                    // Reset form
                    this.reset();
                } else {
                    showAlert('Failed to update image.', 'error');
                }
            })
            .catch(error => {
                showAlert('Error uploading image.', 'error');
            })
            .finally(() => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload Image';
            });
        });

        // Save Changes Function
        function saveChanges() {
            const displayName = document.getElementById('display-name').value;
            const countryId = document.getElementById('country-select').value;
            const currentPrice = document.getElementById('current-price').value;
            
            // Update display name
            if (displayName !== '{{ $product->DISPLAY ?: '' }}') {
                updateDisplay(displayName);
            }
            
            // Update country
            if (countryId !== '{{ $product->vegDetails ? $product->vegDetails->countryCode : '' }}') {
                updateCountry(countryId);
            }
            
            // Update price
            if (parseFloat(currentPrice) !== parseFloat('{{ $product->current_price }}')) {
                updatePrice(currentPrice);
            }
        }

        // Update Display Name
        function updateDisplay(displayName) {
            fetch('{{ route("fruit-veg.display.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    product_code: '{{ $product->CODE }}',
                    display: displayName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Display name updated successfully!', 'success');
                } else {
                    showAlert('Failed to update display name.', 'error');
                }
            });
        }

        // Update Country
        function updateCountry(countryId) {
            if (!countryId) return;
            
            fetch('{{ route("fruit-veg.country.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    product_code: '{{ $product->CODE }}',
                    country_id: parseInt(countryId)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Country updated successfully!', 'success');
                } else {
                    showAlert('Failed to update country.', 'error');
                }
            });
        }

        // Update Price
        function updatePrice(newPrice) {
            fetch('{{ route("fruit-veg.prices.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    product_code: '{{ $product->CODE }}',
                    new_price: parseFloat(newPrice)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Price updated successfully!', 'success');
                    // Reload page to show updated price history
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('Failed to update price.', 'error');
                }
            });
        }

        // Toggle Till Visibility
        function toggleAvailability() {
            const isCurrentlyAvailable = {{ $product->is_available ? 'true' : 'false' }};
            
            fetch('{{ route("fruit-veg.availability.toggle") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    product_code: '{{ $product->CODE }}',
                    is_available: !isCurrentlyAvailable
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Till visibility updated successfully!', 'success');
                    // Reload page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('Failed to update till visibility.', 'error');
                }
            });
        }

        // Show Alert Function
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200';
            
            alertContainer.innerHTML = `
                <div class="${alertClass} border rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            ${type === 'success' ? 
                                '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' :
                                '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
                            }
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">${message}</p>
                        </div>
                    </div>
                </div>
            `;
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Sales Chart and Data Management
        let salesChart = null;
        const productCode = '{{ $product->CODE }}';
        
        // Initialize chart with existing data
        document.addEventListener('DOMContentLoaded', function() {
            @if(isset($salesHistory) && count($salesHistory) > 0)
                const initialData = @json(array_values($salesHistory));
                createChart(initialData);
            @endif
        });

        function createChart(salesData) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (salesChart) {
                salesChart.destroy();
            }
            
            // Prepare data
            const labels = salesData.map(item => item.month_short + ' ' + item.year);
            const data = salesData.map(item => item.units);
            
            // Create gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0.1)');
            
            salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Units Sold',
                        data: data,
                        backgroundColor: gradient,
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Units Sold: ' + context.parsed.y.toFixed(1);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(156, 163, 175, 0.1)'
                            },
                            ticks: {
                                color: 'rgb(107, 114, 128)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'rgb(107, 114, 128)'
                            }
                        }
                    },
                    animation: {
                        duration: 750,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        function loadSalesData(period) {
            // Update button states
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white');
                btn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
            });
            event.target.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
            event.target.classList.add('active', 'bg-indigo-600', 'text-white');
            
            // Show loading
            document.getElementById('chartLoading').classList.remove('hidden');
            
            // Fetch data
            fetch(`/fruit-veg/product/${productCode}/sales-data?period=${period}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                createChart(data.salesHistory);
                updateStatistics(data.salesStats);
                updateTable(data.salesHistory);
                document.getElementById('chartLoading').classList.add('hidden');
            })
            .catch(error => {
                console.error('Error loading sales data:', error);
                document.getElementById('chartLoading').classList.add('hidden');
                alert('Failed to load sales data. Please try again.');
            });
        }

        function updateStatistics(stats) {
            // Update statistics cards if they exist
            const statsElements = {
                'total_sales_12m': document.querySelector('[data-stat="total_sales_12m"]'),
                'avg_monthly_sales': document.querySelector('[data-stat="avg_monthly_sales"]'),
                'this_month_sales': document.querySelector('[data-stat="this_month_sales"]'),
                'trend': document.querySelector('[data-stat="trend"]')
            };
            
            if (statsElements.total_sales_12m) {
                statsElements.total_sales_12m.textContent = stats.total_sales_12m.toLocaleString();
            }
            if (statsElements.avg_monthly_sales) {
                statsElements.avg_monthly_sales.textContent = stats.avg_monthly_sales.toFixed(1);
            }
            if (statsElements.this_month_sales) {
                statsElements.this_month_sales.textContent = stats.this_month_sales.toLocaleString();
            }
            if (statsElements.trend) {
                const trendHtml = stats.trend === 'up' 
                    ? '<span class="text-green-600 dark:text-green-400">↑ Up</span>'
                    : stats.trend === 'down'
                    ? '<span class="text-red-600 dark:text-red-400">↓ Down</span>'
                    : '<span class="text-gray-600 dark:text-gray-400">→ Stable</span>';
                statsElements.trend.innerHTML = trendHtml;
            }
        }

        function updateTable(salesData) {
            // Update the sales table
            const tbody = document.querySelector('#salesTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            let previousUnits = null;
            
            salesData.forEach(monthData => {
                const row = document.createElement('tr');
                
                // Month cell
                const monthCell = document.createElement('td');
                monthCell.className = 'px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100';
                monthCell.textContent = monthData.month;
                row.appendChild(monthCell);
                
                // Units cell
                const unitsCell = document.createElement('td');
                unitsCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100';
                unitsCell.textContent = monthData.units.toFixed(1);
                row.appendChild(unitsCell);
                
                // Trend cell
                const trendCell = document.createElement('td');
                trendCell.className = 'px-6 py-4 whitespace-nowrap text-sm';
                
                if (previousUnits !== null) {
                    if (monthData.units > previousUnits) {
                        const percent = ((monthData.units - previousUnits) / Math.max(previousUnits, 1)) * 100;
                        trendCell.innerHTML = `<span class="text-green-600 dark:text-green-400">↑ ${percent.toFixed(1)}%</span>`;
                    } else if (monthData.units < previousUnits) {
                        const percent = ((previousUnits - monthData.units) / Math.max(previousUnits, 1)) * 100;
                        trendCell.innerHTML = `<span class="text-red-600 dark:text-red-400">↓ ${percent.toFixed(1)}%</span>`;
                    } else {
                        trendCell.innerHTML = '<span class="text-gray-600 dark:text-gray-400">→ 0%</span>';
                    }
                } else {
                    trendCell.innerHTML = '<span class="text-gray-400 dark:text-gray-500">-</span>';
                }
                
                row.appendChild(trendCell);
                tbody.appendChild(row);
                
                previousUnits = monthData.units;
            });
        }
    </script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</x-admin-layout>