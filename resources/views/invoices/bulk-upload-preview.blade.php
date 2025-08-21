<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                @if(isset($isAmazonPendingView) && $isAmazonPendingView)
                    <h2 class="text-2xl font-bold text-gray-100">Amazon Payment Entry</h2>
                    <p class="text-gray-400 text-sm mt-1">Enter EUR payment amounts for Amazon invoices • Batch ID: {{ $batch->batch_id }}</p>
                @else
                    <h2 class="text-2xl font-bold text-gray-100">Upload Preview</h2>
                    <p class="text-gray-400 text-sm mt-1">Batch ID: {{ $batch->batch_id }}</p>
                @endif
            </div>
            <div class="flex space-x-2">
                @if(isset($isAmazonPendingView) && $isAmazonPendingView)
                    <a href="{{ route('invoices.bulk-upload.amazon-pending') }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        ← Back to Amazon Pending
                    </a>
                @else
                    <a href="{{ route('invoices.bulk-upload.index') }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        New Upload
                    </a>
                @endif
                <a href="{{ route('invoices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Invoices
                </a>
            </div>
        </div>

        {{-- Batch Summary --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-100 mb-4">Batch Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-gray-400 text-sm">Total Files</p>
                    <p class="text-2xl font-bold text-gray-100">{{ $batch->total_files }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Status</p>
                    <p class="text-lg font-medium">
                        @if($batch->status === 'completed')
                            <span class="text-green-400">Completed</span>
                        @elseif($batch->status === 'processing')
                            <span class="text-yellow-400">Processing</span>
                        @elseif($batch->status === 'failed')
                            <span class="text-red-400">Failed</span>
                        @else
                            <span class="text-gray-300">{{ ucfirst($batch->status) }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Uploaded By</p>
                    <p class="text-lg text-gray-100">{{ $batch->user->name }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Upload Time</p>
                    <p class="text-lg text-gray-100">{{ $batch->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>

            {{-- Progress Bar --}}
            @if($batch->status === 'processing')
            <div class="mt-6">
                <div class="flex justify-between text-sm text-gray-400 mb-2">
                    <span>Processing Progress</span>
                    <span>{{ $batch->processed_files }}/{{ $batch->total_files }} files</span>
                </div>
                <div class="bg-gray-700 rounded-full h-3">
                    <div class="bg-blue-500 h-3 rounded-full transition-all duration-500" 
                         style="width: {{ $batch->progress_percentage }}%"></div>
                </div>
            </div>
            @endif
        </div>

        {{-- Duplicate Warning Alert --}}
        @php
            $duplicateCount = $files->filter(function($file) {
                return $file->error_message && str_contains(strtolower($file->error_message), 'duplicate');
            })->count();
        @endphp
        @if($duplicateCount > 0)
        <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h4 class="text-yellow-400 font-semibold">
                        {{ $duplicateCount }} Potential Duplicate{{ $duplicateCount > 1 ? 's' : '' }} Detected
                    </h4>
                    <p class="text-yellow-300 text-sm mt-1">
                        Some files appear to match existing invoices in the system. Please review these carefully before creating new invoices.
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Files List --}}
        <div class="bg-gray-800 rounded-lg p-6" x-data="{ selectedFiles: [] }">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-100">Uploaded Files</h3>
                @if($batch->status === 'uploaded')
                <div class="flex space-x-2">
                    <button onclick="startParsing()" 
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Start Processing
                    </button>
                    @if($batch->canBeCancelled())
                    <button onclick="cancelBatch()" 
                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Cancel Batch
                    </button>
                    @endif
                </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                File Name
                            </th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Size
                            </th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($files as $file)
                        <tr class="hover:bg-gray-700/50">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    @if($file->isPdf())
                                        <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                                        </svg>
                                    @elseif($file->isImage())
                                        <svg class="w-5 h-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H4v10h12V5h-2a1 1 0 100-2 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-gray-200">{{ $file->original_filename }}</span>
                                    @if($file->isPdf() && $file->page_count > 0)
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-blue-900 text-blue-300">
                                            {{ $file->page_count }} page{{ $file->page_count > 1 ? 's' : '' }}
                                        </span>
                                    @endif
                                    @if($file->isSplitFile())
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-purple-900 text-purple-300" title="Created from splitting {{ $file->parentFile->original_filename }}">
                                            Split ({{ $file->page_range }})
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-gray-300 text-sm">
                                {{ strtoupper($file->extension) }}
                            </td>
                            <td class="py-3 px-4 text-gray-300 text-sm">
                                {{ $file->formatted_file_size }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs rounded-full bg-{{ $file->status_color }}-900 text-{{ $file->status_color }}-300">
                                    {{ $file->status_label }}
                                </span>
                                @if($file->error_message && str_contains(strtolower($file->error_message), 'duplicate'))
                                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-300 cursor-help" 
                                          title="{{ $file->error_message }}">
                                        ⚠ Possible Duplicate
                                    </span>
                                @endif
                                @if($file->parsing_confidence)
                                    <span class="ml-2 text-xs text-gray-400">
                                        ({{ round($file->parsing_confidence * 100) }}% confidence)
                                    </span>
                                @endif
                                @if($file->supplier_detected)
                                    <span class="ml-2 text-xs text-gray-400">
                                        [{{ $file->supplier_detected }}]
                                    </span>
                                @endif
                                
                                {{-- Amazon Payment Adjustment --}}
                                @php
                                    $adjustmentService = app(\App\Services\AmazonPaymentAdjustmentService::class);
                                    $needsAdjustment = $adjustmentService->needsPaymentAdjustment($file);
                                    $adjustmentData = $needsAdjustment ? $adjustmentService->getAdjustmentData($file) : [];
                                    
                                @endphp
                                
                                @if($needsAdjustment)
                                    {{-- Actual Amazon adjustment needed --}}
                                    <div class="mt-2 p-3 bg-yellow-900/30 border border-yellow-600 rounded-md">
                                        <div class="flex items-center mb-2">
                                            <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-yellow-300 text-sm font-medium">Payment Adjustment Needed</span>
                                        </div>
                                        <div class="text-xs text-gray-300 space-y-1">
                                            @if($adjustmentData['is_pre_parsing'] ?? false)
                                                <div class="text-blue-300">📋 Amazon invoice detected from filename</div>
                                                <div>Enter the EUR amount you paid - calculation will be verified after parsing</div>
                                            @elseif($adjustmentData['gbp_amounts_detected'] ?? false)
                                                {{-- Show GBP amounts from invoice --}}
                                                <div class="bg-gray-700 rounded p-2 mb-2">
                                                    <div class="font-medium text-gray-200 mb-1">Invoice Amounts (GBP):</div>
                                                    @if($adjustmentData['invoice_date'])
                                                    <div class="text-blue-300 font-medium text-sm mb-1">
                                                        📅 Invoice Date: {{ $adjustmentData['invoice_date'] }}
                                                    </div>
                                                    @endif
                                                    @if($adjustmentData['invoice_number'])
                                                    <div class="text-xs text-gray-400 mb-2">
                                                        Invoice #: {{ $adjustmentData['invoice_number'] }}
                                                    </div>
                                                    @endif
                                                    @if($adjustmentData['gbp_total'] > 0)
                                                    <div>GBP Total: £{{ number_format($adjustmentData['gbp_total'], 2) }}</div>
                                                    @endif
                                                    @if($adjustmentData['gbp_vat_amount'] > 0)
                                                    <div>GBP VAT ({{ $adjustmentData['vat_rate'] ?? '23%' }}): £{{ number_format($adjustmentData['gbp_vat_amount'], 2) }}</div>
                                                    @endif
                                                </div>
                                                {{-- Show EUR amounts if detected --}}
                                                @if($adjustmentData['eur_vat_detected'] ?? false)
                                                <div class="bg-green-900/30 rounded p-2">
                                                    <div class="font-medium text-green-300 mb-1">Detected EUR Amounts:</div>
                                                    <div>EUR VAT ({{ $adjustmentData['vat_rate'] ?? '23%' }}): €{{ number_format($adjustmentData['eur_vat_amount'], 2) }}</div>
                                                    @if($adjustmentData['eur_total'] > 0)
                                                    <div>EUR Total: €{{ number_format($adjustmentData['eur_total'], 2) }}</div>
                                                    @endif
                                                </div>
                                                @else
                                                <div class="text-yellow-300">⚠ EUR amounts not detected - enter actual EUR payment below</div>
                                                @endif
                                            @else
                                                <div class="text-yellow-300">⚠ Invoice amounts not detected by parser</div>
                                                <div>Check invoice manually and enter actual EUR amount paid</div>
                                            @endif
                                            <div class="mt-2">
                                                <label class="block text-yellow-300 text-xs font-medium mb-1">
                                                    Actual Amount Paid (from bank):
                                                </label>
                                                <div class="flex items-center space-x-2">
                                                    <input type="number" 
                                                           step="0.01" 
                                                           min="0"
                                                           name="actual_payment[{{ $file->id }}]" 
                                                           id="actual_payment_{{ $file->id }}"
                                                           class="w-24 px-2 py-1 text-xs bg-gray-800 border border-gray-600 rounded text-white focus:border-yellow-500 focus:outline-none"
                                                           placeholder="22.65"
                                                           onchange="updatePaymentPreview({{ $file->id }}, {{ $adjustmentData['eur_vat_amount'] ?? 0 }})"
                                                           onkeyup="if(event.key === 'Enter') updatePaymentPreview({{ $file->id }}, {{ $adjustmentData['eur_vat_amount'] ?? 0 }})" />
                                                    <span class="text-xs text-gray-400">EUR</span>
                                                </div>
                                                <div id="payment_preview_{{ $file->id }}" class="mt-2 p-2 text-xs bg-blue-900/30 border border-blue-600 rounded hidden">
                                                    <div class="font-medium text-blue-300 mb-1">VAT Breakdown (calculated):</div>
                                                    <div>VAT 23% Net: €<span class="vat-23-amount">0.00</span></div>
                                                    <div>VAT 23% Tax: €{{ number_format($adjustmentData['eur_vat_amount'] ?? 0, 2) }}</div>
                                                    <div>VAT 0% (exchange diff): €<span class="vat-0-amount">0.00</span></div>
                                                    <div class="mt-1 pt-1 border-t border-blue-600">
                                                        <strong>Total: €<span class="total-amount">0.00</span></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($file->status === 'parsing')
                                    {{-- Parsing in progress --}}
                                    <div class="mt-2 p-3 bg-purple-900/30 border border-purple-600 rounded-md">
                                        <div class="flex items-center">
                                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-purple-400 mr-2"></div>
                                            <span class="text-purple-300 text-sm font-medium">Processing Invoice...</span>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-2">
                                    @if($file->tempFileExists() && $file->isViewable())
                                    <button onclick="previewFile({{ $file->id }})" 
                                            class="text-blue-400 hover:text-blue-300 text-sm">
                                        View
                                    </button>
                                    @endif
                                    @if($file->canBeSplit())
                                    <button onclick="splitPdf({{ $file->id }})" 
                                            class="text-green-400 hover:text-green-300 text-sm">
                                        Split
                                    </button>
                                    @endif
                                    @if($file->status === 'failed' || $file->status === 'uploaded')
                                    <button onclick="removeFile({{ $file->id }})" 
                                            class="text-red-400 hover:text-red-300 text-sm">
                                        Remove
                                    </button>
                                    @endif
                                    @if($file->error_message)
                                        @if(str_contains(strtolower($file->error_message), 'duplicate'))
                                        <span class="text-yellow-400 text-xs" title="{{ $file->error_message }}">
                                            ⚠ Duplicate?
                                        </span>
                                        @else
                                        <span class="text-red-400 text-xs" title="{{ $file->error_message }}">
                                            ⚠ Error
                                        </span>
                                        @endif
                                    @endif
                                    @if($file->anomaly_warnings && count($file->anomaly_warnings) > 0)
                                    <span class="text-yellow-400 text-xs" title="{{ implode(', ', $file->anomaly_warnings) }}">
                                        ⚠ {{ count($file->anomaly_warnings) }} Warning(s)
                                    </span>
                                    @endif
                                    @if($file->status === 'parsed' || $file->status === 'review')
                                    <button onclick="viewParsedData({{ $file->id }})" 
                                            class="text-green-400 hover:text-green-300 text-sm">
                                        View Data
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Batch Actions --}}
            @php
                $hasReviewFiles = $files->whereIn('status', ['review', 'parsed', 'amazon_pending'])->count() > 0;
                $hasAmazonPending = $files->where('status', 'amazon_pending')->count() > 0;
                $hasCompletedFiles = $files->where('status', 'completed')->count() > 0;
            @endphp
            
            @if($hasReviewFiles)
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="createInvoicesFromReview()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    @if($hasAmazonPending)
                        Process Amazon Invoices with Payments
                    @else
                        Create Invoices from Reviewed Files
                    @endif
                </button>
            </div>
            @endif
            
            @if($hasCompletedFiles)
            <div class="mt-4 p-4 bg-green-900/30 border border-green-700 rounded">
                <p class="text-green-400">
                    <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ $files->where('status', 'completed')->count() }} invoice(s) have been created successfully.
                </p>
            </div>
            @endif
        </div>
        
        {{-- Parsed Data Modal --}}
        <div id="parsedDataModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-gray-800 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-100">Parsed Invoice Data</h3>
                        <button onclick="closeParsedDataModal()" class="text-gray-400 hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="parsedDataContent" class="space-y-4">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        {{-- PDF Split Modal --}}
        <div id="splitPdfModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-gray-800 rounded-lg max-w-6xl w-full max-h-[95vh] overflow-hidden">
                <div class="flex flex-col h-full">
                    {{-- Header --}}
                    <div class="flex justify-between items-center p-6 border-b border-gray-700">
                        <h3 class="text-xl font-bold text-gray-100">Split PDF</h3>
                        <button onclick="closeSplitPdfModal()" class="text-gray-400 hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    {{-- Content --}}
                    <div class="flex-1 p-6 overflow-y-auto">
                        <div id="splitPdfContent">
                            {{-- Loading spinner --}}
                            <div id="splitLoadingSpinner" class="flex items-center justify-center py-12">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-400"></div>
                                <span class="ml-3 text-gray-300">Loading PDF pages...</span>
                            </div>
                            
                            {{-- File info --}}
                            <div id="splitFileInfo" class="hidden">
                                <div class="bg-gray-700 rounded-lg p-4 mb-6">
                                    <h4 class="text-lg font-semibold text-gray-100 mb-2" id="splitFileName"></h4>
                                    <p class="text-gray-400 text-sm" id="splitFileDetails"></p>
                                </div>
                            </div>
                            
                            {{-- Split options --}}
                            <div id="splitOptions" class="hidden">
                                <div class="mb-6">
                                    <label class="block text-gray-300 font-medium mb-3">Split Mode:</label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="splitMode" value="per-page" checked class="form-radio text-blue-600">
                                            <span class="ml-2 text-gray-300">One invoice per page</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="splitMode" value="custom" class="form-radio text-blue-600">
                                            <span class="ml-2 text-gray-300">Custom page ranges</span>
                                        </label>
                                    </div>
                                </div>
                                
                                {{-- Page preview grid --}}
                                <div id="pagePreview" class="mb-6">
                                    <h4 class="text-gray-300 font-medium mb-3">Page Preview:</h4>
                                    <div id="pageGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        {{-- Thumbnails will be loaded here --}}
                                    </div>
                                </div>
                                
                                {{-- Custom ranges --}}
                                <div id="customRanges" class="hidden mb-6">
                                    <h4 class="text-gray-300 font-medium mb-3">Page Ranges:</h4>
                                    <div id="rangeInputs">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <input type="text" placeholder="e.g., 1 or 1-2" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                            <button onclick="addRangeInput()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Add Range</button>
                                        </div>
                                    </div>
                                    <p class="text-gray-400 text-xs mt-1">Enter page ranges like "1", "2-3", "4-5". Each range will create a separate invoice.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Footer --}}
                    <div class="border-t border-gray-700 p-6">
                        <div class="flex justify-end space-x-3">
                            <button onclick="closeSplitPdfModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded">
                                Cancel
                            </button>
                            <button id="confirmSplit" onclick="confirmPdfSplit()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded" disabled>
                                Split PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const batchId = '{{ $batch->batch_id }}';

        function startParsing() {
            if (confirm('Start processing all uploaded files? This will run the Python parser on each file.')) {
                // Collect payment adjustment data
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                
                // Find all payment adjustment inputs
                const paymentInputs = document.querySelectorAll('input[name^="actual_payment["]');
                paymentInputs.forEach(input => {
                    if (input.value && input.value.trim() !== '') {
                        formData.append(input.name, input.value);
                    }
                });
                
                fetch(`/invoices/bulk-upload/${batchId}/process`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Reload to show processing status
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to start processing');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while starting processing');
                });
            }
        }

        function cancelBatch() {
            if (confirm('Are you sure you want to cancel this batch? This cannot be undone.')) {
                fetch(`/invoices/bulk-upload/${batchId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '{{ route("invoices.bulk-upload.index") }}';
                    } else {
                        alert(data.error || 'Failed to cancel batch');
                    }
                });
            }
        }

        function removeFile(fileId) {
            if (confirm('Remove this file from the batch?')) {
                fetch(`/invoices/bulk-upload/${batchId}/file/${fileId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to remove file');
                    }
                });
            }
        }

        function previewFile(fileId) {
            // Open embedded file viewer in new window
            const viewerUrl = `/invoices/bulk-upload/${batchId}/file/${fileId}/viewer`;
            window.open(viewerUrl, 'file-viewer', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }
        
        function viewParsedData(fileId) {
            // Find the file data
            const files = @json($files);
            const file = files.find(f => f.id === fileId);
            
            if (!file) {
                alert('File data not found');
                return;
            }
            
            let content = '<div class="space-y-3">';
            
            // Basic info
            content += '<div class="bg-gray-700 p-4 rounded">';
            content += '<h4 class="text-gray-300 font-semibold mb-2">Basic Information</h4>';
            content += `<p class="text-gray-400"><span class="font-medium">Filename:</span> ${file.original_filename}</p>`;
            content += `<p class="text-gray-400"><span class="font-medium">Supplier:</span> ${file.supplier_detected || 'Unknown'}</p>`;
            content += `<p class="text-gray-400"><span class="font-medium">Confidence:</span> ${Math.round((file.parsing_confidence || 0) * 100)}%</p>`;
            content += '</div>';
            
            // Invoice details
            if (file.parsed_invoice_date || file.parsed_invoice_number || file.parsed_total_amount) {
                content += '<div class="bg-gray-700 p-4 rounded">';
                content += '<h4 class="text-gray-300 font-semibold mb-2">Invoice Details</h4>';
                if (file.parsed_invoice_number) {
                    content += `<p class="text-gray-400"><span class="font-medium">Invoice Number:</span> ${file.parsed_invoice_number}</p>`;
                }
                if (file.parsed_invoice_date) {
                    content += `<p class="text-gray-400"><span class="font-medium">Invoice Date:</span> ${file.parsed_invoice_date}</p>`;
                }
                if (file.parsed_total_amount) {
                    content += `<p class="text-gray-400"><span class="font-medium">Total Amount:</span> €${parseFloat(file.parsed_total_amount).toFixed(2)}</p>`;
                }
                content += `<p class="text-gray-400"><span class="font-medium">Tax Free:</span> ${file.is_tax_free ? 'Yes' : 'No'}</p>`;
                content += `<p class="text-gray-400"><span class="font-medium">Credit Note:</span> ${file.is_credit_note ? 'Yes' : 'No'}</p>`;
                content += '</div>';
            }
            
            // VAT breakdown
            if (file.parsed_vat_data) {
                content += '<div class="bg-gray-700 p-4 rounded">';
                content += '<h4 class="text-gray-300 font-semibold mb-2">VAT Breakdown</h4>';
                content += '<table class="w-full text-sm">';
                content += '<tr class="border-b border-gray-600">';
                content += '<th class="text-left py-2 text-gray-400">VAT Rate</th>';
                content += '<th class="text-right py-2 text-gray-400">Net Amount</th>';
                content += '<th class="text-right py-2 text-gray-400">VAT Amount</th>';
                content += '</tr>';
                
                const vatData = file.parsed_vat_data;
                
                // Handle both old format (simple floats) and new format (objects with net/vat)
                const vatRates = [
                    { key: 'vat_0', rate: '0%' },
                    { key: 'vat_9', rate: '9%' },
                    { key: 'vat_13_5', rate: '13.5%' },
                    { key: 'vat_23', rate: '23%' }
                ];
                
                let hasVatData = false;
                vatRates.forEach(({ key, rate }) => {
                    let netAmount = 0;
                    let vatAmount = 0;
                    
                    if (vatData[key]) {
                        // Check if it's new format (object) or old format (number)
                        if (typeof vatData[key] === 'object' && vatData[key].net !== undefined) {
                            // New format
                            netAmount = vatData[key].net || 0;
                            vatAmount = vatData[key].vat || 0;
                        } else {
                            // Old format - simple float is the net amount
                            netAmount = parseFloat(vatData[key]) || 0;
                            // Calculate VAT based on rate
                            if (rate === '9%') vatAmount = netAmount * 0.09;
                            else if (rate === '13.5%') vatAmount = netAmount * 0.135;
                            else if (rate === '23%') vatAmount = netAmount * 0.23;
                        }
                        
                        if (netAmount > 0) {
                            hasVatData = true;
                            content += `<tr>`;
                            content += `<td class="py-1 text-gray-300">${rate}</td>`;
                            content += `<td class="text-right text-gray-300">€${netAmount.toFixed(2)}</td>`;
                            content += `<td class="text-right text-gray-300">€${vatAmount.toFixed(2)}</td>`;
                            content += `</tr>`;
                        }
                    }
                });
                
                if (!hasVatData) {
                    content += '<tr><td colspan="3" class="py-2 text-gray-400 text-center">No VAT data available</td></tr>';
                }
                
                content += '</table>';
                content += '</div>';
            }
            
            // Check for duplicate detection in error message
            if (file.error_message && file.error_message.toLowerCase().includes('duplicate')) {
                content += '<div class="bg-yellow-900/30 border border-yellow-700 p-4 rounded">';
                content += '<h4 class="text-yellow-400 font-semibold mb-2">⚠ Potential Duplicate Detected</h4>';
                content += `<p class="text-yellow-300">${file.error_message}</p>`;
                content += '<p class="text-yellow-200 text-sm mt-2">This invoice may already exist in the system. Please review before creating.</p>';
                content += '</div>';
            }
            
            // Warnings
            else if (file.anomaly_warnings && file.anomaly_warnings.length > 0) {
                content += '<div class="bg-yellow-900/30 border border-yellow-700 p-4 rounded">';
                content += '<h4 class="text-yellow-400 font-semibold mb-2">⚠ Warnings</h4>';
                content += '<ul class="list-disc list-inside text-yellow-300 space-y-1">';
                file.anomaly_warnings.forEach(warning => {
                    content += `<li>${warning}</li>`;
                });
                content += '</ul>';
                content += '</div>';
            }
            
            // Other Errors
            else if (file.error_message) {
                content += '<div class="bg-red-900/30 border border-red-700 p-4 rounded">';
                content += '<h4 class="text-red-400 font-semibold mb-2">❌ Error</h4>';
                content += `<p class="text-red-300">${file.error_message}</p>`;
                content += '</div>';
            }
            
            content += '</div>';
            
            document.getElementById('parsedDataContent').innerHTML = content;
            document.getElementById('parsedDataModal').classList.remove('hidden');
        }
        
        function closeParsedDataModal() {
            document.getElementById('parsedDataModal').classList.add('hidden');
        }
        
        function createInvoicesFromReview() {
            @if($hasAmazonPending)
            // Check that Amazon invoices have payment amounts entered
            const amazonMissingPayments = [];
            const paymentInputs = document.querySelectorAll('input[name^="actual_payment["]');
            
            paymentInputs.forEach(input => {
                if (!input.value || input.value.trim() === '') {
                    const fileRow = input.closest('tr');
                    if (fileRow) {
                        const filename = fileRow.querySelector('td:first-child .text-gray-200')?.textContent;
                        if (filename) {
                            amazonMissingPayments.push(filename);
                        }
                    }
                }
            });
            
            if (amazonMissingPayments.length > 0) {
                alert('Please enter payment amounts for all Amazon invoices:\n\n' + amazonMissingPayments.join('\n'));
                return;
            }
            
            if (!confirm('Process Amazon invoices with payment adjustments?\n\nThis will:\n• Create invoices with actual EUR amounts paid\n• Calculate correct VAT breakdown\n• Apply exchange rate differences\n\nAre you sure you want to proceed?')) {
                return;
            }
            @else
            if (!confirm('Create invoices from all reviewed files?\n\nThis will:\n• Process all files marked for review\n• CREATE DUPLICATE INVOICES if any were detected\n• Override any warnings about existing invoices\n\nAre you sure you want to proceed?')) {
                return;
            }
            @endif
            
            // Collect payment adjustment data
            const paymentData = {};
            // Reuse the paymentInputs variable from above
            paymentInputs.forEach(input => {
                if (input.value && input.value.trim() !== '') {
                    const fileId = input.name.match(/\[(\d+)\]/)[1];
                    paymentData[fileId] = input.value;
                }
            });
            
            fetch(`/invoices/bulk-upload/${batchId}/create-from-review`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    payment_adjustments: paymentData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to create invoices');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating invoices');
            });
        }

        // Auto-refresh if batch is processing or has files still being parsed
        @if($batch->status === 'processing' || $batch->status === 'uploaded' || $files->whereIn('status', ['uploaded', 'parsing'])->count() > 0)
        let refreshInterval = setInterval(() => {
            fetch(`/invoices/bulk-upload/status/${batchId}`)
                .then(response => {
                    if (!response.ok) {
                        console.error('Status check failed:', response.status);
                        return;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data) {
                        // Check if all files are done processing
                        const stillProcessing = data.files.some(file => 
                            file.status === 'parsing' || file.status === 'uploaded'
                        );
                        
                        // Reload if batch changed or no files still processing
                        if (data.status !== '{{ $batch->status }}' || !stillProcessing) {
                            clearInterval(refreshInterval);
                            window.location.reload();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                });
        }, 2000); // Check every 2 seconds for faster updates
        @endif

        // Amazon Payment Adjustment Preview
        function updatePaymentPreview(fileId, vatAmount) {
            const input = document.getElementById(`actual_payment_${fileId}`);
            const preview = document.getElementById(`payment_preview_${fileId}`);
            const vatDisplay = preview.querySelector('.vat-23-amount');
            const diffDisplay = preview.querySelector('.vat-0-amount');
            const totalDisplay = preview.querySelector('.total-amount');
            
            const actualPaid = parseFloat(input.value);
            
            if (actualPaid && actualPaid > 0) {
                if (vatAmount > 0) {
                    // Calculate VAT breakdown using detected EUR VAT
                    const netAt23 = vatAmount / 0.23;
                    const expectedTotal = netAt23 + vatAmount;
                    const difference = actualPaid - expectedTotal;
                    
                    vatDisplay.textContent = netAt23.toFixed(2);
                    diffDisplay.textContent = Math.max(0, difference).toFixed(2);
                    if (totalDisplay) {
                        totalDisplay.textContent = actualPaid.toFixed(2);
                    }
                } else {
                    // No EUR VAT detected - treat entire amount as 0% VAT
                    vatDisplay.textContent = '0.00';
                    diffDisplay.textContent = actualPaid.toFixed(2);
                    if (totalDisplay) {
                        totalDisplay.textContent = actualPaid.toFixed(2);
                    }
                }
                
                preview.classList.remove('hidden');
                // Add subtle fade-in effect
                preview.style.opacity = '0';
                setTimeout(() => {
                    preview.style.opacity = '1';
                }, 10);
            } else {
                preview.classList.add('hidden');
            }
        }

        // PDF Splitting functionality
        let currentFileForSplit = null;
        let currentThumbnails = [];

        function splitPdf(fileId) {
            currentFileForSplit = fileId;
            
            // Show modal and loading spinner
            document.getElementById('splitPdfModal').classList.remove('hidden');
            document.getElementById('splitLoadingSpinner').classList.remove('hidden');
            document.getElementById('splitFileInfo').classList.add('hidden');
            document.getElementById('splitOptions').classList.add('hidden');
            document.getElementById('confirmSplit').disabled = true;
            
            // Find file data
            const files = @json($files);
            const file = files.find(f => f.id === fileId);
            
            if (!file) {
                alert('File not found');
                closeSplitPdfModal();
                return;
            }
            
            // Update file info
            document.getElementById('splitFileName').textContent = file.original_filename;
            document.getElementById('splitFileDetails').textContent = 
                `${file.formatted_file_size} • ${file.page_count} pages • PDF`;
            
            // Get thumbnails
            fetch(`/invoices/bulk-upload/${batchId}/file/${fileId}/thumbnails`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentThumbnails = data.thumbnails;
                        displayThumbnails(data.thumbnails);
                        
                        // Hide loading, show content
                        document.getElementById('splitLoadingSpinner').classList.add('hidden');
                        document.getElementById('splitFileInfo').classList.remove('hidden');
                        document.getElementById('splitOptions').classList.remove('hidden');
                        document.getElementById('confirmSplit').disabled = false;
                    } else {
                        alert('Failed to load page thumbnails: ' + data.error);
                        closeSplitPdfModal();
                    }
                })
                .catch(error => {
                    console.error('Error loading thumbnails:', error);
                    alert('Failed to load page thumbnails');
                    closeSplitPdfModal();
                });
        }
        
        function displayThumbnails(thumbnails) {
            const grid = document.getElementById('pageGrid');
            grid.innerHTML = '';
            
            thumbnails.forEach(thumbnail => {
                const pageDiv = document.createElement('div');
                pageDiv.className = 'bg-gray-700 rounded-lg p-3 text-center cursor-pointer hover:bg-gray-600 transition-colors';
                pageDiv.innerHTML = `
                    <img src="${thumbnail.data}" alt="Page ${thumbnail.page}" 
                         class="w-full h-40 object-contain rounded mb-2 bg-white">
                    <p class="text-sm text-gray-300">Page ${thumbnail.page}</p>
                `;
                
                // Add click handler for page selection in custom mode
                pageDiv.onclick = () => togglePageSelection(thumbnail.page, pageDiv);
                
                grid.appendChild(pageDiv);
            });
        }
        
        function togglePageSelection(pageNumber, element) {
            const isCustomMode = document.querySelector('input[name="splitMode"][value="custom"]').checked;
            if (!isCustomMode) return;
            
            element.classList.toggle('ring-2');
            element.classList.toggle('ring-blue-500');
            element.classList.toggle('bg-blue-900');
        }
        
        function closeSplitPdfModal() {
            document.getElementById('splitPdfModal').classList.add('hidden');
            currentFileForSplit = null;
            currentThumbnails = [];
            
            // Reset form
            document.querySelector('input[name="splitMode"][value="per-page"]').checked = true;
            document.getElementById('customRanges').classList.add('hidden');
            
            // Clear page selections
            const pages = document.querySelectorAll('#pageGrid > div');
            pages.forEach(page => {
                page.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-900');
            });
        }
        
        function confirmPdfSplit() {
            if (!currentFileForSplit) return;
            
            const splitMode = document.querySelector('input[name="splitMode"]:checked').value;
            let pageRanges = [];
            
            if (splitMode === 'per-page') {
                // Create range for each page
                for (let i = 1; i <= currentThumbnails.length; i++) {
                    pageRanges.push(i.toString());
                }
            } else if (splitMode === 'custom') {
                // Get custom ranges from inputs
                const rangeInputs = document.querySelectorAll('#rangeInputs input');
                rangeInputs.forEach(input => {
                    if (input.value.trim()) {
                        pageRanges.push(input.value.trim());
                    }
                });
                
                if (pageRanges.length === 0) {
                    alert('Please enter at least one page range');
                    return;
                }
            }
            
            // Disable button and show loading
            const confirmButton = document.getElementById('confirmSplit');
            confirmButton.disabled = true;
            confirmButton.innerHTML = 'Splitting...';
            
            // Perform split
            fetch(`/invoices/bulk-upload/${batchId}/file/${currentFileForSplit}/split`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    page_ranges: pageRanges
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully split PDF into ${data.split_count} files`);
                    closeSplitPdfModal();
                    window.location.reload(); // Reload to show new files
                } else {
                    alert('Failed to split PDF: ' + data.error);
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = 'Split PDF';
                }
            })
            .catch(error => {
                console.error('Error splitting PDF:', error);
                alert('Failed to split PDF');
                confirmButton.disabled = false;
                confirmButton.innerHTML = 'Split PDF';
            });
        }
        
        function addRangeInput() {
            const container = document.getElementById('rangeInputs');
            const div = document.createElement('div');
            div.className = 'flex items-center space-x-2 mb-2';
            div.innerHTML = `
                <input type="text" placeholder="e.g., 1 or 1-2" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                <button onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">Remove</button>
            `;
            container.appendChild(div);
        }
        
        // Handle split mode changes
        document.addEventListener('DOMContentLoaded', function() {
            const splitModeInputs = document.querySelectorAll('input[name="splitMode"]');
            splitModeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const customRanges = document.getElementById('customRanges');
                    if (this.value === 'custom') {
                        customRanges.classList.remove('hidden');
                    } else {
                        customRanges.classList.add('hidden');
                    }
                });
            });
        });
    </script>
    @endpush
</x-admin-layout>