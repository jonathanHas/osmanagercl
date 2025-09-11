<!-- Bank Reconciliation Panel Modal -->
<div @reconciliationUpdated.window="$dispatch('transaction-updated')">
    <!-- Modal Backdrop -->
    @if($showPanel)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50" wire:click="closePanel"></div>
        
        <!-- Modal Container -->
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                
                <!-- Header -->
                <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            @if($transaction->exists && $transaction->isCreditTransaction())
                                💰 Categorize Credit Transaction
                            @else
                                🔄 Reconcile Transaction
                            @endif
                        </h3>
                        <button wire:click="closePanel" 
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="px-4 py-2">
                    <div class="mb-2">
                        <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">🔍 Search Invoices</label>
                        <input type="text" wire:model.live="searchQuery" wire:input="searchInvoices"
                               placeholder="Search by number, supplier..."
                               class="block w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Supplier Detection & Filtering Banner -->
                @if($detectedSupplier && !$showAllSuppliers)
                    <div class="px-4 py-2">
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1">
                                        @if($detectedSupplier['match_type'] === 'exact_fingerprint')
                                            <span class="text-blue-600 dark:text-blue-400 text-sm">🎯</span>
                                        @else
                                            <span class="text-blue-600 dark:text-blue-400 text-sm">📚</span>
                                        @endif
                                        <span class="text-xs font-medium text-blue-800 dark:text-blue-200">
                                            Auto-filtered to: <strong>{{ $detectedSupplier['supplier']->name }}</strong>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                            {{ $detectedSupplier['confidence'] }}% confidence
                                        </span>
                                        @if($detectedSupplier['rule'] && $detectedSupplier['rule']->match_count > 1)
                                            <span class="text-xs text-blue-600 dark:text-blue-400" title="This pattern has been matched {{ $detectedSupplier['rule']->match_count }} times">
                                                ({{ $detectedSupplier['rule']->match_count }}x matched)
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <button wire:click="showAllSuppliers"
                                        class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    Show All Suppliers
                                </button>
                            </div>
                            <div class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                @if($detectedSupplier['match_type'] === 'exact_fingerprint')
                                    Payment description matches known pattern for this supplier
                                @else
                                    Payment pattern learned from previous reconciliations
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if($showAllSuppliers && $detectedSupplier)
                    <div class="px-4 py-2">
                        <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    Showing invoices from all suppliers
                                    @if($detectedSupplier)
                                        • Detected: <strong>{{ $detectedSupplier['supplier']->name }}</strong>
                                    @endif
                                </div>
                                <button wire:click="filterByDetectedSupplier"
                                        class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    Filter to {{ $detectedSupplier['supplier']->name }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Transaction vs Invoices Table -->
                <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900">
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px]">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Date</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20 hidden sm:table-cell">Status/Match</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description / Supplier</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Amount</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Actions</th>
                                    </tr>
                                </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Transaction Row -->
                                <tr class="bg-blue-50 dark:bg-blue-900/20">
                                    <td class="px-3 py-2 text-xs text-gray-900 dark:text-white">
                                        @if($transaction->exists)
                                            {{ $transaction->transaction_date->format('M j, Y') }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center hidden sm:table-cell">
                                        @if($transaction->exists)
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                                    'matched' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                                    'reconciled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                                    'ignored' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
                                                ];
                                                $statusColor = $statusColors[$transaction->status ?? 'pending'] ?? $statusColors['pending'];
                                            @endphp
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $statusColor }}">
                                                {{ ucfirst($transaction->status ?? 'pending') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white">
                                            @if($transaction->exists)
                                                {{ $transaction->description ?? 'No description' }}
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Bank Transaction ({{ ucfirst($this->transactionType) }})
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if($transaction->exists)
                                            @if($this->transactionType === 'expense')
                                                <span class="text-sm font-medium text-red-600 dark:text-red-400">-€{{ number_format($this->transactionAmount, 2) }}</span>
                                            @else
                                                <span class="text-sm font-medium text-green-600 dark:text-green-400">+€{{ number_format($this->transactionAmount, 2) }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">Transaction</span>
                                    </td>
                                </tr>
                                
                                <!-- Suggested Invoice Matches as Table Rows -->
                                @if(!$showMultiInvoiceMode && $suggestedMatches->count() > 0)
                                    @foreach($suggestedMatches->take(3) as $match)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                                            wire:click="selectInvoice({{ $match['invoice']->id }})">
                                            <td class="px-3 py-2 text-xs text-gray-900 dark:text-white">
                                                {{ $match['invoice']->invoice_date->format('M j, Y') }}
                                            </td>
                                            <td class="px-3 py-2 text-center hidden sm:table-cell">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                                    @if($match['confidence'] >= 80) bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                    @elseif($match['confidence'] >= 50) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                                    @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 @endif">
                                                    {{ $match['confidence'] }}%
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white">
                                                    {{ $match['invoice']->supplier_name }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    #{{ $match['invoice']->invoice_number }} • 
                                                    @php
                                                        $reasonsText = implode(', ', $match['match_reasons']);
                                                        $hasPaymentMatch = str_contains($reasonsText, '🎯');
                                                        $exactMatch = str_contains($reasonsText, 'Exact match');
                                                        $withinOneDay = str_contains($reasonsText, 'within 1 day');
                                                        $withinThreeDays = str_contains($reasonsText, 'within 3 days');
                                                    @endphp
                                                    @if($hasPaymentMatch)
                                                        <span title="{{ $reasonsText }}" class="inline-flex items-center cursor-help">
                                                            🎯 
                                                            @if($exactMatch)
                                                                0d
                                                            @elseif($withinOneDay) 
                                                                1d
                                                            @elseif($withinThreeDays)
                                                                3d
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span title="{{ $reasonsText }}">{{ $reasonsText }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($match['invoice']->total_amount, 2) }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button wire:click.stop="matchWithInvoice({{ $match['invoice']->id }})"
                                                        class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                                    Match
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                
                                <!-- Search Results as Table Rows -->
                                @if(!$showMultiInvoiceMode && $searchResults->count() > 0)
                                    @foreach($searchResults as $result)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer bg-yellow-50 dark:bg-yellow-900/10" 
                                            wire:click="selectInvoice({{ $result['invoice']->id }})">
                                            <td class="px-3 py-2 text-xs text-gray-900 dark:text-white">
                                                {{ $result['invoice']->invoice_date->format('M j, Y') }}
                                            </td>
                                            <td class="px-3 py-2 text-center hidden sm:table-cell">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                                    Search
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white">
                                                    {{ $result['invoice']->supplier_name }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    #{{ $result['invoice']->invoice_number }} • Manual search result
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($result['invoice']->total_amount, 2) }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button wire:click.stop="matchWithInvoice({{ $result['invoice']->id }})"
                                                        class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                                    Match
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                
                                <!-- No Results Message Row -->
                                @if(!$showMultiInvoiceMode && strlen($searchQuery) >= 2 && $searchResults->count() === 0 && $suggestedMatches->count() === 0)
                                    <tr>
                                        <td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No invoices found matching "{{ $searchQuery }}"
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-1 px-4 py-2 overflow-y-auto">
                    <!-- Credit Categorization Section (for credit transactions) -->
                    @if($transaction->exists && $transaction->isCreditTransaction())
                        <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-3">
                                💰 Categorize Credit Transaction
                            </h4>

                            <!-- Credit Prediction Display -->
                            @if($creditPrediction)
                                <div class="bg-white dark:bg-gray-800 border border-green-300 dark:border-green-600 rounded-lg p-3 mb-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">{{ $creditPrediction['icon'] }}</span>
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                AI Prediction: {{ $creditPrediction['description'] }}
                                            </span>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $creditPrediction['color'] }}">
                                            {{ $creditPrediction['confidence'] }}% confidence
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        <strong>Reason:</strong> {{ $creditPrediction['reason'] }}
                                    </div>
                                </div>
                            @endif

                            <!-- Credit Category Selection -->
                            <div class="mb-3">
                                <label for="creditCategory" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Select Credit Category:
                                </label>
                                <select wire:model.live="selectedCreditCategory" id="creditCategory"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <option value="">-- Select Category --</option>
                                    @foreach($this->getCreditCategories() as $key => $label)
                                        <option value="{{ $key }}">
                                            @if($key === 'card_lodgement')
                                                💳 {{ $label }}
                                            @elseif($key === 'cash_lodgement')
                                                💰 {{ $label }}
                                            @elseif($key === 'rent')
                                                🏠 {{ $label }}
                                            @else
                                                📈 {{ $label }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Notes for Credit Transaction -->
                            <div class="mb-3">
                                <label for="creditNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Notes (optional):
                                </label>
                                <textarea wire:model="notes" id="creditNotes" rows="2" 
                                          class="block w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring-green-500"
                                          placeholder="Add notes about this credit transaction..."></textarea>
                            </div>

                            <!-- Credit Categorization Action -->
                            <div class="flex gap-3">
                                <button wire:click="categorizeCredit"
                                        class="px-4 py-2 {{ $selectedCreditCategory ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed' }} text-white rounded-md transition-colors font-semibold"
                                        @unless($selectedCreditCategory) disabled @endunless>
                                    ✅ Categorize Credit
                                </button>
                                
                                <button wire:click="ignoreTransaction"
                                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                    🚫 Ignore Transaction
                                </button>

                                @if($transaction->exists && $transaction->status !== 'pending')
                                    <button wire:click="undoReconciliation"
                                            class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors">
                                        ↶ Undo Reconciliation
                                    </button>
                                @endif
                            </div>
                        </div>

                    <!-- Expense/Invoice Matching Section (for debit transactions) -->
                    @elseif(!$showCreateExpense)
                        @if(!$showMultiInvoiceMode)
                            <!-- Mode Selection -->
                            <div class="mb-3 bg-blue-50 dark:bg-blue-900/30 p-2 rounded">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-xs text-blue-800 dark:text-blue-200">
                                        Reconcile with single invoice or:
                                    </div>
                                    <button wire:click="enableMultiInvoiceMode"
                                            class="px-2 py-1 bg-purple-600 text-white rounded hover:bg-purple-700 transition-colors text-xs">
                                        📊 Multi-Invoice
                                    </button>
                                </div>
                            </div>

                            <!-- Suggested Invoice Combinations -->
                            @if($suggestedCombinations && $suggestedCombinations->count() > 0)
                                <div class="mb-2">
                                    <h4 class="text-xs font-medium text-gray-900 dark:text-white mb-1">
                                        🎯 Suggested Combinations ({{ $suggestedCombinations->count() }})
                                    </h4>
                                    <div class="space-y-1">
                                        @foreach($suggestedCombinations->take(3) as $combination)
                                            <div class="border border-purple-200 dark:border-purple-700 rounded p-2 hover:bg-purple-50 dark:hover:bg-purple-900/20 cursor-pointer">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-1 mb-1">
                                                            <h5 class="text-xs font-medium text-gray-900 dark:text-white">
                                                                {{ count($combination['invoices']) }} Invoices
                                                            </h5>
                                                            <span class="inline-flex items-center px-1 py-0.5 rounded text-xs font-medium
                                                                @if($combination['confidence'] >= 80) bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                                @elseif($combination['confidence'] >= 50) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                                                @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 @endif">
                                                                {{ $combination['confidence'] }}%
                                                            </span>
                                                            <span class="text-xs text-purple-600 dark:text-purple-400">
                                                                €{{ number_format($combination['total'], 2) }}
                                                                @if(abs($combination['difference']) > 0.01)
                                                                    <span class="text-gray-500">
                                                                        ({{ $combination['difference'] > 0 ? '+' : '' }}€{{ number_format($combination['difference'], 2) }})
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </div>
                                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                                            @foreach($combination['invoices'] as $invoice)
                                                                #{{ $invoice->invoice_number }}@if(!$loop->last), @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <button wire:click="selectCombination({{ json_encode($combination) }})"
                                                            class="px-2 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                                                        Use
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif



                        <!-- Notes Section -->
                        <div class="mb-2">
                            <label for="notes" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Notes (optional)
                            </label>
                            <textarea wire:model="notes" rows="2" 
                                      class="block w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Add notes..."></textarea>
                        </div>
                        @else
                            <!-- Multi-Invoice Allocation Mode -->
                            <div class="mb-2">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-semibold text-purple-900 dark:text-purple-100">
                                        📊 Multi-Invoice Allocation
                                    </h4>
                                    <button wire:click="disableMultiInvoiceMode"
                                            class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                        ← Back to Single Mode
                                    </button>
                                </div>

                                <!-- Allocation Summary -->
                                <div class="bg-purple-50 dark:bg-purple-900/30 p-2 rounded-lg mb-2">
                                    <div class="grid grid-cols-3 gap-2 text-center">
                                        <div>
                                            <div class="text-xs font-medium text-purple-700 dark:text-purple-300">Transaction</div>
                                            <div class="text-sm font-bold text-purple-900 dark:text-purple-100">
                                                €{{ number_format($this->transactionAmount, 2) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-purple-700 dark:text-purple-300">Allocated</div>
                                            <div class="text-sm font-bold text-purple-900 dark:text-purple-100">
                                                €{{ number_format($totalAllocated, 2) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-purple-700 dark:text-purple-300">Remaining</div>
                                            <div class="text-sm font-bold {{ $remainingAmount > 0.01 ? 'text-red-600 dark:text-red-400' : ($remainingAmount < -0.01 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400') }}">
                                                €{{ number_format($remainingAmount, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi-Invoice Allocation Table -->
                                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <!-- Selection Controls -->
                                    <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                                        <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                            Available Invoices ({{ $this->getAvailableInvoicesForMultiSelect()->count() }} total)
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="selectAllInvoices"
                                                    class="px-2 py-1 text-xs text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">
                                                ✓ Select All
                                            </button>
                                            @if(count($selectedInvoiceIds) > 0)
                                                <button wire:click="deselectAllInvoices"
                                                        class="px-2 py-1 text-xs text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300">
                                                    ✕ Clear All
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-[640px]">
                                            <thead class="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Date</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20 hidden sm:table-cell">Status</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Supplier / Invoice</th>
                                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Amount</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                <!-- Multi-Select Invoice List -->
                                                @foreach($this->getAvailableInvoicesForMultiSelect() as $match)
                                                    @php
                                                        $invoice = $match['invoice'];
                                                        $isSelected = in_array($invoice->id, $selectedInvoiceIds);
                                                        $allocatedAmount = $invoiceAllocations[$invoice->id] ?? 0;
                                                    @endphp
                                                    <tr class="{{ $isSelected ? 'bg-purple-50 dark:bg-purple-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                                        <td class="px-3 py-2 text-xs text-gray-900 dark:text-white">
                                                            {{ $invoice->invoice_date->format('M j, Y') }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center hidden sm:table-cell">
                                                            @if($isSelected)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                                                                    Selected
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                                                    @if(($match['confidence'] ?? 0) >= 80) bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                                    @elseif(($match['confidence'] ?? 0) >= 50) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                                                    @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 @endif">
                                                                    {{ $match['confidence'] ?? 0 }}%
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <div class="flex items-center gap-2">
                                                                <!-- Checkbox -->
                                                                <input type="checkbox" 
                                                                       wire:click="toggleInvoiceSelection({{ $invoice->id }})"
                                                                       {{ $isSelected ? 'checked' : '' }}
                                                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                                                <div>
                                                                    <div class="text-sm font-bold text-gray-900 dark:text-white">
                                                                        {{ $invoice->supplier_name }}
                                                                    </div>
                                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                        #{{ $invoice->invoice_number }}
                                                                        @if(isset($match['match_reasons']) && count($match['match_reasons']) > 0)
                                                                            • 
                                                                            @php
                                                                                $reasonsText = implode(', ', $match['match_reasons']);
                                                                                $hasPaymentMatch = str_contains($reasonsText, '🎯');
                                                                                $exactMatch = str_contains($reasonsText, 'Exact match');
                                                                                $withinOneDay = str_contains($reasonsText, 'within 1 day');
                                                                                $withinThreeDays = str_contains($reasonsText, 'within 3 days');
                                                                            @endphp
                                                                            @if($hasPaymentMatch)
                                                                                <span title="{{ $reasonsText }}" class="inline-flex items-center cursor-help">
                                                                                    🎯 
                                                                                    @if($exactMatch)
                                                                                        0d
                                                                                    @elseif($withinOneDay) 
                                                                                        1d
                                                                                    @elseif($withinThreeDays)
                                                                                        3d
                                                                                    @endif
                                                                                </span>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-2 text-right">
                                                            @if($isSelected)
                                                                <!-- Editable amount input for selected invoices -->
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       wire:model.live="invoiceAllocations.{{ $invoice->id }}"
                                                                       wire:input="calculateTotals"
                                                                       class="w-20 text-right text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                                                       placeholder="0.00">
                                                            @else
                                                                <!-- Display invoice total amount -->
                                                                <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($invoice->total_amount, 2) }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-center">
                                                            @if($isSelected)
                                                                <button wire:click="toggleInvoiceSelection({{ $invoice->id }})"
                                                                        class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                                    Remove
                                                                </button>
                                                            @else
                                                                <button wire:click="toggleInvoiceSelection({{ $invoice->id }})"
                                                                        class="px-2 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                                                                    Select
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                @if(count($selectedInvoiceIds) === 0)
                                    <!-- Help text when no invoices selected -->
                                    <div class="text-center py-4">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Check the boxes next to invoices you want to include in this allocation.
                                        </p>
                                    </div>
                                @else
                                    <!-- Selected invoices summary -->
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded p-2 mb-2">
                                        <div class="text-xs text-blue-800 dark:text-blue-200">
                                            📋 <strong>{{ count($selectedInvoiceIds) }} invoices selected</strong> 
                                            - Total allocated: €{{ number_format($totalAllocated, 2) }}
                                        </div>
                                    </div>
                                @endif

                                <!-- Allocation Status Messages -->
                                @php
                                    // Handle both old and new systems
                                    if (!empty($selectedInvoiceIds)) {
                                        $validAllocCount = count(array_filter($invoiceAllocations, fn($amount) => $amount > 0));
                                    } else {
                                        $validAllocCount = count(array_filter($selectedAllocations, fn($a) => isset($a['invoice_id']) && $a['invoice_id'] && isset($a['amount']) && $a['amount'] > 0));
                                    }
                                @endphp
                                @if($remainingAmount > 0.01)
                                    <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded p-2 mb-2">
                                        <div class="text-xs text-yellow-800 dark:text-yellow-200">
                                            ⚠️ <strong>Under-allocated:</strong> €{{ number_format($remainingAmount, 2) }} remaining.
                                        </div>
                                    </div>
                                @elseif($remainingAmount < -0.01)
                                    <div class="bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-700 rounded p-2 mb-2">
                                        <div class="text-xs text-orange-800 dark:text-orange-200">
                                            🔄 <strong>Over-allocated:</strong> €{{ number_format(abs($remainingAmount), 2) }} over transaction amount.
                                        </div>
                                    </div>
                                @elseif($validAllocCount > 0)
                                    <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded p-2 mb-2">
                                        <div class="text-xs text-green-800 dark:text-green-200">
                                            ✅ <strong>Perfect allocation:</strong> {{ $validAllocCount }} invoice(s) allocated.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @elseif($showCreateExpense)
                        <!-- Create Expense Form -->
                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">➕ Create New Expense</h4>
                            
                            <!-- Learned Suggestion Display -->
                            @if($learnedSuggestion)
                                <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-800 rounded-lg p-3">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-green-800 dark:text-green-200">
                                                Learned Pattern Detected
                                            </h4>
                                            <p class="text-sm text-green-700 dark:text-green-300">
                                                Based on {{ $learnedSuggestion['match_count'] }} previous transaction(s) like this, 
                                                this appears to be: <strong>{{ $learnedSuggestion['description'] }}</strong>
                                                ({{ $learnedSuggestion['confidence'] }}% confidence)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Non-Supplier Expense Toggle -->
                            <div class="flex items-center">
                                <input type="checkbox" wire:model.live="isNonSupplierExpense" id="nonSupplierToggle"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="nonSupplierToggle" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    This is a non-supplier expense (e.g., wages, taxes, bank fees)
                                    @if($learnedSuggestion)
                                        <span class="ml-1 text-green-600 dark:text-green-400 text-xs font-medium">(Auto-detected)</span>
                                    @endif
                                </label>
                            </div>
                            
                            @if(!$isNonSupplierExpense)
                                <div>
                                    <label for="expenseSupplierName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Supplier Name
                                    </label>
                                    <input type="text" wire:model="expenseSupplierName"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @error('expenseSupplierName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div>
                                    <label for="expenseDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Expense Description <span class="text-xs text-gray-500">(optional - auto-filled from category)</span>
                                    </label>
                                    <input type="text" wire:model="expenseDescription"
                                           placeholder="Auto-filled from category selection"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @error('expenseDescription') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="expenseCategory" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Category
                                    </label>
                                    <select wire:model="expenseCategory"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Select Category</option>
                                        @foreach($availableCategories as $code => $name)
                                            <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('expenseCategory') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="expenseSubtotal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Subtotal (ex. VAT)
                                    </label>
                                    <input type="number" step="0.01" wire:model="expenseSubtotal"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @error('expenseSubtotal') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="expenseVatAmount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        VAT Amount
                                    </label>
                                    <input type="number" step="0.01" wire:model="expenseVatAmount"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @error('expenseVatAmount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="bg-blue-50 dark:bg-blue-900/30 p-3 rounded-md">
                                <p class="text-sm text-blue-800 dark:text-blue-300">
                                    <strong>Total:</strong> €{{ number_format($expenseSubtotal + $expenseVatAmount, 2) }}
                                </p>
                            </div>
                            
                            @if($isNonSupplierExpense)
                                <div class="bg-yellow-50 dark:bg-yellow-900/30 p-3 rounded-md">
                                    <p class="text-xs text-yellow-800 dark:text-yellow-300">
                                        <strong>ℹ️ Common Non-Supplier Expenses:</strong><br>
                                        • <strong>Wages:</strong> Use "WAGES" category, VAT = 0<br>
                                        • <strong>Tax Payments:</strong> Revenue, VAT returns, Corporation tax<br>
                                        • <strong>Bank Fees:</strong> Service charges, transaction fees<br>
                                        • <strong>Insurance:</strong> Business insurance premiums (VAT exempt)
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex-shrink-0 px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <!-- Credit transactions don't need bottom actions - they're handled in the credit section above -->
                    @if($transaction->exists && $transaction->isCreditTransaction())
                        <!-- Just a close button for credit transactions since actions are above -->
                        <div class="flex justify-end">
                            <button wire:click="closePanel"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                Close
                            </button>
                        </div>
                    @elseif(!$showCreateExpense)
                        @if($showMultiInvoiceMode)
                            <div class="flex flex-wrap gap-3">
                                @php
                                    // Handle both old and new systems for button state
                                    if (!empty($selectedInvoiceIds)) {
                                        $validCount = count(array_filter($invoiceAllocations, fn($amount) => $amount > 0));
                                    } else {
                                        $validCount = count(array_filter($selectedAllocations, fn($a) => isset($a['invoice_id']) && $a['invoice_id'] && isset($a['amount']) && $a['amount'] > 0));
                                    }
                                @endphp
                                <button wire:click="confirmMultiInvoiceAllocation"
                                        @if($validCount === 0) disabled @endif
                                        class="px-6 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed font-semibold shadow-lg">
                                    🎯 CONFIRM ALLOCATION @if($validCount > 0) ({{ $validCount }} invoices) @endif
                                </button>
                                
                                <button wire:click="disableMultiInvoiceMode"
                                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                    ← Back to Single Mode
                                </button>

                                <button wire:click="closePanel"
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                    Cancel
                                </button>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-3">
                                <button wire:click="showCreateExpenseForm"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                    ➕ Create Expense
                                </button>
                                
                                <button wire:click="ignoreTransaction"
                                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                    🚫 Ignore Transaction
                                </button>

                                @if($transaction->exists && $transaction->status !== 'pending')
                                    <button wire:click="undoReconciliation"
                                            class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors">
                                        ↶ Undo Reconciliation
                                    </button>
                                @endif

                                <button wire:click="closePanel"
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                    Cancel
                                </button>
                            </div>
                        @endif
                    @else
                        <div class="flex gap-3">
                            <button wire:click="createExpense"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                Create & Match
                            </button>
                            
                            <button wire:click="$set('showCreateExpense', false)"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                Back
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
