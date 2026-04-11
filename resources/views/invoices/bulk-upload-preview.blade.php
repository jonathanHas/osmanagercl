<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
            <div>
                @if(isset($isAmazonPendingView) && $isAmazonPendingView)
                    <h2 class="text-2xl font-bold text-gray-100">Amazon Payment Entry</h2>
                    <p class="text-gray-400 text-sm mt-1">Enter EUR payment amounts for Amazon invoices • Batch ID: {{ $batch->batch_id }}</p>
                @else
                    <h2 class="text-2xl font-bold text-gray-100">Upload Preview</h2>
                    <p class="text-gray-400 text-sm mt-1">Batch ID: {{ $batch->batch_id }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if(isset($isAmazonPendingView) && $isAmazonPendingView)
                    <a href="{{ route('invoices.bulk-upload.amazon-pending') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold text-sm py-1.5 px-3 sm:py-2 sm:px-4 rounded">
                        ← Back to Amazon Pending
                    </a>
                @else
                    <a href="{{ route('invoices.bulk-upload.index') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold text-sm py-1.5 px-3 sm:py-2 sm:px-4 rounded">
                        New Upload
                    </a>
                @endif
                <a href="{{ route('invoices.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold text-sm py-1.5 px-3 sm:py-2 sm:px-4 rounded">
                    Back to Invoices
                </a>
            </div>
        </div>

        {{-- Batch Summary --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-100 mb-4">Batch Summary</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                <div>
                    <p class="text-gray-400 text-sm">Total Files</p>
                    <p class="text-2xl font-bold text-gray-100">{{ $batch->total_files }}</p>
                </div>
                @php
                    $multiPageCount = $files->filter(function($file) {
                        return $file->isPdf() && $file->page_count > 1;
                    })->count();
                @endphp
                @if($multiPageCount > 0)
                <div>
                    <p class="text-amber-400 text-sm font-semibold">⚠ Multi-Page PDFs</p>
                    <p class="text-2xl font-bold text-amber-300">{{ $multiPageCount }}</p>
                    <p class="text-amber-400 text-xs">May need splitting</p>
                </div>
                @endif
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
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 text-sm text-gray-400 mb-2">
                    <span>Processing Progress</span>
                    <div class="flex items-center space-x-4">
                        <span>{{ $batch->processed_files }}/{{ $batch->total_files }} files</span>
                        <button onclick="cancelBatch()"
                                class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-1 px-3 rounded">
                            Cancel Processing
                        </button>
                    </div>
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
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
                <h3 class="text-lg font-semibold text-gray-100">Uploaded Files</h3>
                @if($batch->status === 'uploaded')
                <div class="flex flex-wrap gap-2">
                    <button onclick="startParsing()"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold text-sm py-1.5 px-3 sm:py-2 sm:px-4 rounded">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Start Processing
                    </button>
                    @if($batch->canBeCancelled())
                    <button onclick="cancelBatch()"
                            class="bg-red-600 hover:bg-red-700 text-white font-bold text-sm py-1.5 px-3 sm:py-2 sm:px-4 rounded">
                        Cancel Batch
                    </button>
                    @endif
                </div>
                @endif
            </div>

            {{-- Mobile Card Layout --}}
            <div class="md:hidden space-y-4">
                @foreach($files as $file)
                <div class="bg-gray-700/50 rounded-lg p-4 @if($file->isPdf() && $file->page_count > 1) border-l-4 border-amber-500 @endif"
                     x-data="{ showEdit: false }">

                    {{-- Card Header: filename + status --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center min-w-0">
                            @if($file->isPdf())
                                <svg class="w-5 h-5 flex-shrink-0 @if($file->page_count > 1) text-amber-400 @else text-red-400 @endif mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                                </svg>
                            @elseif($file->isImage())
                                <svg class="w-5 h-5 flex-shrink-0 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 flex-shrink-0 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                                </svg>
                            @endif
                            <div class="min-w-0">
                                <p class="text-gray-200 text-sm font-medium truncate">{{ $file->original_filename }}</p>
                                <p class="text-gray-400 text-xs mt-0.5">
                                    {{ strtoupper($file->extension) }} &middot; {{ $file->formatted_file_size }}
                                    @if($file->isPdf() && $file->page_count > 0)
                                        &middot; {{ $file->page_count }} page{{ $file->page_count > 1 ? 's' : '' }}
                                    @endif
                                    @if($file->isSplitFile())
                                        &middot; <span class="text-purple-300">Split ({{ $file->page_range }})</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="flex-shrink-0 px-2 py-1 text-xs rounded-full bg-{{ $file->status_color }}-900 text-{{ $file->status_color }}-300">
                            {{ $file->status_label }}
                        </span>
                    </div>

                    {{-- Key invoice data (always visible if parsed) --}}
                    @if(in_array($file->status, ['parsed', 'review', 'amazon_pending', 'completed']))
                    <div class="mt-3 bg-gray-800 rounded-lg p-3 space-y-2">
                        @if($file->supplier_detected)
                        <div class="text-white text-base font-bold">{{ $file->supplier_detected }}</div>
                        @endif

                        <div class="flex items-center justify-between">
                            @if($file->parsed_invoice_date)
                                @php
                                    $parsedDate = \Carbon\Carbon::parse($file->parsed_invoice_date);
                                    $monthsDiff = abs($parsedDate->diffInMonths(now()));
                                    $isSuspiciousDate = $monthsDiff > 2 || $parsedDate->year < now()->year - 1 || $parsedDate->isAfter(now()->addDays(7));
                                @endphp
                                <span class="text-sm {{ $isSuspiciousDate ? 'px-2 py-0.5 bg-red-900 border border-red-500 rounded text-red-300 font-bold' : 'text-gray-300' }}">
                                    {{ $parsedDate->format('d/m/Y') }}
                                    @if($isSuspiciousDate) ⚠@endif
                                </span>
                            @endif
                            @if($file->parsed_invoice_number)
                                <span class="text-sm text-gray-400 font-mono">#{{ $file->parsed_invoice_number }}</span>
                            @endif
                        </div>

                        @if($file->parsed_total_amount)
                            @php
                                $vatData = $file->parsed_vat_data;
                                $totalVat = 0;
                                $totalNet = 0;
                                if ($vatData) {
                                    foreach ($vatData as $rate) {
                                        if (is_array($rate)) {
                                            $totalNet += $rate['net'] ?? 0;
                                            $totalVat += $rate['vat'] ?? 0;
                                        }
                                    }
                                }
                            @endphp
                            <div class="grid grid-cols-3 gap-2 text-center bg-gray-700/50 rounded p-2">
                                <div>
                                    <div class="text-gray-400 text-xs">Net</div>
                                    <div class="text-gray-100 text-sm font-semibold">&euro;{{ number_format($totalNet > 0 ? $totalNet : $file->parsed_total_amount, 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-xs">VAT</div>
                                    <div class="text-gray-100 text-sm font-semibold">&euro;{{ number_format($totalVat, 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-xs">Total</div>
                                    <div class="text-green-400 text-sm font-bold">&euro;{{ number_format($file->parsed_total_amount, 2) }}</div>
                                </div>
                            </div>
                        @endif

                        @if($file->parsing_confidence)
                            <div class="text-gray-500 text-xs">{{ round($file->parsing_confidence * 100) }}% confidence</div>
                        @endif
                    </div>
                    @endif

                    {{-- Duplicate badge --}}
                    @if($file->error_message && str_contains(strtolower($file->error_message), 'duplicate'))
                        <div class="mt-2">
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-300">⚠ Possible Duplicate</span>
                        </div>
                    @endif

                    {{-- Parsing spinner --}}
                    @if($file->status === 'parsing')
                        <div class="mt-3 p-3 bg-purple-900/30 border border-purple-600 rounded-md">
                            <div class="flex items-center">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-purple-400 mr-2"></div>
                                <span class="text-purple-300 text-sm font-medium">Processing Invoice...</span>
                            </div>
                        </div>
                    @endif


                    {{-- Details panel --}}
                    <div class="mt-3 space-y-2">
                        {{-- VAT breakdown --}}
                        @if($file->parsed_vat_data)
                            @php
                                $vatSummary = [];
                                foreach (['vat_0' => '0%', 'vat_9' => '9%', 'vat_13_5' => '13.5%', 'vat_23' => '23%'] as $key => $rate) {
                                    if (isset($file->parsed_vat_data[$key])) {
                                        $netAmount = is_array($file->parsed_vat_data[$key])
                                            ? ($file->parsed_vat_data[$key]['net'] ?? 0)
                                            : $file->parsed_vat_data[$key];
                                        if ($netAmount > 0) {
                                            $vatSummary[] = $rate . ' (&euro;' . number_format($netAmount, 2) . ')';
                                        }
                                    }
                                }
                            @endphp
                            @if(count($vatSummary) > 0)
                                <div class="p-2 bg-gray-800 rounded text-xs text-gray-400">
                                    <span class="font-medium text-gray-300">VAT:</span> {!! implode(' | ', $vatSummary) !!}
                                </div>
                            @endif
                        @endif

                        {{-- RTD Summary --}}
                        @if($file->supplier_detected === 'Udea' && isset($file->parsed_data['lines']) && count($file->parsed_data['lines']) > 0)
                            @php
                                $rtdService = app(\App\Services\RtdResolutionService::class);
                                $lines = $file->parsed_data['lines'];
                                $resolvedCount = 0;
                                $unresolvedCount = 0;
                                $unresolvedValue = 0;
                                foreach ($lines as $line) {
                                    $lineType = $line['line_type'] ?? 'unknown';
                                    if (!in_array($lineType, ['product_for_resale', 'unknown'])) continue;
                                    $articleCode = $line['article_code'] ?? null;
                                    $lineTotal = $rtdService->parseMonetaryValue($line['line_total'] ?? 0);
                                    $resolution = $rtdService->resolveArticleCode($articleCode);
                                    if ($resolution['status'] === 'resolved' && $rtdService->isValidIrishVatRate($resolution['vat_rate'] ?? null)) {
                                        $resolvedCount++;
                                    } else {
                                        $unresolvedCount++;
                                        $unresolvedValue += $lineTotal;
                                    }
                                }
                                $totalProductLines = $resolvedCount + $unresolvedCount;
                            @endphp
                            @if($totalProductLines > 0)
                                <div class="text-xs {{ $unresolvedCount > 0 ? 'text-yellow-400' : 'text-green-400' }}">
                                    RTD: {{ $resolvedCount }}/{{ $totalProductLines }} resolved
                                    @if($unresolvedCount > 0)
                                        <span class="text-red-400">({{ $unresolvedCount }} unresolved: &euro;{{ number_format($unresolvedValue, 2) }})</span>
                                    @endif
                                </div>
                            @endif
                        @endif

                        {{-- Warnings --}}
                        @if($file->anomaly_warnings && count($file->anomaly_warnings) > 0)
                            @php
                                $hasDateWarning = collect($file->anomaly_warnings)->contains(fn($w) =>
                                    str_contains(strtolower($w), 'date') || str_contains(strtolower($w), 'year')
                                );
                            @endphp
                            <div class="p-2.5 rounded-md border {{ $hasDateWarning ? 'bg-red-900/40 border-red-500' : 'bg-yellow-900/30 border-yellow-600' }}"
                                 id="warnings-block-mobile-{{ $file->id }}">
                                <p class="text-xs font-semibold {{ $hasDateWarning ? 'text-red-300' : 'text-yellow-300' }} mb-1">
                                    {{ count($file->anomaly_warnings) }} Warning(s)
                                </p>
                                <ul class="text-xs space-y-1">
                                    @foreach($file->anomaly_warnings as $wIdx => $warning)
                                        @php
                                            $isDateW = str_contains(strtolower($warning), 'date') || str_contains(strtolower($warning), 'year');
                                            $isVatW = str_contains(strtolower($warning), 'vat');
                                        @endphp
                                        @if($isVatW)
                                            <li id="vat-warning-mobile-{{ $file->id }}-{{ $wIdx }}"
                                                class="text-yellow-200 cursor-pointer active:bg-yellow-900/50 rounded p-1 -m-1"
                                                onclick="openVatFixModal({{ $file->id }}, '{{ $batch->batch_id }}', {{ $file->parsed_total_amount ?? 0 }}, {{ $wIdx }}, true)">
                                                <span class="underline decoration-dotted">&#9888; {{ $warning }}</span>
                                                <span class="ml-1 inline-block px-2 py-0.5 bg-blue-600 text-white text-xs rounded font-medium">Fix</span>
                                            </li>
                                        @else
                                            <li class="{{ $isDateW ? 'text-red-300 font-semibold' : 'text-yellow-200' }}">
                                                {{ $isDateW ? '📅' : '•' }} {{ $warning }}
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Amazon Payment Adjustment --}}
                        @php
                            $adjustmentService = app(\App\Services\AmazonPaymentAdjustmentService::class);
                            $needsAdjustment = $adjustmentService->needsPaymentAdjustment($file);
                            $adjustmentData = $needsAdjustment ? $adjustmentService->getAdjustmentData($file) : [];
                        @endphp
                        @if($needsAdjustment)
                            <div class="p-3 bg-yellow-900/30 border border-yellow-600 rounded-md">
                                <div class="flex items-center mb-2">
                                    <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-yellow-300 text-sm font-medium">Payment Adjustment Needed</span>
                                </div>
                                <div class="text-xs text-gray-300 space-y-1">
                                    @if($adjustmentData['is_pre_parsing'] ?? false)
                                        <div class="text-blue-300">📋 Amazon invoice detected from filename</div>
                                        <div>Enter the EUR amount you paid</div>
                                    @elseif($adjustmentData['gbp_amounts_detected'] ?? false)
                                        <div class="bg-gray-700 rounded p-2 mb-2">
                                            <div class="font-medium text-gray-200 mb-1">Invoice Amounts (GBP):</div>
                                            @if($adjustmentData['invoice_date'])
                                            <div class="text-blue-300 font-medium text-sm mb-1">📅 {{ $adjustmentData['invoice_date'] }}</div>
                                            @endif
                                            @if($adjustmentData['gbp_total'] > 0)
                                            <div>GBP Total: £{{ number_format($adjustmentData['gbp_total'], 2) }}</div>
                                            @endif
                                        </div>
                                        @if($adjustmentData['eur_vat_detected'] ?? false)
                                        <div class="bg-green-900/30 rounded p-2">
                                            <div class="font-medium text-green-300 mb-1">Detected EUR Amounts:</div>
                                            <div>EUR VAT: &euro;{{ number_format($adjustmentData['eur_vat_amount'], 2) }}</div>
                                        </div>
                                        @endif
                                    @endif
                                    <div class="mt-2">
                                        <label class="block text-yellow-300 text-xs font-medium mb-1">Actual Amount Paid (from bank):</label>
                                        <div class="flex items-center space-x-2">
                                            <input type="number"
                                                   step="0.01" min="0"
                                                   name="actual_payment[{{ $file->id }}]"
                                                   id="actual_payment_mobile_{{ $file->id }}"
                                                   class="w-full px-2 py-1 text-xs bg-gray-800 border border-gray-600 rounded text-white focus:border-yellow-500 focus:outline-none"
                                                   placeholder="22.65"
                                                   onchange="updatePaymentPreview({{ $file->id }}, {{ $adjustmentData['eur_vat_amount'] ?? 0 }})"
                                                   onkeyup="if(event.key === 'Enter') updatePaymentPreview({{ $file->id }}, {{ $adjustmentData['eur_vat_amount'] ?? 0 }})" />
                                            <span class="text-xs text-gray-400">EUR</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Actions panel --}}
                    <div class="mt-3">
                        <div class="flex flex-wrap gap-2">
                            @if($file->tempFileExists() && $file->isViewable())
                            <button onclick="previewFile({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-blue-400 hover:bg-gray-500">
                                View Invoice
                            </button>
                            @endif
                            @if($file->canBeSplit())
                            <button onclick="splitPdf({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded {{ $file->page_count > 1 ? 'bg-amber-600 text-white font-semibold' : 'bg-gray-600 text-green-400' }} hover:opacity-80">
                                @if($file->page_count > 1) ⚠ Split @else Split @endif
                            </button>
                            @endif
                            @if($file->status === 'failed')
                            <button onclick="retryFile({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-amber-400 hover:bg-gray-500">
                                🔄 Retry
                            </button>
                            <button onclick="removeFile({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-red-400 hover:bg-gray-500">
                                Remove
                            </button>
                            @elseif($file->status === 'uploaded')
                            <button onclick="removeFile({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-red-400 hover:bg-gray-500">
                                Remove
                            </button>
                            @elseif($file->status === 'review' && $file->error_message && str_contains(strtolower($file->error_message), 'duplicate'))
                            <button onclick="removeDuplicateFile({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-yellow-400 hover:bg-gray-500">
                                Delete Duplicate
                            </button>
                            @endif
                            @if($file->status === 'parsed' || $file->status === 'review')
                            <button onclick="viewParsedData({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-green-400 hover:bg-gray-500">
                                View Data
                            </button>
                            @endif
                            @if($file->isPdf())
                            <button onclick="parseUdeaInvoice({{ $file->id }})"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-purple-400 hover:bg-gray-500">
                                Parse Udea
                            </button>
                            @endif
                            <button @click="showEdit = !showEdit"
                                    class="px-3 py-1.5 text-xs rounded bg-gray-600 text-blue-400 hover:bg-gray-500">
                                Edit/Enter Data
                            </button>
                        </div>
                    </div>

                    {{-- Inline Edit Form (expandable) --}}
                    <div x-show="showEdit" x-cloak x-collapse class="mt-3">
                        <div class="bg-gray-800 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-100 mb-3">
                                Edit Invoice Data
                            </h4>
                            <form onsubmit="saveParsedData(event, {{ $file->id }}, '{{ $batch->batch_id }}')">
                                <div class="space-y-3">
                                    {{-- Supplier --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-300 mb-1">Supplier <span class="text-red-400">*</span></label>
                                        @php
                                            $matchedSupplier = $file->supplier_detected ?? ($file->parsed_data['supplier_name'] ?? '');
                                        @endphp
                                        <select name="supplier_name"
                                                class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none"
                                                required>
                                            <option value="">-- Select Supplier --</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->name }}" @selected($matchedSupplier === $supplier->name)>
                                                    {{ $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @php
                                            $detectedSupplier = $file->supplier_detected ?? ($file->parsed_data['supplier_name'] ?? '');
                                            $isInDropdown = $suppliers->contains('name', $detectedSupplier);
                                            $customFieldValue = !$isInDropdown ? $detectedSupplier : '';
                                        @endphp
                                        <input type="text" name="supplier_name_custom"
                                               placeholder="Or type new supplier"
                                               class="w-full px-3 py-2 mt-1 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none"
                                               value="{{ $customFieldValue }}">
                                    </div>
                                    {{-- Invoice Ref + Date --}}
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-300 mb-1">Invoice Ref</label>
                                            <input type="text" name="supplier_invoice_reference"
                                                   value="{{ $file->parsed_invoice_number ?? ($file->parsed_data['supplier_invoice_reference'] ?? '') }}"
                                                   class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-300 mb-1">Date</label>
                                            <input type="date" name="invoice_date"
                                                   value="{{ $file->parsed_invoice_date ? \Carbon\Carbon::parse($file->parsed_invoice_date)->format('Y-m-d') : ($file->parsed_data['invoice_date'] ?? '') }}"
                                                   class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                        </div>
                                    </div>
                                    {{-- VAT fields --}}
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-300 mb-1">VAT 0% Net</label>
                                            <input type="number" step="0.01" min="0" name="vat_0_net"
                                                   value="{{ $file->parsed_data['vat_breakdown']['vat_0']['net'] ?? 0 }}"
                                                   class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-300 mb-1">VAT 9% Net</label>
                                            <input type="number" step="0.01" min="0" name="vat_9_net"
                                                   value="{{ $file->parsed_data['vat_breakdown']['vat_9']['net'] ?? 0 }}"
                                                   class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-300 mb-1">VAT 13.5% Net</label>
                                            <input type="number" step="0.01" min="0" name="vat_13_5_net"
                                                   value="{{ $file->parsed_data['vat_breakdown']['vat_13_5']['net'] ?? 0 }}"
                                                   class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-300 mb-1">VAT 23% Net</label>
                                            <input type="number" step="0.01" min="0" name="vat_23_net"
                                                   value="{{ $file->parsed_data['vat_breakdown']['vat_23']['net'] ?? 0 }}"
                                                   class="w-full px-3 py-2 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                        </div>
                                    </div>
                                    {{-- Checkboxes --}}
                                    <div class="flex space-x-4">
                                        <label class="flex items-center text-xs text-gray-300">
                                            <input type="checkbox" name="is_tax_free" value="1"
                                                   {{ ($file->is_tax_free || ($file->parsed_data['is_tax_free'] ?? false)) ? 'checked' : '' }}
                                                   class="mr-1.5">
                                            Tax Free
                                        </label>
                                        <label class="flex items-center text-xs text-gray-300">
                                            <input type="checkbox" name="is_credit_note" value="1"
                                                   {{ ($file->is_credit_note || ($file->parsed_data['is_credit_note'] ?? false)) ? 'checked' : '' }}
                                                   class="mr-1.5">
                                            Credit Note
                                        </label>
                                    </div>
                                    {{-- Buttons --}}
                                    <div class="flex gap-2 pt-2">
                                        <button type="button" @click="showEdit = false"
                                                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white text-sm font-bold py-2 rounded">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-bold py-2 rounded">
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Desktop Table Layout --}}
            <div class="hidden md:block overflow-x-auto">
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
                        <tr class="hover:bg-gray-700/50 @if($file->isPdf() && $file->page_count > 1) bg-amber-900/20 border-l-4 border-amber-500 @endif">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    @if($file->isPdf())
                                        <svg class="w-5 h-5 @if($file->page_count > 1) text-amber-400 @else text-red-400 @endif mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                                        </svg>
                                    @elseif($file->isImage())
                                        <svg class="w-5 h-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif($file->isWordDocument())
                                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2zm8-13V2l4 4h-3a1 1 0 01-1-1z"/>
                                        </svg>
                                    @elseif($file->isExcelDocument())
                                        <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2zm8-13V2l4 4h-3a1 1 0 01-1-1z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H4v10h12V5h-2a1 1 0 100-2 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-gray-200">{{ $file->original_filename }}</span>
                                    @if($file->isPdf() && $file->page_count > 0)
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full @if($file->page_count > 1) bg-amber-900 text-amber-300 font-semibold @else bg-blue-900 text-blue-300 @endif">
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

                                {{-- Parsed Invoice Summary --}}
                                @if(in_array($file->status, ['parsed', 'review', 'amazon_pending', 'completed']))
                                    <div class="mt-2">
                                        @if($file->supplier_detected)
                                            <div class="text-white text-sm font-bold">{{ $file->supplier_detected }}</div>
                                        @endif
                                        <div class="flex items-center gap-3 mt-1">
                                            @if($file->parsed_invoice_date)
                                                @php
                                                    $parsedDate = \Carbon\Carbon::parse($file->parsed_invoice_date);
                                                    $monthsDiff = abs($parsedDate->diffInMonths(now()));
                                                    $isSuspiciousDate = $monthsDiff > 2 || $parsedDate->year < now()->year - 1 || $parsedDate->isAfter(now()->addDays(7));
                                                @endphp
                                                <span class="text-sm {{ $isSuspiciousDate ? 'px-1.5 py-0.5 bg-red-900 border border-red-500 rounded text-red-300 font-bold' : 'text-gray-300' }}">
                                                    {{ $parsedDate->format('d/m/Y') }}
                                                    @if($isSuspiciousDate) ⚠@endif
                                                </span>
                                            @endif
                                            @if($file->parsed_invoice_number)
                                                <span class="text-sm text-gray-400 font-mono">#{{ $file->parsed_invoice_number }}</span>
                                            @endif
                                        </div>
                                        @if($file->parsed_total_amount)
                                            @php
                                                $vatData = $file->parsed_vat_data;
                                                $totalVat = 0;
                                                $totalNet = 0;
                                                if ($vatData) {
                                                    foreach ($vatData as $rate) {
                                                        if (is_array($rate)) {
                                                            $totalNet += $rate['net'] ?? 0;
                                                            $totalVat += $rate['vat'] ?? 0;
                                                        }
                                                    }
                                                }
                                            @endphp
                                            <div class="flex items-center gap-4 mt-1 text-sm">
                                                <span class="text-gray-300">Net: <span class="text-gray-100 font-semibold">€{{ number_format($totalNet > 0 ? $totalNet : $file->parsed_total_amount, 2) }}</span></span>
                                                <span class="text-gray-300">VAT: <span class="text-gray-100 font-semibold">€{{ number_format($totalVat, 2) }}</span></span>
                                                <span class="text-green-400 font-bold">Total: €{{ number_format($file->parsed_total_amount, 2) }}</span>
                                            </div>
                                            @if($file->parsed_vat_data)
                                                @php
                                                    $vatSummary = [];
                                                    foreach (['vat_0' => '0%', 'vat_9' => '9%', 'vat_13_5' => '13.5%', 'vat_23' => '23%'] as $key => $rate) {
                                                        if (isset($file->parsed_vat_data[$key])) {
                                                            $netAmount = is_array($file->parsed_vat_data[$key])
                                                                ? ($file->parsed_vat_data[$key]['net'] ?? 0)
                                                                : $file->parsed_vat_data[$key];
                                                            if ($netAmount > 0) {
                                                                $vatSummary[] = $rate . ' (€' . number_format($netAmount, 2) . ')';
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                @if(count($vatSummary) > 0)
                                                    <div class="text-gray-400 text-xs mt-1">
                                                        VAT Rates: {{ implode(' | ', $vatSummary) }}
                                                    </div>
                                                @endif
                                            @endif
                                        @endif

                                        {{-- RTD Summary for Udea invoices with line items --}}
                                        @if($file->supplier_detected === 'Udea' && isset($file->parsed_data['lines']) && count($file->parsed_data['lines']) > 0)
                                            @php
                                                $rtdService = app(\App\Services\RtdResolutionService::class);
                                                $lines = $file->parsed_data['lines'];
                                                $resolvedCount = 0;
                                                $unresolvedCount = 0;
                                                $unresolvedValue = 0;

                                                foreach ($lines as $line) {
                                                    $lineType = $line['line_type'] ?? 'unknown';
                                                    if (!in_array($lineType, ['product_for_resale', 'unknown'])) {
                                                        continue;
                                                    }
                                                    $articleCode = $line['article_code'] ?? null;
                                                    $lineTotal = $rtdService->parseMonetaryValue($line['line_total'] ?? 0);
                                                    $resolution = $rtdService->resolveArticleCode($articleCode);

                                                    if ($resolution['status'] === 'resolved' && $rtdService->isValidIrishVatRate($resolution['vat_rate'] ?? null)) {
                                                        $resolvedCount++;
                                                    } else {
                                                        $unresolvedCount++;
                                                        $unresolvedValue += $lineTotal;
                                                    }
                                                }
                                                $totalProductLines = $resolvedCount + $unresolvedCount;
                                            @endphp
                                            @if($totalProductLines > 0)
                                                <div class="mt-1 text-xs {{ $unresolvedCount > 0 ? 'text-yellow-400' : 'text-green-400' }}">
                                                    RTD: {{ $resolvedCount }}/{{ $totalProductLines }} resolved
                                                    @if($unresolvedCount > 0)
                                                        <span class="text-red-400">({{ $unresolvedCount }} unresolved: €{{ number_format($unresolvedValue, 2) }})</span>
                                                    @endif
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endif

                                {{-- Warnings Block --}}
                                @if($file->anomaly_warnings && count($file->anomaly_warnings) > 0)
                                    @php
                                        $hasDateWarning = collect($file->anomaly_warnings)->contains(fn($w) =>
                                            str_contains(strtolower($w), 'date') || str_contains(strtolower($w), 'year')
                                        );
                                        $hasVatWarning = collect($file->anomaly_warnings)->contains(fn($w) =>
                                            str_contains(strtolower($w), 'vat')
                                        );
                                    @endphp
                                    <div class="mt-2 p-2.5 rounded-md border {{ $hasDateWarning ? 'bg-red-900/40 border-red-500' : 'bg-yellow-900/30 border-yellow-600' }}"
                                         id="warnings-block-{{ $file->id }}">
                                        <div class="flex items-start">
                                            <svg class="w-4 h-4 {{ $hasDateWarning ? 'text-red-400' : 'text-yellow-400' }} mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="text-xs font-semibold {{ $hasDateWarning ? 'text-red-300' : 'text-yellow-300' }} mb-1">
                                                    {{ count($file->anomaly_warnings) }} Warning(s)
                                                </p>
                                                <ul class="text-xs space-y-0.5">
                                                    @foreach($file->anomaly_warnings as $wIdx => $warning)
                                                        @php
                                                            $isDateW = str_contains(strtolower($warning), 'date') || str_contains(strtolower($warning), 'year');
                                                            $isVatW = str_contains(strtolower($warning), 'vat');
                                                        @endphp
                                                        @if($isVatW)
                                                            <li id="vat-warning-{{ $file->id }}-{{ $wIdx }}"
                                                                class="text-yellow-200 cursor-pointer hover:text-white transition-colors"
                                                                onclick="openVatFixModal({{ $file->id }}, '{{ $batch->batch_id }}', {{ $file->parsed_total_amount ?? 0 }}, {{ $wIdx }})">
                                                                <span class="underline decoration-dotted">&#9888; {{ $warning }}</span>
                                                                <span class="ml-1 text-blue-400 text-xs">[Fix]</span>
                                                            </li>
                                                        @else
                                                            <li class="{{ $isDateW ? 'text-red-300 font-semibold' : 'text-yellow-200' }}">
                                                                {{ $isDateW ? '📅' : '•' }} {{ $warning }}
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
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
                                <div class="flex flex-wrap gap-1">
                                    @if($file->tempFileExists() && $file->isViewable())
                                    <button onclick="previewFile({{ $file->id }})"
                                            class="text-blue-400 hover:text-blue-300 text-sm">
                                        View Invoice
                                    </button>
                                    @endif
                                    @if($file->canBeSplit())
                                    <button onclick="splitPdf({{ $file->id }})" 
                                            class="@if($file->page_count > 1) bg-amber-600 hover:bg-amber-700 text-white px-2 py-1 rounded text-xs font-semibold @else text-green-400 hover:text-green-300 text-sm @endif">
                                        @if($file->page_count > 1) ⚠ Split @else Split @endif
                                    </button>
                                    @endif
                                    @if($file->status === 'failed')
                                    <button onclick="retryFile({{ $file->id }})" 
                                            class="text-amber-400 hover:text-amber-300 text-sm mr-2">
                                        🔄 Retry
                                    </button>
                                    <button onclick="removeFile({{ $file->id }})" 
                                            class="text-red-400 hover:text-red-300 text-sm">
                                        Remove
                                    </button>
                                    @elseif($file->status === 'uploaded')
                                    <button onclick="removeFile({{ $file->id }})" 
                                            class="text-red-400 hover:text-red-300 text-sm">
                                        Remove
                                    </button>
                                    @elseif($file->status === 'review' && $file->error_message && str_contains(strtolower($file->error_message), 'duplicate'))
                                    <button onclick="removeDuplicateFile({{ $file->id }})" 
                                            class="text-yellow-400 hover:text-yellow-300 text-sm">
                                        Delete Duplicate
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
                                    <span class="text-yellow-400 text-xs">
                                        ⚠ {{ count($file->anomaly_warnings) }} Warning(s)
                                    </span>
                                    @endif
                                    @if($file->status === 'parsed' || $file->status === 'review')
                                    <button onclick="viewParsedData({{ $file->id }})"
                                            class="text-green-400 hover:text-green-300 text-sm">
                                        View Data
                                    </button>
                                    @endif
                                    @if($file->isPdf())
                                    <button onclick="parseUdeaInvoice({{ $file->id }})"
                                            class="text-purple-400 hover:text-purple-300 text-sm ml-2"
                                            title="Parse this PDF using the Udea invoice parser (debug mode)">
                                        Parse Udea
                                    </button>
                                    @endif
                                    <button x-data onclick="document.getElementById('edit-form-{{ $file->id }}').classList.toggle('hidden')"
                                            class="text-blue-400 hover:text-blue-300 text-sm ml-2">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit/Enter Data
                                    </button>
                                </div>
                            </td>
                        </tr>
                        {{-- Collapsible Edit Form Row --}}
                        <tr id="edit-form-{{ $file->id }}" class="hidden bg-gray-700/50">
                            <td colspan="5" class="p-6">
                                <div class="bg-gray-800 rounded-lg p-6">
                                    <h4 class="text-lg font-semibold text-gray-100 mb-4">
                                        Edit/Enter Invoice Data - {{ $file->original_filename }}
                                    </h4>

                                    <form id="parsed-data-form-{{ $file->id }}" onsubmit="saveParsedData(event, {{ $file->id }}, '{{ $batch->batch_id }}')">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {{-- Supplier Selection --}}
                                            <div class="col-span-2">
                                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                                    Supplier <span class="text-red-400">*</span>
                                                </label>
                                                @php
                                                    $matchedSupplier = $file->supplier_detected ?? ($file->parsed_data['supplier_name'] ?? '');
                                                @endphp
                                                <select name="supplier_name"
                                                        id="supplier_dropdown_{{ $file->id }}"
                                                        class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none"
                                                        onchange="if(this.value) document.getElementById('supplier_custom_{{ $file->id }}').value = ''"
                                                        required>
                                                    <option value="">-- Select Supplier or Type New --</option>
                                                    @foreach($suppliers as $supplier)
                                                        <option value="{{ $supplier->name }}"
                                                                @selected($matchedSupplier === $supplier->name)>
                                                            {{ $supplier->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if($matchedSupplier)
                                                <script>
                                                    document.getElementById('supplier_dropdown_{{ $file->id }}').value = @json($matchedSupplier);
                                                </script>
                                                @endif
                                                <p class="text-xs text-gray-400 mt-1">Or type a new supplier name directly</p>
                                                @php
                                                    // Only pre-populate custom field if supplier is not in dropdown
                                                    $detectedSupplier = $file->supplier_detected ?? ($file->parsed_data['supplier_name'] ?? '');
                                                    $isInDropdown = $suppliers->contains('name', $detectedSupplier);
                                                    $customFieldValue = !$isInDropdown ? $detectedSupplier : '';
                                                @endphp
                                                <input type="text"
                                                       name="supplier_name_custom"
                                                       id="supplier_custom_{{ $file->id }}"
                                                       placeholder="Or type new supplier name"
                                                       class="w-full px-3 py-2 mt-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none text-sm"
                                                       oninput="if(this.value) { this.form.supplier_name.value = this.value; document.getElementById('supplier_dropdown_{{ $file->id }}').value = ''; }"
                                                       value="{{ $customFieldValue }}">
                                            </div>

                                            {{-- Supplier Invoice Reference --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                                    Supplier Invoice Reference
                                                    <span class="text-xs text-gray-400 font-normal">(optional)</span>
                                                </label>
                                                <input type="text"
                                                       name="supplier_invoice_reference"
                                                       value="{{ $file->parsed_invoice_number ?? ($file->parsed_data['supplier_invoice_reference'] ?? '') }}"
                                                       placeholder="e.g., INV-2024-001234"
                                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                                <p class="text-xs text-gray-400 mt-1">The supplier's original invoice number (if available)</p>
                                            </div>

                                            {{-- Invoice Date --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-300 mb-2">Invoice Date</label>
                                                <input type="date"
                                                       name="invoice_date"
                                                       value="{{ $file->parsed_invoice_date ? \Carbon\Carbon::parse($file->parsed_invoice_date)->format('Y-m-d') : ($file->parsed_data['invoice_date'] ?? '') }}"
                                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                            </div>

                                            {{-- VAT 0% Net --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-300 mb-2">VAT 0% (Net Amount)</label>
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       name="vat_0_net"
                                                       value="{{ $file->parsed_data['vat_breakdown']['vat_0']['net'] ?? 0 }}"
                                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                            </div>

                                            {{-- VAT 9% Net --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-300 mb-2">VAT 9% (Net Amount)</label>
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       name="vat_9_net"
                                                       value="{{ $file->parsed_data['vat_breakdown']['vat_9']['net'] ?? 0 }}"
                                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                            </div>

                                            {{-- VAT 13.5% Net --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-300 mb-2">VAT 13.5% (Net Amount)</label>
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       name="vat_13_5_net"
                                                       value="{{ $file->parsed_data['vat_breakdown']['vat_13_5']['net'] ?? 0 }}"
                                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                            </div>

                                            {{-- VAT 23% Net --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-300 mb-2">VAT 23% (Net Amount)</label>
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       name="vat_23_net"
                                                       value="{{ $file->parsed_data['vat_breakdown']['vat_23']['net'] ?? 0 }}"
                                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white focus:border-blue-500 focus:outline-none">
                                            </div>

                                            {{-- Checkboxes --}}
                                            <div class="col-span-2 flex flex-col sm:flex-row sm:space-x-6 space-y-2 sm:space-y-0">
                                                <label class="flex items-center text-sm text-gray-300">
                                                    <input type="checkbox"
                                                           name="is_tax_free"
                                                           value="1"
                                                           {{ ($file->is_tax_free || ($file->parsed_data['is_tax_free'] ?? false)) ? 'checked' : '' }}
                                                           class="mr-2">
                                                    Tax Free
                                                </label>
                                                <label class="flex items-center text-sm text-gray-300">
                                                    <input type="checkbox"
                                                           name="is_credit_note"
                                                           value="1"
                                                           {{ ($file->is_credit_note || ($file->parsed_data['is_credit_note'] ?? false)) ? 'checked' : '' }}
                                                           class="mr-2">
                                                    Credit Note
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mt-6 flex justify-end space-x-2">
                                            <button type="button"
                                                    onclick="document.getElementById('edit-form-{{ $file->id }}').classList.add('hidden')"
                                                    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                                Save Data
                                            </button>
                                        </div>
                                    </form>
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
            <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3">
                <button onclick="createInvoicesFromReview()"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold text-sm py-2 px-4 rounded">
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

        {{-- VAT Quick Fix Modal --}}
        <div id="vatFixModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-gray-800 rounded-lg max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-100">Quick VAT Fix</h3>
                        <button onclick="closeVatFixModal()" class="text-gray-400 hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        {{-- Amount Display (editable) --}}
                        <div class="bg-gray-700 rounded p-3">
                            <label for="vatFixAmountInput" class="text-gray-400 text-xs mb-1 block">Amount</label>
                            <div class="flex items-center">
                                <span class="text-white text-2xl font-bold mr-1">&euro;</span>
                                <input type="number" id="vatFixAmountInput" step="0.01" min="0"
                                       class="bg-gray-600 border border-gray-500 rounded px-3 py-1 text-white text-2xl font-bold w-40 focus:border-blue-500 focus:outline-none"
                                       onchange="vatFixState.amount = parseFloat(this.value) || 0; updateVatPreview()"
                                       oninput="vatFixState.amount = parseFloat(this.value) || 0; updateVatPreview()">
                            </div>
                        </div>

                        {{-- Is this Gross or Net? --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">This amount is:</label>
                            <div class="flex space-x-3">
                                <label class="flex items-center px-4 py-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors flex-1">
                                    <input type="radio" name="vat_amount_type" value="gross" checked
                                           class="mr-2 text-blue-500" onchange="updateVatPreview()">
                                    <span class="text-gray-200 text-sm">Gross (inc. VAT)</span>
                                </label>
                                <label class="flex items-center px-4 py-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors flex-1">
                                    <input type="radio" name="vat_amount_type" value="net"
                                           class="mr-2 text-blue-500" onchange="updateVatPreview()">
                                    <span class="text-gray-200 text-sm">Net (ex. VAT)</span>
                                </label>
                            </div>
                        </div>

                        {{-- VAT Rate Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Correct VAT Rate:</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center px-3 py-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors">
                                    <input type="radio" name="vat_rate_fix" value="23"
                                           class="mr-2 text-blue-500" onchange="updateVatPreview()">
                                    <span class="text-gray-200 text-sm">23% (Standard)</span>
                                </label>
                                <label class="flex items-center px-3 py-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors">
                                    <input type="radio" name="vat_rate_fix" value="13.5"
                                           class="mr-2 text-blue-500" onchange="updateVatPreview()">
                                    <span class="text-gray-200 text-sm">13.5% (Reduced)</span>
                                </label>
                                <label class="flex items-center px-3 py-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors">
                                    <input type="radio" name="vat_rate_fix" value="9"
                                           class="mr-2 text-blue-500" onchange="updateVatPreview()">
                                    <span class="text-gray-200 text-sm">9% (2nd Reduced)</span>
                                </label>
                                <label class="flex items-center px-3 py-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 transition-colors">
                                    <input type="radio" name="vat_rate_fix" value="0" checked
                                           class="mr-2 text-blue-500" onchange="updateVatPreview()">
                                    <span class="text-gray-200 text-sm">0% (Zero/Exempt)</span>
                                </label>
                            </div>
                        </div>

                        {{-- Calculated Preview --}}
                        <div id="vatFixPreview" class="hidden bg-blue-900/30 border border-blue-600 rounded p-3 text-sm">
                            <p class="text-blue-300 font-semibold mb-1">Calculated Breakdown:</p>
                            <div class="text-gray-300 space-y-0.5">
                                <div>Net: <span id="vatFixNet" class="text-white font-mono"></span></div>
                                <div>VAT: <span id="vatFixVat" class="text-white font-mono"></span></div>
                                <div>Gross: <span id="vatFixGross" class="text-white font-mono"></span></div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex justify-end space-x-3 pt-2">
                            <button onclick="closeVatFixModal()"
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded transition-colors">
                                Cancel
                            </button>
                            <button onclick="saveVatFix()" id="vatFixSaveBtn"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded transition-colors">
                                Apply Fix
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- PDF Split Modal --}}
        <div id="splitPdfModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-gray-800 rounded-lg w-full max-w-full sm:max-w-2xl md:max-w-4xl lg:max-w-6xl max-h-[95vh] overflow-hidden">
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
                                    <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-2 sm:space-y-0">
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
                    <div class="border-t border-gray-700 p-4 sm:p-6">
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                            <button onclick="closeSplitPdfModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm">
                                Cancel
                            </button>
                            <button id="confirmSplit" onclick="confirmPdfSplit()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm" disabled>
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

        function removeDuplicateFile(fileId) {
            if (confirm('Delete this duplicate invoice?\n\nThis file appears to match an existing invoice in the system. Deleting it will prevent creating a duplicate.\n\nAre you sure you want to delete this file?')) {
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
                        alert(data.error || 'Failed to delete duplicate file');
                    }
                });
            }
        }

        function retryFile(fileId) {
            if (confirm('Retry parsing this failed invoice?\n\nThis will:\n• Reset the file status to uploaded\n• Clear the error message\n• Re-run the parser with any updated code\n\nAre you sure you want to retry?')) {
                fetch(`/invoices/bulk-upload/${batchId}/file/${fileId}/retry`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'File queued for retry processing');
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to retry file');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while retrying the file');
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
            // Get payment inputs (used for Amazon invoices)
            const paymentInputs = document.querySelectorAll('input[name^="actual_payment["]');

            @if($hasAmazonPending)
            // Check that Amazon invoices have payment amounts entered
            const amazonMissingPayments = [];

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

            // Collect payment adjustment data (if any Amazon invoices)
            const paymentData = {};
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
                    let message = data.message;

                    // Add RTD stats if present
                    if (data.rtd_stats && data.rtd_stats.udea_invoices > 0) {
                        message += '\n\n--- RTD Summary ---';
                        message += '\nUdea invoices: ' + data.rtd_stats.udea_invoices;
                        message += '\nResolved lines: ' + data.rtd_stats.resolved_lines;

                        if (data.rtd_stats.unresolved_lines > 0) {
                            message += '\nUnresolved lines: ' + data.rtd_stats.unresolved_lines;
                            message += ' (\u20AC' + data.rtd_stats.unresolved_value.toFixed(2) + ')';
                            message += '\n\nVisit RTD Fallbacks to assign VAT rates.';
                        } else {
                            message += '\n\nAll lines resolved successfully!';
                        }
                    }

                    alert(message);
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
        // Only refresh if there are actually files to process (prevents infinite reload loop on empty batches)
        @if($files->count() > 0 && ($batch->status === 'processing' || $files->whereIn('status', ['uploaded', 'parsing'])->count() > 0))
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
            
            // Perform split with timeout handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout for large PDFs
            
            fetch(`/invoices/bulk-upload/${batchId}/file/${currentFileForSplit}/split`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    page_ranges: pageRanges
                }),
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    // Try to get error message from response
                    return response.text().then(text => {
                        let errorMsg = 'Server error';
                        try {
                            const json = JSON.parse(text);
                            errorMsg = json.error || json.message || errorMsg;
                        } catch (e) {
                            // Response wasn't JSON, use status text
                            errorMsg = `Server error (${response.status})`;
                        }
                        throw new Error(errorMsg);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(`Successfully split PDF into ${data.split_count} files`);
                    closeSplitPdfModal();
                    window.location.reload(); // Reload to show new files
                } else {
                    alert('Failed to split PDF: ' + (data.error || 'Unknown error'));
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = 'Split PDF';
                }
            })
            .catch(error => {
                console.error('Error splitting PDF:', error);
                
                // Check if it was a timeout
                if (error.name === 'AbortError') {
                    alert('PDF split is taking longer than expected. Please refresh the page to see if it completed.');
                } else {
                    alert('Failed to split PDF: ' + error.message);
                }
                
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

        // Save parsed data (edit form submission)
        function saveParsedData(event, fileId, batchId) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            // Convert FormData to JSON
            const data = {
                supplier_invoice_reference: formData.get('supplier_invoice_reference'),
                invoice_date: formData.get('invoice_date'),
                supplier_name: formData.get('supplier_name'),
                is_tax_free: formData.get('is_tax_free') ? true : false,
                is_credit_note: formData.get('is_credit_note') ? true : false,
                vat_0_net: formData.get('vat_0_net'),
                vat_9_net: formData.get('vat_9_net'),
                vat_13_5_net: formData.get('vat_13_5_net'),
                vat_23_net: formData.get('vat_23_net'),
            };

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            fetch(`/invoices/bulk-upload/${batchId}/file/${fileId}/parsed-data`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Data saved successfully!');
                    // Close the form
                    document.getElementById(`edit-form-${fileId}`).classList.add('hidden');
                    // Reload page to show updated data
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save data');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving data');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        // Parse Udea Invoice (debug mode)
        function parseUdeaInvoice(fileId) {
            // Show loading state
            const btn = event.target;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Parsing...';

            fetch(`/invoices/bulk-upload/${batchId}/file/${fileId}/parse-udea`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = originalText;

                if (data.success) {
                    // Show results in a modal
                    showUdeaParseResults(data);
                } else {
                    // Show error in modal for better debugging
                    let errorContent = '<div class="space-y-4">';
                    errorContent += '<div class="bg-red-900/30 border border-red-700 p-4 rounded">';
                    errorContent += '<h4 class="text-red-400 font-semibold mb-2">Parser Error</h4>';
                    errorContent += `<p class="text-red-300">${data.error || 'Unknown error'}</p>`;
                    if (data.json_error) {
                        errorContent += `<p class="text-red-300 text-sm mt-2">JSON Error: ${data.json_error}</p>`;
                    }
                    if (data.command) {
                        errorContent += `<p class="text-gray-400 text-xs mt-2">Command: ${data.command}</p>`;
                    }
                    errorContent += '</div>';

                    if (data.raw_output) {
                        errorContent += '<div class="bg-gray-700 p-4 rounded">';
                        errorContent += '<h4 class="text-gray-300 font-semibold mb-2">Raw Parser Output</h4>';
                        errorContent += '<pre class="text-xs text-gray-400 overflow-auto max-h-96 whitespace-pre-wrap">' +
                            (data.raw_output || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
                        errorContent += '</div>';
                        console.log('Raw parser output:', data.raw_output);
                    }

                    errorContent += '</div>';
                    document.getElementById('parsedDataContent').innerHTML = errorContent;
                    document.getElementById('parsedDataModal').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.textContent = originalText;
                alert('An error occurred while parsing the invoice');
            });
        }

        // Show Udea parse results in a modal
        function showUdeaParseResults(data) {
            const result = data.parser_result;
            let content = '<div class="space-y-4">';

            // Header section
            content += '<div class="bg-gray-700 p-4 rounded">';
            content += '<h4 class="text-gray-300 font-semibold mb-2">Invoice Header</h4>';
            content += `<p class="text-gray-400"><span class="font-medium text-gray-200">Invoice Number:</span> ${result.header?.invoice_number || 'Not found'}</p>`;
            content += `<p class="text-gray-400"><span class="font-medium text-gray-200">Invoice Date:</span> ${result.header?.invoice_date || 'Not found'}</p>`;
            content += `<p class="text-gray-400"><span class="font-medium text-gray-200">Total Excl VAT:</span> €${(result.header?.total_excl_vat || 0).toFixed(2)}</p>`;
            content += `<p class="text-gray-400"><span class="font-medium text-gray-200">VAT Amount:</span> €${(result.header?.vat_amount || 0).toFixed(2)}</p>`;
            content += `<p class="text-gray-400"><span class="font-medium text-gray-200">Zero VAT:</span> ${result.header?.is_zero_vat ? 'Yes' : 'No'}</p>`;
            content += '</div>';

            // Validation section
            if (result.validation) {
                const v = result.validation;
                const validClass = v.is_valid ? 'text-green-400' : 'text-red-400';
                content += '<div class="bg-gray-700 p-4 rounded">';
                content += '<h4 class="text-gray-300 font-semibold mb-2">Validation</h4>';
                content += `<p class="${validClass} font-semibold">${v.is_valid ? '✓ Totals Match' : '✗ Totals Mismatch'}</p>`;
                content += `<p class="text-gray-400">Products: €${v.products_total?.toFixed(2) || '0.00'}</p>`;
                content += `<p class="text-gray-400">Barrels: €${v.barrels_total?.toFixed(2) || '0.00'}</p>`;
                content += `<p class="text-gray-400">Costs: €${v.costs_total?.toFixed(2) || '0.00'}</p>`;
                content += `<p class="text-gray-400">Calculated Total: €${v.calculated_total?.toFixed(2) || '0.00'}</p>`;
                content += `<p class="text-gray-400">Invoice Total: €${v.invoice_total?.toFixed(2) || '0.00'}</p>`;
                if (!v.is_valid) {
                    content += `<p class="text-red-400">Difference: €${v.difference?.toFixed(2) || '0.00'}</p>`;
                }
                content += '</div>';
            }

            // RTD Preview section
            if (data.rtd_preview) {
                const rtd = data.rtd_preview;
                const summary = rtd.summary;
                const breakdown = rtd.breakdown;
                const isComplete = summary.is_complete;

                content += '<div class="' + (isComplete ? 'bg-green-900/30 border border-green-700' : 'bg-purple-900/30 border border-purple-700') + ' p-4 rounded">';
                content += '<div class="flex justify-between items-center mb-3">';
                content += '<h4 class="text-gray-100 font-semibold">RTD Preview (Goods for Resale)</h4>';

                if (isComplete) {
                    content += '<span class="px-2 py-1 bg-green-600 text-white text-xs rounded">Ready</span>';
                } else {
                    content += `<span class="px-2 py-1 bg-red-600 text-white text-xs rounded">${summary.unresolved_lines} Unresolved</span>`;
                }
                content += '</div>';

                // VAT breakdown table
                content += '<table class="w-full text-sm mb-3">';
                content += '<thead><tr class="text-gray-400 text-xs border-b border-gray-600">';
                content += '<th class="text-left py-1">VAT Rate</th>';
                content += '<th class="text-right py-1">Net Amount</th>';
                content += '</tr></thead><tbody class="text-gray-300">';

                const gfr = breakdown.goods_for_resale;
                content += `<tr><td class="py-1">0%</td><td class="text-right">€${(gfr['0'] || 0).toFixed(2)}</td></tr>`;
                content += `<tr><td class="py-1">9%</td><td class="text-right">€${(gfr['9'] || 0).toFixed(2)}</td></tr>`;
                content += `<tr><td class="py-1">13.5%</td><td class="text-right">€${(gfr['13.5'] || 0).toFixed(2)}</td></tr>`;
                content += `<tr><td class="py-1">23%</td><td class="text-right">€${(gfr['23'] || 0).toFixed(2)}</td></tr>`;
                content += `<tr class="border-t border-gray-600 font-semibold"><td class="py-1">Total RTD</td><td class="text-right text-green-400">€${(summary.total_resolved || 0).toFixed(2)}</td></tr>`;
                content += '</tbody></table>';

                // Stats
                content += `<p class="text-xs text-gray-400">${summary.resolved_lines}/${summary.total_lines} lines resolved (${summary.resolved_percentage}%)`;
                if (summary.excluded_lines > 0) {
                    content += ` | ${summary.excluded_lines} excluded (barrels/costs)`;
                }
                content += '</p>';

                // Unresolved items
                if (rtd.issues && rtd.issues.length > 0) {
                    content += '<div class="mt-3 p-3 bg-red-900/30 rounded">';
                    content += `<p class="text-red-400 text-sm font-medium mb-2">Unresolved Items (€${(summary.total_unresolved || 0).toFixed(2)})</p>`;
                    content += '<div class="max-h-40 overflow-y-auto space-y-1">';

                    rtd.issues.slice(0, 15).forEach(issue => {
                        content += '<div class="text-xs flex justify-between items-center py-1 border-b border-gray-700">';
                        content += `<span class="font-mono text-gray-300">${issue.article_code}</span>`;
                        content += `<span class="text-gray-400 truncate mx-2 flex-1">${issue.description || ''}</span>`;
                        content += `<span class="text-red-400">${issue.reason_text || issue.reason}</span>`;
                        content += `<span class="text-gray-500 ml-2">€${(issue.line_total || 0).toFixed(2)}</span>`;
                        content += '</div>';
                    });

                    if (rtd.issues.length > 15) {
                        content += `<p class="text-gray-500 text-xs text-center mt-2">... and ${rtd.issues.length - 15} more unresolved items</p>`;
                    }

                    content += '</div>';
                    content += '<p class="text-xs text-gray-500 mt-2">These items need SupplierLink entries to link article codes to products.</p>';
                    content += '</div>';
                }

                content += '</div>';
            }

            // Lines section
            if (result.lines && result.lines.length > 0) {
                content += '<div class="bg-gray-700 p-4 rounded">';
                content += `<h4 class="text-gray-300 font-semibold mb-2">Product Lines (${result.lines.length})</h4>`;
                content += '<div class="max-h-60 overflow-y-auto">';
                content += '<table class="w-full text-sm">';
                content += '<thead><tr class="text-gray-400 text-xs">';
                content += '<th class="text-left py-1">Code</th>';
                content += '<th class="text-left py-1">Description</th>';
                content += '<th class="text-right py-1">Qty</th>';
                content += '<th class="text-right py-1">Price</th>';
                content += '<th class="text-right py-1">Total</th>';
                content += '<th class="text-left py-1">Type</th>';
                content += '</tr></thead><tbody>';
                result.lines.slice(0, 20).forEach(line => {
                    const typeColor = line.line_type === 'product_for_resale' ? 'text-blue-300' : 'text-orange-300';
                    content += `<tr class="text-gray-300 border-t border-gray-600">`;
                    content += `<td class="py-1">${line.article_code}</td>`;
                    content += `<td class="py-1">${line.description?.substring(0, 30) || ''}...</td>`;
                    content += `<td class="py-1 text-right">${line.quantity}</td>`;
                    content += `<td class="py-1 text-right">€${(line.unit_price || 0).toFixed(2)}</td>`;
                    content += `<td class="py-1 text-right">€${(line.line_total || 0).toFixed(2)}</td>`;
                    content += `<td class="py-1 ${typeColor} text-xs">${line.line_type?.replace(/_/g, ' ')}</td>`;
                    content += '</tr>';
                });
                if (result.lines.length > 20) {
                    content += `<tr><td colspan="6" class="text-gray-400 text-center py-2">... and ${result.lines.length - 20} more lines</td></tr>`;
                }
                content += '</tbody></table></div></div>';
            }

            // Barrels section
            if (result.barrels && result.barrels.items && result.barrels.items.length > 0) {
                content += '<div class="bg-gray-700 p-4 rounded">';
                content += `<h4 class="text-orange-300 font-semibold mb-2">Barrels/Deposits (${result.barrels.items.length}) - €${result.barrels.total?.toFixed(2)}</h4>`;
                content += '<ul class="text-sm text-gray-300 space-y-1">';
                result.barrels.items.forEach(item => {
                    content += `<li>${item.quantity}x ${item.description} - €${item.total?.toFixed(2)}</li>`;
                });
                content += '</ul></div>';
            }

            // Costs section
            if (result.costs && result.costs.items && result.costs.items.length > 0) {
                content += '<div class="bg-gray-700 p-4 rounded">';
                content += `<h4 class="text-yellow-300 font-semibold mb-2">Costs/Freight (${result.costs.items.length}) - €${result.costs.total?.toFixed(2)}</h4>`;
                content += '<ul class="text-sm text-gray-300 space-y-1">';
                result.costs.items.forEach(item => {
                    content += `<li>${item.description} - €${item.total?.toFixed(2)}</li>`;
                });
                content += '</ul></div>';
            }

            // Warnings
            if (result.warnings && result.warnings.length > 0) {
                content += '<div class="bg-yellow-900/30 border border-yellow-700 p-4 rounded">';
                content += '<h4 class="text-yellow-400 font-semibold mb-2">⚠ Warnings</h4>';
                content += '<ul class="list-disc list-inside text-yellow-300 space-y-1">';
                result.warnings.forEach(warning => {
                    content += `<li>${warning}</li>`;
                });
                content += '</ul></div>';
            }

            // Errors
            if (result.errors && result.errors.length > 0) {
                content += '<div class="bg-red-900/30 border border-red-700 p-4 rounded">';
                content += '<h4 class="text-red-400 font-semibold mb-2">❌ Errors</h4>';
                content += '<ul class="list-disc list-inside text-red-300 space-y-1">';
                result.errors.forEach(error => {
                    content += `<li>${error.message || error}</li>`;
                });
                content += '</ul></div>';
            }

            // Problem Lines (lines that couldn't be parsed)
            if (result.problem_lines && result.problem_lines.length > 0) {
                content += '<div class="bg-orange-900/30 border border-orange-700 p-4 rounded">';
                content += `<h4 class="text-orange-400 font-semibold mb-2">⚠ Problem Lines (${result.problem_lines.length})</h4>`;
                content += '<p class="text-orange-300 text-xs mb-2">These lines look like products but couldn\'t be fully parsed:</p>';
                content += '<div class="max-h-48 overflow-y-auto space-y-2">';
                result.problem_lines.forEach((item, idx) => {
                    content += '<div class="bg-gray-800 p-2 rounded text-xs">';
                    content += `<p class="text-gray-400 font-mono break-all">${(item.original || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>`;
                    content += `<p class="text-orange-400 mt-1">Reason: ${item.reason || 'unknown'}</p>`;
                    content += '</div>';
                });
                content += '</div></div>';
            }

            // Stats
            if (result.metadata?.stats) {
                const s = result.metadata.stats;
                content += '<div class="bg-gray-700 p-4 rounded text-xs text-gray-400">';
                content += '<h4 class="text-gray-300 font-semibold mb-1">Parser Stats</h4>';
                content += `<p>Lines scanned: ${s.total_lines_scanned}, Products: ${s.product_lines_parsed}, Barrels: ${s.barrel_lines_parsed}, Costs: ${s.cost_lines_parsed}</p>`;
                content += '</div>';
            }

            content += '</div>';

            // Show in modal (reuse existing parsedDataModal)
            document.getElementById('parsedDataContent').innerHTML = content;
            document.getElementById('parsedDataModal').classList.remove('hidden');
        }
        // ─── VAT Quick Fix Modal ─────────────────────────────────
        let vatFixState = { fileId: null, batchId: null, amount: 0, warningIdx: null, isMobile: false };

        function openVatFixModal(fileId, batchId, amount, warningIdx, isMobile = false) {
            vatFixState = { fileId, batchId, amount, warningIdx, isMobile };
            document.getElementById('vatFixAmountInput').value = amount.toFixed(2);

            // Reset selections
            document.querySelector('input[name="vat_amount_type"][value="gross"]').checked = true;
            document.querySelector('input[name="vat_rate_fix"][value="0"]').checked = true;
            document.getElementById('vatFixPreview').classList.add('hidden');

            document.getElementById('vatFixModal').classList.remove('hidden');
        }

        function closeVatFixModal() {
            document.getElementById('vatFixModal').classList.add('hidden');
        }

        function updateVatPreview() {
            const amountType = document.querySelector('input[name="vat_amount_type"]:checked')?.value;
            const rateStr = document.querySelector('input[name="vat_rate_fix"]:checked')?.value;

            if (!rateStr) return;

            const rate = parseFloat(rateStr) / 100;
            const amount = vatFixState.amount;
            let net, vat, gross;

            if (rate === 0) {
                net = amount;
                vat = 0;
                gross = amount;
            } else if (amountType === 'gross') {
                gross = amount;
                net = gross / (1 + rate);
                vat = gross - net;
            } else {
                net = amount;
                vat = net * rate;
                gross = net + vat;
            }

            document.getElementById('vatFixNet').textContent = '€' + net.toFixed(2);
            document.getElementById('vatFixVat').textContent = '€' + vat.toFixed(2);
            document.getElementById('vatFixGross').textContent = '€' + gross.toFixed(2);
            document.getElementById('vatFixPreview').classList.remove('hidden');
        }

        function saveVatFix() {
            const amountType = document.querySelector('input[name="vat_amount_type"]:checked')?.value;
            const rateStr = document.querySelector('input[name="vat_rate_fix"]:checked')?.value;
            const rate = parseFloat(rateStr) / 100;
            const amount = vatFixState.amount;

            let net;
            if (rate === 0) {
                net = amount;
            } else if (amountType === 'gross') {
                net = amount / (1 + rate);
            } else {
                net = amount;
            }

            // Build VAT data -- put all into the selected rate bucket
            const data = {
                vat_0_net: 0,
                vat_9_net: 0,
                vat_13_5_net: 0,
                vat_23_net: 0,
            };

            const rateKey = {
                '0': 'vat_0_net',
                '9': 'vat_9_net',
                '13.5': 'vat_13_5_net',
                '23': 'vat_23_net',
            }[rateStr];

            data[rateKey] = parseFloat(net.toFixed(2));

            const saveBtn = document.getElementById('vatFixSaveBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            fetch(`/invoices/bulk-upload/${vatFixState.batchId}/file/${vatFixState.fileId}/parsed-data`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    const rateLabel = rateStr === '0' ? '0%' : rateStr + '%';
                    const resolvedHtml = `<span>&#10003; VAT corrected to ${rateLabel} (Net: €${net.toFixed(2)})</span>`;

                    // Mark both desktop and mobile warning elements as resolved (green)
                    ['', 'mobile-'].forEach(prefix => {
                        const el = document.getElementById(`vat-warning-${prefix}${vatFixState.fileId}-${vatFixState.warningIdx}`);
                        if (el) {
                            el.className = 'text-green-400 p-1 -m-1';
                            el.innerHTML = resolvedHtml;
                            el.onclick = null;
                            el.style.cursor = 'default';
                        }
                    });
                    closeVatFixModal();
                } else {
                    alert(result.error || 'Failed to save');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error saving VAT fix');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Apply Fix';
            });
        }
    </script>
    @endpush
</x-admin-layout>