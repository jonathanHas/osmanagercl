<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Barrel Code: {{ $barrelCode->supplier_code }}
            </h2>
            <a href="{{ route('barrel-codes.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Barrel Codes
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form -->
                <div class="lg:col-span-2">
                    <form action="{{ route('barrel-codes.update', $barrelCode) }}" method="POST" class="bg-white dark:bg-gray-800 shadow rounded-lg">
                        @csrf
                        @method('PUT')

                        <div class="p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Barrel Details</h3>
                            </div>

                            <div class="space-y-4">
                                <!-- Supplier Code (Read Only) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier Code
                                    </label>
                                    <input type="text"
                                           value="{{ $barrelCode->supplier_code }}"
                                           disabled
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Code from supplier invoice - cannot be changed.
                                    </p>
                                </div>

                                <!-- Supplier (Read Only) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier
                                    </label>
                                    <input type="text"
                                           value="{{ $barrelCode->supplier?->Supplier ?? 'Unknown' }}"
                                           disabled
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                                </div>

                                <!-- Custom Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Custom Name
                                    </label>
                                    <input type="text"
                                           id="name"
                                           name="name"
                                           value="{{ old('name', $barrelCode->name) }}"
                                           placeholder="Enter your own name for this barrel"
                                           maxlength="100"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Optional - your custom name will be displayed alongside the description.
                                    </p>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Description *
                                    </label>
                                    <input type="text"
                                           id="description"
                                           name="description"
                                           value="{{ old('description', $barrelCode->description) }}"
                                           required
                                           maxlength="150"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Description from the supplier invoice.
                                    </p>
                                </div>

                                <!-- Unit Price -->
                                <div>
                                    <label for="unit_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Unit Price (&euro;) *
                                    </label>
                                    <input type="number"
                                           id="unit_price"
                                           name="unit_price"
                                           value="{{ old('unit_price', $barrelCode->unit_price) }}"
                                           required
                                           step="0.01"
                                           min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Current deposit price per unit.
                                    </p>
                                </div>

                                <!-- Active Status -->
                                <div class="flex items-center">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox"
                                           id="is_active"
                                           name="is_active"
                                           value="1"
                                           {{ old('is_active', $barrelCode->is_active) ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded">
                                    <label for="is_active" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Active
                                    </label>
                                    <p class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                                        Inactive barrels will still be tracked but may be hidden from some views.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 rounded-b-lg flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Image Section -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Barrel Image</h3>
                        </div>

                        <!-- Current Image Display -->
                        <div class="mb-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Image</p>
                            <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden w-32 h-32">
                                <img id="current-barrel-image"
                                     src="{{ $barrelCode->image ? route('barrel-codes.image', $barrelCode) . '?t=' . time() : '' }}"
                                     alt="{{ $barrelCode->name ?? $barrelCode->description }}"
                                     class="w-full h-full object-cover {{ $barrelCode->image ? '' : 'hidden' }}"
                                     onerror="this.classList.add('hidden'); document.getElementById('no-image-placeholder').classList.remove('hidden');">
                                <div id="no-image-placeholder" class="w-full h-full flex items-center justify-center text-gray-400 {{ $barrelCode->image ? 'hidden' : '' }}">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Image Upload -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Upload New Image
                            </label>
                            <input type="file"
                                   id="image-input"
                                   accept="image/*"
                                   class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Max 2MB. JPEG, PNG, or GIF.</p>
                        </div>

                        <button type="button"
                                id="upload-btn"
                                class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Upload Image
                        </button>

                        @if($barrelCode->image)
                            <button type="button"
                                    id="remove-image-btn"
                                    class="w-full mt-2 px-4 py-2 bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 rounded-lg hover:bg-red-200 dark:hover:bg-red-800 transition">
                                Remove Image
                            </button>
                        @endif

                        <!-- Image Preview -->
                        <div id="image-preview" class="mt-4 hidden">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Preview:</p>
                            <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden w-24 h-24">
                                <img id="preview-img" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>

                    <!-- Usage Statistics -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Usage Statistics</h3>
                        </div>

                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Deliveries</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $usageCount }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Total Quantity</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($totalQuantity) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Total Value</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">&euro;{{ number_format($totalValue, 2) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Created: {{ $barrelCode->created_at->format('d M Y H:i') }}<br>
                                Updated: {{ $barrelCode->updated_at->format('d M Y H:i') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('image-input');
            const uploadBtn = document.getElementById('upload-btn');
            const removeBtn = document.getElementById('remove-image-btn');

            // Image Preview on file select
            imageInput.addEventListener('change', function(event) {
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

            // Image Upload Button Click
            uploadBtn.addEventListener('click', function(e) {
                e.preventDefault();

                const file = imageInput.files[0];
                if (!file) {
                    showAlert('Please select an image first.', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('image', file);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Uploading...';

                fetch('{{ route("barrel-codes.update-image", $barrelCode) }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Image updated successfully!', 'success');
                        // Refresh current image with server timestamp for cache busting
                        const timestamp = data.timestamp || new Date().getTime();
                        const img = document.getElementById('current-barrel-image');
                        img.src = '{{ route("barrel-codes.image", $barrelCode) }}?t=' + timestamp;
                        img.classList.remove('hidden');
                        document.getElementById('no-image-placeholder').classList.add('hidden');
                        // Hide preview
                        document.getElementById('image-preview').classList.add('hidden');
                        // Clear file input
                        imageInput.value = '';
                        // Show remove button if not present
                        if (!removeBtn) {
                            location.reload();
                        }
                    } else {
                        showAlert(data.message || 'Failed to update image.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error uploading image:', error);
                    showAlert('Error uploading image.', 'error');
                })
                .finally(() => {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload Image';
                });
            });

            // Remove Image Button Click
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    if (!confirm('Are you sure you want to remove this image?')) {
                        return;
                    }

                    fetch('{{ route("barrel-codes.remove-image", $barrelCode) }}', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('Image removed successfully!', 'success');
                            document.getElementById('current-barrel-image').classList.add('hidden');
                            document.getElementById('no-image-placeholder').classList.remove('hidden');
                            removeBtn.remove();
                        } else {
                            showAlert('Failed to remove image.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error removing image:', error);
                        showAlert('Error removing image.', 'error');
                    });
                });
            }

            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                    type === 'success'
                        ? 'bg-green-100 text-green-800 border border-green-300'
                        : 'bg-red-100 text-red-800 border border-red-300'
                }`;
                alertDiv.innerHTML = `
                    <div class="flex items-center">
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg font-bold">&times;</button>
                    </div>
                `;
                document.body.appendChild(alertDiv);

                // Auto-remove after 3 seconds
                setTimeout(() => alertDiv.remove(), 3000);
            }
        });
    </script>
    @endpush
</x-admin-layout>
