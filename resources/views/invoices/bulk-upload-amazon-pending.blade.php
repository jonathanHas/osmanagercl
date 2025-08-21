<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Amazon Pending Payments</h2>
                <p class="text-gray-400 text-sm mt-1">Amazon invoices awaiting EUR payment entry ({{ $totalFiles }} invoice{{ $totalFiles !== 1 ? 's' : '' }})</p>
            </div>
            <div class="flex space-x-2">
                @if($totalFiles > 0)
                    <button onclick="selectAll()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Select All
                    </button>
                    <button onclick="deleteSelected()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" disabled>
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span id="delete-text">Delete Selected</span>
                    </button>
                @endif
                <a href="{{ route('invoices.bulk-upload.index') }}" 
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Upload More Invoices
                </a>
                <a href="{{ route('invoices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Invoices
                </a>
            </div>
        </div>

        @if($totalFiles === 0)
            {{-- No pending invoices --}}
            <div class="bg-gray-800 rounded-lg p-8 text-center">
                <svg class="w-16 h-16 text-gray-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-100 mb-2">No Amazon Invoices Pending</h3>
                <p class="text-gray-400 mb-4">All Amazon invoices have been processed!</p>
                <a href="{{ route('invoices.bulk-upload.index') }}" 
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Upload More Invoices
                </a>
            </div>
        @else
            {{-- Summary Card --}}
            <div class="bg-yellow-900/30 border border-yellow-600 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-2xl font-bold text-yellow-300">{{ $totalFiles }}</p>
                        <p class="text-yellow-400 text-sm">Amazon Invoice{{ $totalFiles !== 1 ? 's' : '' }} Pending Payment Entry</p>
                    </div>
                </div>
            </div>

            {{-- Batches --}}
            @foreach($batches as $batchData)
                <div class="bg-gray-800 rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100">
                                Batch: {{ $batchData['batch']->batch_id }}
                            </h3>
                            <p class="text-gray-400 text-sm">
                                Uploaded {{ $batchData['batch']->created_at->format('d/m/Y H:i') }} • 
                                {{ $batchData['files']->count() }} Amazon invoice{{ $batchData['files']->count() !== 1 ? 's' : '' }}
                            </p>
                        </div>
                        <a href="{{ route('invoices.bulk-upload.preview', ['batchId' => $batchData['batch']->batch_id, 'amazon_pending' => '1', 'supplier' => 'Amazon']) }}" 
                           class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Process Amazon Invoices
                        </a>
                    </div>

                    {{-- File List --}}
                    <div class="space-y-3">
                        @foreach($batchData['files'] as $file)
                            <div class="flex items-center justify-between p-3 bg-gray-700 rounded file-item">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <input type="checkbox" 
                                               class="file-checkbox w-5 h-5 text-blue-600 bg-gray-600 border-gray-500 rounded focus:ring-blue-500 focus:ring-2"
                                               value="{{ $file->id }}"
                                               onchange="updateDeleteButton()">
                                    </div>
                                    <div class="flex-shrink-0">
                                        <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-gray-100 font-medium">{{ $file->original_filename }}</p>
                                        <div class="flex items-center space-x-4 text-sm text-gray-400">
                                            <span>Status: 
                                                <span class="px-2 py-1 text-xs rounded-full bg-{{ $file->status_color }}-900 text-{{ $file->status_color }}-300">
                                                    {{ $file->status_label }}
                                                </span>
                                            </span>
                                            @if($file->parsed_invoice_date)
                                                <span>Date: {{ \Carbon\Carbon::parse($file->parsed_invoice_date)->format('d/m/Y') }}</span>
                                            @endif
                                            @if($file->parsed_total_amount)
                                                <span>Amount: €{{ number_format($file->parsed_total_amount, 2) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    @if($file->tempFileExists())
                                        <a href="{{ route('invoices.bulk-upload.file-viewer', ['batchId' => $file->bulk_upload_id, 'fileId' => $file->id]) }}" 
                                           target="_blank"
                                           class="text-blue-400 hover:text-blue-300 text-sm">
                                            View PDF
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Help Text --}}
            <div class="bg-blue-900/30 border border-blue-600 rounded-lg p-4">
                <div class="flex">
                    <svg class="w-5 h-5 text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 011-1h.01a1 1 0 110 2H12a1 1 0 01-1-1zM10 7a2 2 0 10.001 3.999A2 2 0 0010 7zM10 12a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-blue-300 mb-1">How to Process Amazon Invoices</h4>
                        <p class="text-blue-200 text-sm">
                            Click "Process Amazon Invoices" for each batch to enter the actual EUR payment amounts from your bank statement. 
                            The system will automatically calculate VAT breakdowns and create proper invoice records.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($totalFiles > 0)
    <script>
        // CSRF token for AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            const selectAllBtn = document.querySelector('button[onclick="selectAll()"]');
            
            // Check if all are already selected
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            // Update button text
            if (allChecked) {
                selectAllBtn.innerHTML = `<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>Select All`;
            } else {
                selectAllBtn.innerHTML = `<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>Deselect All`;
            }
            
            updateDeleteButton();
        }
        
        function updateDeleteButton() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            const deleteBtn = document.querySelector('button[onclick="deleteSelected()"]');
            const deleteText = document.getElementById('delete-text');
            
            if (checkboxes.length > 0) {
                deleteBtn.disabled = false;
                deleteText.textContent = `Delete Selected (${checkboxes.length})`;
            } else {
                deleteBtn.disabled = true;
                deleteText.textContent = 'Delete Selected';
            }
        }
        
        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select files to delete.');
                return;
            }
            
            const fileIds = Array.from(checkboxes).map(cb => cb.value);
            const count = fileIds.length;
            
            if (!confirm(`Are you sure you want to delete ${count} Amazon pending invoice${count !== 1 ? 's' : ''}?\n\nThis action cannot be undone.`)) {
                return;
            }
            
            const deleteBtn = document.querySelector('button[onclick="deleteSelected()"]');
            const originalText = deleteBtn.innerHTML;
            
            // Disable button and show loading
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = `<svg class="w-4 h-4 inline mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>Deleting...`;
            
            // Make AJAX request
            fetch('{{ route("invoices.bulk-upload.delete-amazon-pending-files") }}', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    file_ids: fileIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove deleted items from DOM
                    checkboxes.forEach(checkbox => {
                        const fileItem = checkbox.closest('.file-item');
                        fileItem.remove();
                    });
                    
                    // Update counters
                    location.reload(); // Refresh to update counts
                } else {
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete files. Please try again.');
            })
            .finally(() => {
                // Re-enable button
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            });
        }
    </script>
    @endif
</x-admin-layout>