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
                    ← Back to Availability
                </a>
                @if($product->is_available)
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Available</span>
                @else
                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Not Available</span>
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
                                        {{ $product->is_available ? 'Mark Unavailable' : 'Mark Available' }}
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
                                    <dt class="text-sm font-medium text-gray-500">Availability Status</dt>
                                    <dd class="text-sm">
                                        @if($product->is_available)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Available
                                        </span>
                                        @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Not Available
                                        </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="sales">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Sales Statistics</h3>
                        
                        <!-- Sales Stats Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-lg bg-blue-100">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-blue-600">Total Sold</p>
                                        <p class="text-2xl font-semibold text-blue-900">{{ $salesStats['total_sold'] }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-lg bg-green-100">
                                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-green-600">Total Revenue</p>
                                        <p class="text-2xl font-semibold text-green-900">€{{ number_format($salesStats['revenue'], 2) }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-lg bg-yellow-100">
                                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-yellow-600">Avg Sale Price</p>
                                        <p class="text-2xl font-semibold text-yellow-900">€{{ number_format($salesStats['avg_sale_price'], 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center py-8 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sales Integration Coming Soon</h3>
                            <p class="mt-1 text-sm text-gray-500">Detailed sales charts and history will be available in a future update.</p>
                        </div>
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

        // Toggle Availability
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
                    showAlert('Availability updated successfully!', 'success');
                    // Reload page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('Failed to update availability.', 'error');
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
    </script>
</x-admin-layout>