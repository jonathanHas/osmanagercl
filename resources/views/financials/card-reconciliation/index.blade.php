<x-admin-layout>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Card Transaction Reconciliation</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Upload myPOS card transaction files to compare against POS records and identify discrepancies.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('management.card-reconciliation.terminal-mappings') }}"
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    Terminal Mappings
                </a>
                <a href="{{ route('management.card-reconciliation.transactions') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                    View All Transactions
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="mb-6 p-4 rounded-lg bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ session('warning') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Alpine.js Component -->
        <div x-data="cardReconciliationUpload()" x-init="init()">

            <!-- Upload Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload Card Transactions</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Select an XLS file exported from myPOS. File size limit: 10MB.
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Time Window:</label>
                        <select x-model="timeWindow" @change="saveSettings()"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="3">3 minutes</option>
                            <option value="5">5 minutes</option>
                            <option value="10">10 minutes</option>
                            <option value="15">15 minutes</option>
                        </select>
                    </div>
                </div>

                <form @submit.prevent="handleSubmit()" enctype="multipart/form-data">
                    @csrf
                    <div class="p-6">
                        <!-- File Drop Zone -->
                        <div class="mb-4"
                             @dragover.prevent="dragover = true"
                             @dragleave.prevent="dragover = false"
                             @drop.prevent="dragover = false; $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0]?.name || ''">
                            <div :class="dragover ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/10' : 'border-gray-300 dark:border-gray-600'"
                                 class="border-2 border-dashed rounded-lg p-6 text-center transition-colors hover:border-blue-400">
                                <div class="space-y-2">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <label for="card_transactions_file" class="cursor-pointer">
                                            <span class="font-medium text-blue-600 hover:text-blue-500">Click to upload</span>
                                            or drag and drop
                                        </label>
                                        <input type="file"
                                               name="card_transactions_file"
                                               id="card_transactions_file"
                                               x-ref="fileInput"
                                               @change="fileName = $event.target.files[0]?.name || ''"
                                               accept=".xls,.xlsx"
                                               class="sr-only">
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">XLS, XLSX up to 10MB</p>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2" x-text="fileName || 'No file selected'"></p>
                        </div>

                        <!-- Processing Status -->
                        <div x-show="processing" class="mb-4 p-4 rounded-lg bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            <div class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="processingMessage || 'Processing your file...'"></span>
                            </div>
                        </div>

                        <!-- Upload Button -->
                        <button type="submit"
                                :disabled="uploading || processing"
                                :class="(uploading || processing) ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                class="px-6 py-2 text-white rounded-md font-medium transition-colors">
                            <span x-show="!uploading && !processing">Upload and Process</span>
                            <span x-show="uploading || processing" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="uploading ? 'Uploading...' : 'Processing...'"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($overallStats['total']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm font-medium text-green-600 dark:text-green-400">Matched</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($overallStats['matched']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm font-medium text-red-600 dark:text-red-400">Mismatches</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($overallStats['mismatches']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm font-medium text-orange-600 dark:text-orange-400">Declined</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($overallStats['declined']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Orphans</p>
                    <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($overallStats['orphans']) }}</p>
                </div>
            </div>

            <!-- Upload History -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload History</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Recent card transaction uploads and reconciliation results.</p>
                </div>

                @if($batches->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Matched</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Issues</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Uploaded</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($batches as $batch)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded bg-blue-100 dark:bg-blue-900/30 mr-3">
                                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $batch->source_filename }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($batch->total) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        {{ number_format($batch->matched) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($batch->mismatches + $batch->declined + $batch->orphans > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        {{ number_format($batch->mismatches + $batch->declined + $batch->orphans) }} issues
                                    </span>
                                    @else
                                    <span class="text-sm text-green-600 dark:text-green-400">All matched</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($batch->date_from && $batch->date_to)
                                        {{ \Carbon\Carbon::parse($batch->date_from)->format('M j') }} - {{ \Carbon\Carbon::parse($batch->date_to)->format('M j, Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($batch->uploaded_at)->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('management.card-reconciliation.transactions', ['batch_id' => $batch->upload_batch_id]) }}"
                                           class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            View
                                        </a>
                                        @if($batch->orphans > 0)
                                        <button @click="openAutoMatchModal('{{ $batch->upload_batch_id }}', {{ $batch->orphans }})"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                            Auto-match
                                        </button>
                                        @endif
                                        <form action="{{ route('management.card-reconciliation.reprocess') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="batch_id" value="{{ $batch->upload_batch_id }}">
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300">
                                                Reprocess
                                            </button>
                                        </form>
                                        <form action="{{ route('management.card-reconciliation.delete') }}" method="POST"
                                              onsubmit="return confirm('Delete {{ number_format($batch->total) }} transactions from {{ $batch->source_filename }}?')"
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="batch_id" value="{{ $batch->upload_batch_id }}">
                                            <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No uploads yet</h3>
                    <p class="text-gray-500 dark:text-gray-400">Upload your first myPOS transaction file to get started.</p>
                </div>
                @endif
            </div>

            <!-- Auto-Match Modal -->
            <div x-show="showAutoMatchModal" x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 @keydown.escape.window="showAutoMatchModal = false">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black opacity-50" @click="showAutoMatchModal = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Auto-Match Orphan Transactions</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Configure matching options and preview which orphan transactions will be matched.
                            </p>
                        </div>
                        <div class="p-6">
                            <!-- Options -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time Window</label>
                                    <select x-model="autoMatchOptions.window_minutes"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <option value="15">15 minutes</option>
                                        <option value="30">30 minutes</option>
                                        <option value="60">1 hour</option>
                                        <option value="120">2 hours</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Minimum Confidence</label>
                                    <select x-model="autoMatchOptions.min_confidence"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <option value="70">70%</option>
                                        <option value="80">80% (Recommended)</option>
                                        <option value="90">90%</option>
                                    </select>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" x-model="autoMatchOptions.exact_amount_only"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Exact amount match only (no variance)</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" x-model="autoMatchOptions.card_only"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Card payments only (exclude cash)</label>
                                </div>
                                <div class="flex items-center col-span-2">
                                    <input type="checkbox" x-model="autoMatchOptions.respect_till_mapping"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Respect terminal-till mappings (only match configured tills)</label>
                                </div>
                            </div>

                            <button @click="loadAutoMatchPreview()"
                                    :disabled="loadingPreview"
                                    class="mb-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                                <span x-show="!loadingPreview">Preview Matches</span>
                                <span x-show="loadingPreview" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading...
                                </span>
                            </button>

                            <!-- Preview Results -->
                            <div x-show="previewLoaded">
                                <div class="mb-4 p-3 rounded-lg" :class="autoMatchPreviews.length > 0 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20'">
                                    <p class="font-medium" :class="autoMatchPreviews.length > 0 ? 'text-green-800 dark:text-green-300' : 'text-yellow-800 dark:text-yellow-300'">
                                        <span x-text="autoMatchPreviews.length"></span> of <span x-text="totalOrphans"></span> orphans will be matched
                                    </p>
                                </div>

                                <div x-show="autoMatchPreviews.length > 0" class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="p-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Card TX</th>
                                                <th class="p-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                                <th class="p-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">POS Amount</th>
                                                <th class="p-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Method</th>
                                                <th class="p-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Confidence</th>
                                                <th class="p-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variance</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <template x-for="p in autoMatchPreviews" :key="p.card_tx.id">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="p-2 text-gray-900 dark:text-white" x-text="p.card_tx.transaction_reference"></td>
                                                    <td class="p-2 text-right font-medium text-gray-900 dark:text-white" x-text="'€' + parseFloat(p.card_tx.amount).toFixed(2)"></td>
                                                    <td class="p-2 text-right font-medium text-gray-900 dark:text-white" x-text="'€' + parseFloat(p.pos_payment.TOTAL).toFixed(2)"></td>
                                                    <td class="p-2 text-center">
                                                        <span :class="p.payment_method === 'magcard' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'"
                                                              class="px-2 py-0.5 rounded text-xs font-medium"
                                                              x-text="p.payment_method === 'magcard' ? 'Card' : (p.payment_method === 'cash' ? 'Cash' : p.payment_method)"></span>
                                                    </td>
                                                    <td class="p-2 text-center">
                                                        <span :class="p.confidence >= 90 ? 'text-green-600 dark:text-green-400' : (p.confidence >= 80 ? 'text-blue-600 dark:text-blue-400' : 'text-yellow-600 dark:text-yellow-400')"
                                                              class="font-medium"
                                                              x-text="p.confidence + '%'"></span>
                                                    </td>
                                                    <td class="p-2 text-right">
                                                        <span x-show="p.variance >= 0.01" class="text-red-600 dark:text-red-400" x-text="'€' + p.variance.toFixed(2)"></span>
                                                        <span x-show="p.variance < 0.01" class="text-gray-400">-</span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div x-show="autoMatchPreviews.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    No matches found with the current criteria. Try widening the time window or lowering the confidence threshold.
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                            <button @click="showAutoMatchModal = false"
                                    class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                Cancel
                            </button>
                            <button @click="executeAutoMatch()"
                                    x-show="autoMatchPreviews.length > 0"
                                    :disabled="executingAutoMatch"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                                <span x-show="!executingAutoMatch">
                                    Match <span x-text="autoMatchPreviews.length"></span> Transactions
                                </span>
                                <span x-show="executingAutoMatch" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Matching...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cardReconciliationUpload() {
            return {
                uploading: false,
                dragover: false,
                processing: false,
                processingMessage: '',
                fileName: '',
                batchId: null,
                timeWindow: {{ $settings->time_window_minutes }},
                statusCheckAttempts: 0,
                maxStatusCheckAttempts: 60,
                csrfToken: '{{ csrf_token() }}',
                // Auto-match modal state
                showAutoMatchModal: false,
                selectedBatchId: null,
                totalOrphans: 0,
                loadingPreview: false,
                previewLoaded: false,
                executingAutoMatch: false,
                autoMatchPreviews: [],
                autoMatchOptions: {
                    window_minutes: '30',
                    min_confidence: '80',
                    exact_amount_only: false,
                    card_only: true,
                    respect_till_mapping: true
                },

                init() {
                    @if(session('batch_id'))
                    this.batchId = '{{ session('batch_id') }}';
                    this.processing = true;
                    this.processingMessage = 'Processing uploaded file...';
                    setTimeout(() => this.checkProcessingStatus(), 2000);
                    @endif
                },

                handleSubmit() {
                    const fileInput = this.$refs.fileInput;
                    const file = fileInput.files[0];

                    if (!file) {
                        alert('Please select a file to upload.');
                        return;
                    }

                    this.uploading = true;
                    this.processingMessage = 'Uploading file...';

                    const formData = new FormData();
                    formData.append('card_transactions_file', file);
                    formData.append('_token', this.csrfToken);

                    fetch('{{ route("management.card-reconciliation.store") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin'
                    })
                    .then(async response => {
                        const data = await response.json();
                        this.uploading = false;

                        if (response.ok && data.success) {
                            this.processing = true;
                            this.batchId = data.batch_id;
                            this.processingMessage = data.message || 'Processing file...';
                            this.statusCheckAttempts = 0;
                            fileInput.value = '';
                            this.fileName = '';
                            setTimeout(() => this.checkProcessingStatus(), 3000);
                        } else {
                            alert(data.error || 'Upload failed. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        this.uploading = false;
                        alert('Upload failed. Please try again.');
                    });
                },

                checkProcessingStatus() {
                    if (!this.batchId) return;

                    this.statusCheckAttempts++;

                    if (this.statusCheckAttempts > this.maxStatusCheckAttempts) {
                        this.processing = false;
                        location.reload();
                        return;
                    }

                    fetch('{{ route("management.card-reconciliation.status", ["batchId" => "__BATCH__"]) }}'.replace('__BATCH__', this.batchId), {
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            this.processing = false;
                            location.reload();
                        } else if (data.status === 'failed') {
                            this.processing = false;
                            alert('Processing failed: ' + (data.message || 'Unknown error'));
                            location.reload();
                        } else {
                            this.processingMessage = data.message || 'Processing...';
                            setTimeout(() => this.checkProcessingStatus(), 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Status check error:', error);
                        setTimeout(() => this.checkProcessingStatus(), 5000);
                    });
                },

                saveSettings() {
                    fetch('{{ route("management.card-reconciliation.settings") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            time_window_minutes: parseInt(this.timeWindow),
                            auto_match_threshold: 80
                        }),
                        credentials: 'same-origin'
                    });
                },

                openAutoMatchModal(batchId, orphanCount) {
                    this.selectedBatchId = batchId;
                    this.totalOrphans = orphanCount;
                    this.showAutoMatchModal = true;
                    this.previewLoaded = false;
                    this.autoMatchPreviews = [];
                    // Reset options to defaults
                    this.autoMatchOptions = {
                        window_minutes: '30',
                        min_confidence: '80',
                        exact_amount_only: false,
                        card_only: true,
                        respect_till_mapping: true
                    };
                },

                loadAutoMatchPreview() {
                    this.loadingPreview = true;
                    this.previewLoaded = false;

                    const params = new URLSearchParams({
                        batch_id: this.selectedBatchId,
                        window_minutes: this.autoMatchOptions.window_minutes,
                        min_confidence: this.autoMatchOptions.min_confidence,
                        exact_amount_only: this.autoMatchOptions.exact_amount_only ? '1' : '0',
                        card_only: this.autoMatchOptions.card_only ? '1' : '0',
                        respect_till_mapping: this.autoMatchOptions.respect_till_mapping ? '1' : '0'
                    });

                    fetch('{{ route("management.card-reconciliation.preview-auto-match") }}?' + params.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.autoMatchPreviews = data.previews || [];
                        this.totalOrphans = data.total_orphans || 0;
                        this.loadingPreview = false;
                        this.previewLoaded = true;
                    })
                    .catch(error => {
                        console.error('Error loading preview:', error);
                        this.loadingPreview = false;
                        alert('Failed to load preview. Please try again.');
                    });
                },

                executeAutoMatch() {
                    if (!confirm('Match ' + this.autoMatchPreviews.length + ' transactions?')) return;

                    this.executingAutoMatch = true;

                    fetch('{{ route("management.card-reconciliation.auto-match") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            batch_id: this.selectedBatchId,
                            window_minutes: parseInt(this.autoMatchOptions.window_minutes),
                            min_confidence: parseInt(this.autoMatchOptions.min_confidence),
                            exact_amount_only: this.autoMatchOptions.exact_amount_only,
                            card_only: this.autoMatchOptions.card_only,
                            respect_till_mapping: this.autoMatchOptions.respect_till_mapping
                        }),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.executingAutoMatch = false;
                        if (data.success) {
                            this.showAutoMatchModal = false;
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to auto-match transactions.');
                        }
                    })
                    .catch(error => {
                        console.error('Error executing auto-match:', error);
                        this.executingAutoMatch = false;
                        alert('Failed to auto-match transactions. Please try again.');
                    });
                }
            }
        }
    </script>
</x-admin-layout>
