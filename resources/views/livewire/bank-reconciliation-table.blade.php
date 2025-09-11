<div>
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    @php
        $stats = $this->fullDatasetStatistics;
    @endphp
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Transactions</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_count']) }}</p>
                @if($this->hasActiveFilters())
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Filtered total</p>
                @endif
            </div>
            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/30">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unmatched</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                    {{ number_format($stats['unmatched_count']) }}
                </p>
                @if($this->hasActiveFilters())
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Filtered total</p>
                @endif
            </div>
            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900/30">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0l-5.898 6.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                    €{{ number_format($stats['total_credits'], 2) }}
                </p>
                @if($this->hasActiveFilters())
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Filtered total</p>
                @endif
            </div>
            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/30">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Debits</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                    €{{ number_format($stats['total_debits'], 2) }}
                </p>
                @if($this->hasActiveFilters())
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Filtered total</p>
                @endif
            </div>
            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900/30">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 11l3 3m0 0l3-3m-3 3V8"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">🔍 Search & Filter Transactions</h3>
        
        <!-- Search Bar -->
        <div class="mb-4">
            <div class="relative">
                <input type="text" 
                       wire:model.live.debounce.300ms="searchQuery" 
                       placeholder="Search by description, amount, or filename..." 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                @if(!empty($searchQuery))
                    <button wire:click="$set('searchQuery', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <!-- Quick Status Filters -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quick Status Filters:</label>
            <div class="flex flex-wrap gap-2">
                <button wire:click="setStatusFilter('pending')" 
                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $statusFilter === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600' }}">
                    ⏳ Unmatched Only
                </button>
                <button wire:click="setStatusFilter('matched')" 
                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $statusFilter === 'matched' ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600' }}">
                    ✅ Matched
                </button>
                <button wire:click="setStatusFilter('reconciled')" 
                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $statusFilter === 'reconciled' ? 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600' }}">
                    🔄 Reconciled
                </button>
                <button wire:click="setStatusFilter('ignored')" 
                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $statusFilter === 'ignored' ? 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600' }}">
                    🚫 Ignored
                </button>
            </div>
        </div>

        <!-- Compact Date Navigation and Filters -->
        <div class="space-y-3">
            <!-- Quick Date Selection with integrated month navigator -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">📅 Quick Date Selection:</label>
                <div class="flex items-center justify-between gap-2">
                    <!-- Quick selection buttons -->
                    <div class="flex flex-wrap gap-1.5">
                        <button wire:click="setCurrentMonth" 
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            📅 Current Month
                        </button>
                        <button wire:click="setPreviousMonth" 
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            ⏪ Previous Month
                        </button>
                        <button wire:click="setLastThreeMonths" 
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            📊 Last 3 Months
                        </button>
                        <button wire:click="setThisYear" 
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            📆 This Year
                        </button>
                    </div>
                    
                    <!-- Compact month navigator -->
                    <div class="flex items-center gap-2 ml-auto">
                        <button wire:click="navigateToPreviousMonth"
                                class="p-1.5 rounded-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-3.5 h-3.5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $this->currentMonthDisplay }}
                        </div>
                        <button wire:click="navigateToNextMonth"
                                class="p-1.5 rounded-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-3.5 h-3.5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date and Amount Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2">
                <!-- Date Range -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">From Date:</label>
                    <input type="date" wire:model.live="dateFrom" 
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">To Date:</label>
                    <input type="date" wire:model.live="dateTo" 
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>

                <!-- Amount Range -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">Min Amount (€):</label>
                    <input type="number" step="0.01" wire:model.live.debounce.500ms="amountFrom" placeholder="0.00"
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">Max Amount (€):</label>
                    <input type="number" step="0.01" wire:model.live.debounce.500ms="amountTo" placeholder="10000.00"
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <!-- Transaction Type Filter -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Transaction Type:</label>
                <div class="flex gap-2">
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="transactionType" value="" class="form-radio h-4 w-4 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">All</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="transactionType" value="debit" class="form-radio h-4 w-4 text-red-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Debits Only</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="transactionType" value="credit" class="form-radio h-4 w-4 text-green-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Credits Only</span>
                </label>
                </div>
            </div>

            <!-- Credit Category Filter -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Credit Category:</label>
                <div class="flex flex-wrap gap-2">
                @php
                    $creditCategories = \App\Models\BankTransaction::getCreditCategories();
                @endphp
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="creditCategoryFilter" value="" class="form-radio h-4 w-4 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">All Categories</span>
                </label>
                @foreach($creditCategories as $key => $label)
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="creditCategoryFilter" value="{{ $key }}" class="form-radio h-4 w-4 text-green-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        @if($key === 'card_lodgement')
                            💳 {{ $label }}
                        @elseif($key === 'cash_lodgement')
                            💰 {{ $label }}
                        @else
                            📈 {{ $label }}
                        @endif
                    </span>
                </label>
                @endforeach
                </div>
            </div>
        </div>
        <!-- End of compact filters wrapper -->

        <!-- Clear Filters & Results Count -->
        <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                @if($this->hasActiveFilters())
                    Showing {{ $this->filteredCount }} of {{ \App\Models\BankTransaction::count() }} transactions
                @else
                    Showing all {{ $transactions->total() }} transactions
                @endif
            </div>
            
            <div class="flex gap-2">
                @if($this->hasActiveFilters())
                    <button wire:click="clearFilters" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear All Filters
                    </button>
                @endif
                
                <!-- Bulk Selection Button -->
                <button wire:click="selectAllVisible" 
                        class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700 dark:hover:bg-blue-800/30">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ $selectAll ? 'Deselect All' : 'Select Unmatched' }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow" wire:loading.class="opacity-75">
    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction History</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            @if($this->hasActiveFilters())
                Filtered results - {{ $this->filteredCount }} transactions found
            @else
                All imported bank transactions sorted by date (most recent first).
            @endif
        </p>
    </div>
    
    @if($transactions->count() > 0)
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">
                        <span class="sr-only">Select</span>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Debit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Credit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Source</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($transactions as $transaction)
                @php
                    $prediction = $this->getPredictedExpense($transaction);
                    $canSelect = in_array($transaction->status, ['pending', 'unmatched']);
                    $isSelected = in_array($transaction->id, $selectedTransactions);
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                    <!-- Selection Checkbox -->
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($canSelect)
                            <input type="checkbox" 
                                   wire:model.live="selectedTransactions"
                                   value="{{ $transaction->id }}"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        @endif
                    </td>
                    
                    <!-- Date Column -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        {{ $transaction->transaction_date->format('M j, Y') }}
                    </td>
                    
                    <!-- Description Column with Prediction Badge -->
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white max-w-xs">
                        <div class="flex items-center space-x-2">
                            <div class="truncate" title="{{ $transaction->description }}">
                                {{ $transaction->description }}
                            </div>
                            @if($prediction)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $prediction['color'] }}" 
                                      title="{{ $prediction['description'] ?? $prediction['reason'] ?? 'AI Prediction' }} ({{ $prediction['confidence'] }}% confidence)">
                                    {{ $prediction['icon'] }} {{ $prediction['confidence'] }}%
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        @if($transaction->debit_amount > 0)
                            <span class="font-medium text-red-600 dark:text-red-400">
                                -€{{ number_format($transaction->debit_amount, 2) }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        @if($transaction->credit_amount > 0)
                            <span class="font-medium text-green-600 dark:text-green-400">
                                +€{{ number_format($transaction->credit_amount, 2) }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                        €{{ number_format($transaction->balance, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                'matched' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                'fully_matched' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                'categorized' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                'reconciled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                'ignored' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
                                'unmatched' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                            ];
                            $statusColor = $statusColors[$transaction->status ?? 'pending'] ?? $statusColors['pending'];
                        @endphp
                        <div class="space-y-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                @if($transaction->status === 'fully_matched')
                                    Multi-Invoice
                                @elseif($transaction->status === 'categorized')
                                    Categorized
                                @else
                                    {{ ucfirst($transaction->status ?? 'pending') }}
                                @endif
                            </span>
                            @if($transaction->isCreditTransaction() && $transaction->credit_category)
                                <div class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    @if($transaction->credit_category === 'card_lodgement')
                                        💳 Card
                                    @elseif($transaction->credit_category === 'cash_lodgement')
                                        💰 Cash
                                    @else
                                        📈 Other
                                    @endif
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 max-w-32">
                        <div class="truncate" title="{{ $transaction->source_filename }}">
                            {{ $transaction->source_filename }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        @if($transaction->status === 'pending' || $transaction->status === 'unmatched')
                            <button onclick="console.log('Opening reconciliation panel for transaction:', '{{ $transaction->id }}'); Livewire.dispatchTo('bank-reconciliation-panel', 'openReconciliationPanel', ['{{ $transaction->id }}'])" 
                                    class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                🔄 Reconcile
                            </button>
                        @elseif($transaction->status === 'matched')
                            <div class="flex items-center space-x-2">
                                <span class="text-green-600 dark:text-green-400 text-xs">
                                    ✅ Matched
                                    @if($transaction->reconciliation_id)
                                        with #{{ \App\Models\Invoice::find($transaction->reconciliation_id)?->invoice_number ?? $transaction->reconciliation_id }}
                                    @endif
                                </span>
                                <button onclick="console.log('Opening edit for transaction:', '{{ $transaction->id }}'); Livewire.dispatchTo('bank-reconciliation-panel', 'openReconciliationPanel', ['{{ $transaction->id }}'])" 
                                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                    Edit
                                </button>
                            </div>
                        @elseif($transaction->status === 'fully_matched')
                            <div class="flex items-center space-x-2">
                                @if($transaction->allocations && $transaction->allocations->count() > 1)
                                    <div class="text-green-600 dark:text-green-400 text-xs">
                                        <div class="flex items-center gap-1 mb-1">
                                            🎯 <strong>Multi-Invoice Allocation</strong>
                                        </div>
                                        @foreach($transaction->allocations as $allocation)
                                            <div class="ml-2 text-xs">
                                                ✓ #{{ $allocation->invoice->invoice_number }} (€{{ number_format($allocation->allocated_amount, 2) }})
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-green-600 dark:text-green-400 text-xs">
                                        ✅ Fully Matched
                                    </span>
                                @endif
                                <button onclick="console.log('Opening edit for transaction:', '{{ $transaction->id }}'); Livewire.dispatchTo('bank-reconciliation-panel', 'openReconciliationPanel', ['{{ $transaction->id }}'])" 
                                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                    Edit
                                </button>
                            </div>
                        @elseif($transaction->status === 'categorized')
                            <div class="flex items-center space-x-2">
                                <span class="text-green-600 dark:text-green-400 text-xs">
                                    ✅ {{ $transaction->credit_category_display }}
                                </span>
                                <button onclick="console.log('Opening edit for transaction:', '{{ $transaction->id }}'); Livewire.dispatchTo('bank-reconciliation-panel', 'openReconciliationPanel', ['{{ $transaction->id }}'])" 
                                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                    Edit
                                </button>
                            </div>
                        @elseif($transaction->status === 'ignored')
                            <div class="flex items-center space-x-2">
                                <span class="text-gray-500 dark:text-gray-400 text-xs">🚫 Ignored</span>
                                <button onclick="console.log('Opening edit for transaction:', '{{ $transaction->id }}'); Livewire.dispatchTo('bank-reconciliation-panel', 'openReconciliationPanel', ['{{ $transaction->id }}'])" 
                                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                    Edit
                                </button>
                            </div>
                        @else
                            <button onclick="console.log('Opening review for transaction:', '{{ $transaction->id }}'); Livewire.dispatchTo('bank-reconciliation-panel', 'openReconciliationPanel', ['{{ $transaction->id }}'])" 
                                    class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                Review
                            </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($transactions->hasPages())
    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
        {{ $transactions->links() }}
    </div>
    @endif

    @else
    <div class="p-8 text-center">
        @if($this->hasActiveFilters())
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No transactions match your filters</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">
                Try adjusting your search criteria or clearing your filters to see more results.
            </p>
            <button wire:click="clearFilters" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Clear All Filters
            </button>
        @else
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No transactions found</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">Upload a bank statement CSV file to start importing transactions.</p>
            <a href="{{ route('management.bank-statements.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                Upload Statement →
            </a>
        @endif
    </div>
    @endif
</div>

<!-- Sticky Bulk Actions Bar -->
@if($showBulkActions)
<div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-30">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center space-x-4">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ count($selectedTransactions) }} transaction{{ count($selectedTransactions) > 1 ? 's' : '' }} selected
            </span>
            @php
                $selectedData = App\Models\BankTransaction::whereIn('id', $selectedTransactions)
                    ->selectRaw('
                        SUM(CASE WHEN debit_amount > 0 THEN debit_amount ELSE credit_amount END) as total,
                        SUM(CASE WHEN credit_amount > 0 THEN 1 ELSE 0 END) as credit_count,
                        SUM(CASE WHEN debit_amount > 0 THEN 1 ELSE 0 END) as debit_count
                    ')
                    ->first();
                    
                $selectedTotal = $selectedData->total ?? 0;
                $creditCount = $selectedData->credit_count ?? 0;
                $debitCount = $selectedData->debit_count ?? 0;
            @endphp
            <span class="text-sm text-gray-500 dark:text-gray-400">
                (€{{ number_format($selectedTotal, 2) }} total
                @if($creditCount > 0 && $debitCount > 0)
                    • {{ $creditCount }} credits, {{ $debitCount }} debits
                @elseif($creditCount > 0)
                    • {{ $creditCount }} credit{{ $creditCount > 1 ? 's' : '' }}
                @elseif($debitCount > 0)
                    • {{ $debitCount }} debit{{ $debitCount > 1 ? 's' : '' }}
                @endif
                )
            </span>
        </div>
        
        <div class="flex items-center space-x-2">
            <!-- Bulk Category Assignment (only show for credit transactions) -->
            @if($creditCount > 0)
            <div class="flex items-center space-x-2">
                @if($debitCount > 0)
                    <div class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded">
                        ℹ️ Categories apply only to {{ $creditCount }} credit{{ $creditCount > 1 ? 's' : '' }}
                    </div>
                @endif
                
                <select wire:model="bulkCreditCategory" 
                        class="block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                    <option value="">Select Category</option>
                    <option value="card_lodgement">💳 Card Lodgements</option>
                    <option value="cash_lodgement">💰 Cash Lodgements</option>
                    <option value="rent">🏠 Rent</option>
                    <option value="other_credit">📈 Other Credits</option>
                </select>
                
                <button wire:click="bulkCategorizeSelected" 
                        wire:confirm="Are you sure you want to categorize {{ $creditCount }} credit transaction{{ $creditCount > 1 ? 's' : '' }} as {{ $bulkCreditCategory ? \App\Models\BankTransaction::getCreditCategories()[$bulkCreditCategory] ?? $bulkCreditCategory : 'selected category' }}?@if($debitCount > 0) ({{ $debitCount }} debit transaction{{ $debitCount > 1 ? 's' : '' }} will be skipped)@endif"
                        @if(empty($bulkCreditCategory)) disabled @endif
                        class="inline-flex items-center px-4 py-2 {{ $bulkCreditCategory ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed' }} text-white text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Categorize {{ $creditCount }} Credit{{ $creditCount > 1 ? 's' : '' }}
                </button>
            </div>
            @endif
            
            <!-- Bulk Category Assignment (for debit transactions) -->
            @if($debitCount > 0)
            <div class="flex items-center space-x-2">
                @if($creditCount > 0)
                    <div class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded">
                        ℹ️ Categories apply only to {{ $debitCount }} debit{{ $debitCount > 1 ? 's' : '' }}
                    </div>
                @endif
                
                <select wire:model.live="bulkDebitCategory" 
                        class="block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring-red-500 text-sm">
                    <option value="">Select Expense Category</option>
                    <option value="STOCK">📦 Stock Purchases</option>
                    <option value="UTILITIES">⚡ Utilities</option>
                    <option value="RENT">🏠 Rent & Rates</option>
                    <option value="WAGES">💰 Wages & Salaries</option>
                    <option value="MARKETING">📢 Marketing & Advertising</option>
                    <option value="INSURANCE">🛡️ Insurance</option>
                    <option value="REPAIRS">🔧 Repairs & Maintenance</option>
                    <option value="OFFICE">🗃️ Office Supplies</option>
                    <option value="PROFESSIONAL">👔 Professional Fees</option>
                    <option value="OTHER">📋 Other Expenses</option>
                </select>
                
                <button wire:click="bulkCategorizeDebitsSelected" 
                        @if(empty($bulkDebitCategory)) disabled @endif
                        class="inline-flex items-center px-4 py-2 {{ $bulkDebitCategory ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-400 cursor-not-allowed' }} text-white text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                        onclick="if (!confirm('Are you sure you want to categorize the selected debit transactions as expenses?')) { event.stopPropagation(); return false; }">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Categorize {{ $debitCount }} Debit{{ $debitCount > 1 ? 's' : '' }}
                </button>
            </div>
            @endif
            
            <div class="h-6 border-l border-gray-300 dark:border-gray-600"></div>
            
            <button wire:click="previewBulkReconciliation" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Auto-Reconcile Selected
            </button>
            
            <button wire:click="clearSelection" 
                    class="inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-500 transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Clear
            </button>
        </div>
    </div>
</div>
@endif

<!-- Bulk Reconciliation Preview Modal -->
@if($showPreviewModal)
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-40" wire:click="closePreviewModal">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800" wire:click.stop>
        <div class="mt-3">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    🚀 Bulk Auto-Reconciliation Preview
                </h3>
                <button wire:click="closePreviewModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Content -->
            <div class="py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ count($previewData) }} transaction{{ count($previewData) > 1 ? 's' : '' }} will be processed:
                </p>
                
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @foreach($previewData as $item)
                    @php
                        $transaction = $item['transaction'];
                        $prediction = $item['prediction'];
                        $amount = $item['amount'];
                    @endphp
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate" title="{{ $transaction->description }}">
                                {{ $transaction->description }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $transaction->transaction_date->format('M j, Y') }}
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                €{{ number_format($amount, 2) }}
                            </div>
                            
                            @if($prediction)
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg">{{ $prediction['icon'] }}</span>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $prediction['description'] }}
                                        </div>
                                        <div class="text-xs {{ $prediction['color'] }} px-2 py-1 rounded-full">
                                            {{ $prediction['confidence'] }}% confidence
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-sm text-red-600 dark:text-red-400">
                                    ❌ No prediction available
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                
                @php
                    $totalAmount = array_sum(array_column($previewData, 'amount'));
                    $predictedCount = count(array_filter($previewData, function($item) {
                        if (!$item['prediction']) return false;
                        // Use same threshold logic as processing: 30% for credits, 50% for debits
                        $minConfidence = $item['transaction']->isCreditTransaction() ? 30 : 50;
                        return $item['prediction']['confidence'] >= $minConfidence;
                    }));
                @endphp
                
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <div class="text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700 dark:text-gray-300">Total Amount:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">€{{ number_format($totalAmount, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-gray-700 dark:text-gray-300">Ready to Process:</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ $predictedCount }} transactions</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button wire:click="closePreviewModal" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-500">
                    Cancel
                </button>
                <button wire:click="processBulkReconciliation" 
                        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    🚀 Process {{ $predictedCount }} Transaction{{ $predictedCount > 1 ? 's' : '' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Loading indicator -->
<div wire:loading class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-40">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 flex items-center space-x-4">
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-gray-900 dark:text-white">Updating transactions...</span>
    </div>
</div>
</div>