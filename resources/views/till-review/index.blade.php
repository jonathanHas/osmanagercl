<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Receipts
        </h2>
    </x-slot>

    <div class="py-6" x-data="tillReview()">
        <!-- Progress Overlay -->
        <div x-show="showProgress" x-transition.opacity class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
            <div class="relative flex items-center justify-center min-h-screen p-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-8">
                    <div class="text-center mb-6">
                        <div x-show="!progressDone" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3">
                            <i class="fas fa-circle-notch fa-spin text-xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div x-show="progressDone" x-cloak class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 mb-3">
                            <i class="fas fa-check text-xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="progressTitle"></h3>
                    </div>

                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-6">
                        <div class="h-2 rounded-full transition-all duration-500 ease-out"
                             :class="progressDone ? 'bg-green-500' : 'bg-blue-600'"
                             :style="'width: ' + progressPercent + '%'"></div>
                    </div>

                    <div class="space-y-2">
                        <template x-for="step in progressSteps" :key="step.id">
                            <div class="flex items-center gap-3">
                                <template x-if="step.status === 'active'">
                                    <i class="fas fa-circle-notch fa-spin text-sm text-blue-600 dark:text-blue-400 w-4"></i>
                                </template>
                                <template x-if="step.status === 'done'">
                                    <i class="fas fa-check text-sm text-green-600 dark:text-green-400 w-4"></i>
                                </template>
                                <template x-if="step.status === 'warning'">
                                    <i class="fas fa-exclamation-triangle text-sm text-amber-500 w-4"></i>
                                </template>
                                <template x-if="step.status === 'error'">
                                    <i class="fas fa-times text-sm text-red-600 w-4"></i>
                                </template>
                                <span class="text-sm"
                                      :class="{
                                          'text-blue-700 dark:text-blue-300 font-medium': step.status === 'active',
                                          'text-green-700 dark:text-green-300': step.status === 'done',
                                          'text-amber-700 dark:text-amber-300': step.status === 'warning',
                                          'text-red-700 dark:text-red-300': step.status === 'error'
                                      }"
                                      x-text="step.message"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Date Selector and Summary Cards -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-wrap items-center justify-between mb-6">
                        <div class="flex items-center space-x-2 sm:space-x-3">
                            <button @@click="changeDate(-1)" :disabled="loading"
                                    class="p-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition disabled:opacity-50"
                                    title="Previous day">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <input type="date"
                                   x-model="selectedDate"
                                   @@change="loadTransactions()"
                                   value="{{ $selectedDate->format('Y-m-d') }}"
                                   max="{{ now()->format('Y-m-d') }}"
                                   :disabled="loading"
                                   class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <button @@click="changeDate(1)" :disabled="loading || selectedDate >= new Date().toISOString().split('T')[0]"
                                    class="p-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition disabled:opacity-50"
                                    title="Next day">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button @@click="goToToday()" :disabled="loading"
                                    class="px-3 py-2 text-sm bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition disabled:opacity-50">
                                Today
                            </button>

                            <button @@click="refreshCache()" :disabled="loading"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition disabled:opacity-50">
                                <i class="fas fa-sync-alt mr-2" :class="loading && 'fa-spin'"></i>Refresh Cache
                            </button>

                            <!-- Loading indicator -->
                            <span x-show="loading" x-transition class="flex items-center text-sm text-blue-600 dark:text-blue-400">
                                <i class="fas fa-circle-notch fa-spin mr-2"></i>Loading...
                            </span>
                        </div>

                        <div class="flex space-x-2 mt-4 sm:mt-0">
                            <button @@click="exportData('csv')" :disabled="loading"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition disabled:opacity-50">
                                <i class="fas fa-file-csv mr-2"></i>Export CSV
                            </button>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-4 transition-opacity" :class="loading && 'opacity-50'">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Sales</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                €<span x-text="summary.total_sales.toFixed(2)"></span>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">Transactions</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="summary.total_transactions">
                            </div>
                        </div>
                        
                        <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                            <div class="text-sm text-green-600 dark:text-green-400">Cash</div>
                            <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                                €<span x-text="summary.cash_total.toFixed(2)"></span>
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 dark:bg-purple-900 rounded-lg p-4">
                            <div class="text-sm text-purple-600 dark:text-purple-400">Card</div>
                            <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                                €<span x-text="summary.card_total.toFixed(2)"></span>
                            </div>
                        </div>
                        
                        <div class="bg-orange-50 dark:bg-orange-900 rounded-lg p-4">
                            <div class="text-sm text-orange-600 dark:text-orange-400">Free</div>
                            <div class="text-2xl font-bold text-orange-900 dark:text-orange-100">
                                €<span x-text="summary.free_total.toFixed(2)"></span>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4">
                            <div class="text-sm text-yellow-600 dark:text-yellow-400">Debt</div>
                            <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">
                                €<span x-text="summary.debt_total.toFixed(2)"></span>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                            <div class="text-sm text-blue-600 dark:text-blue-400">Drawer Opens</div>
                            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100" x-text="summary.drawer_opens">
                            </div>
                        </div>
                        
                        <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4">
                            <div class="text-sm text-red-600 dark:text-red-400">Voids</div>
                            <div class="text-2xl font-bold text-red-900 dark:text-red-100" x-text="summary.voided_items_count">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hourly Sales Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                        <i class="fas fa-chart-bar mr-2"></i>Hourly Sales
                    </h3>
                    <div class="relative" style="height: 300px;">
                        <canvas id="hourlySalesChart"></canvas>
                        <!-- Chart loading overlay -->
                        <div x-show="chartLoading" x-transition
                             class="absolute inset-0 bg-white/70 dark:bg-gray-800/70 flex items-center justify-center rounded">
                            <div class="text-center">
                                <i class="fas fa-circle-notch fa-spin text-2xl text-blue-500 mb-2"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Loading chart...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Filters</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Transaction Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type
                            </label>
                            <select x-model="filters.type" @@change="loadTransactions()"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="">All Types</option>
                                <option value="receipt">Receipts</option>
                                <option value="drawer">Drawer Opens</option>
                                <option value="removed">Voided Items</option>
                                <option value="card">Card Transactions</option>
                            </select>
                        </div>

                        <!-- Terminal -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Terminal
                            </label>
                            <select x-model="filters.terminal" @@change="loadTransactions()"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="">All Terminals</option>
                                @foreach($terminals as $terminal)
                                    <option value="{{ $terminal }}">{{ $terminal }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Cashier -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Cashier
                            </label>
                            <select x-model="filters.cashier" @@change="loadTransactions()"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="">All Cashiers</option>
                                @foreach($cashiers as $cashier)
                                    <option value="{{ $cashier }}">{{ $cashier }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Search
                            </label>
                            <input type="text" 
                                   x-model="filters.search"
                                   @@keyup.debounce.300ms="loadTransactions()"
                                   placeholder="Search products..."
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>
                    </div>

                    <!-- Time and Amount Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Time From
                            </label>
                            <input type="time" 
                                   x-model="filters.time_from"
                                   @@change="loadTransactions()"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Time To
                            </label>
                            <input type="time" 
                                   x-model="filters.time_to"
                                   @@change="loadTransactions()"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Min Amount (€)
                            </label>
                            <input type="number" 
                                   x-model="filters.min_amount"
                                   @@change="loadTransactions()"
                                   step="0.01"
                                   min="0"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Max Amount (€)
                            </label>
                            <input type="number" 
                                   x-model="filters.max_amount"
                                   @@change="loadTransactions()"
                                   step="0.01"
                                   min="0"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>
                    </div>

                    <div class="mt-4 flex justify-between items-center">
                        <!-- Active Payment Filter Indicator -->
                        <div x-show="filters.payment_type" x-transition class="flex items-center space-x-2">
                            <div class="px-3 py-1 rounded-full text-sm font-medium"
                                 :class="getPaymentTypeColor(filters.payment_type).badge">
                                <i class="fas fa-filter mr-1"></i>
                                Filtering by: <span x-text="filters.payment_type.toUpperCase()"></span>
                            </div>
                            <button @@click="clearPaymentTypeFilter()"
                                    class="px-2 py-1 bg-red-100 text-red-800 rounded-md hover:bg-red-200 transition text-sm">
                                <i class="fas fa-times mr-1"></i>Clear
                            </button>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button @@click="clearFilters()"
                                    class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                                Clear All Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtered Summary -->
            <div x-show="hasActiveFilters() && filteredSummary.total_transactions > 0" 
                 x-transition
                 class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <h3 class="text-lg font-semibold mb-3 text-amber-800 dark:text-amber-200 flex items-center">
                        <i class="fas fa-filter mr-2"></i>
                        Filtered Results Summary
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-3">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Filtered Sales</div>
                            <div class="text-lg font-bold text-gray-900 dark:text-white">
                                €<span x-text="filteredSummary.total_sales.toFixed(2)"></span>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Filtered Transactions</div>
                            <div class="text-lg font-bold text-gray-900 dark:text-white" x-text="filteredSummary.total_transactions">
                            </div>
                        </div>
                        
                        <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                             @@click="filterByPaymentType('cash')">
                            <div class="text-xs text-green-600 dark:text-green-400">Filtered Cash</div>
                            <div class="text-lg font-bold text-green-900 dark:text-green-100">
                                €<span x-text="filteredSummary.cash_total.toFixed(2)"></span>
                            </div>
                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                <i class="fas fa-filter mr-1"></i>Click to filter
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 dark:bg-purple-900 rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                             @@click="filterByPaymentType('magcard')">
                            <div class="text-xs text-purple-600 dark:text-purple-400">Filtered Card</div>
                            <div class="text-lg font-bold text-purple-900 dark:text-purple-100">
                                €<span x-text="filteredSummary.card_total.toFixed(2)"></span>
                            </div>
                            <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                <i class="fas fa-filter mr-1"></i>Click to filter
                            </div>
                        </div>
                        
                        <div class="bg-orange-50 dark:bg-orange-900 rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                             @@click="filterByPaymentType('free')">
                            <div class="text-xs text-orange-600 dark:text-orange-400">Filtered Free</div>
                            <div class="text-lg font-bold text-orange-900 dark:text-orange-100">
                                €<span x-text="filteredSummary.free_total.toFixed(2)"></span>
                            </div>
                            <div class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                                <i class="fas fa-filter mr-1"></i>Click to filter
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                             @@click="filterByPaymentType('debt')">
                            <div class="text-xs text-yellow-600 dark:text-yellow-400">Filtered Debt</div>
                            <div class="text-lg font-bold text-yellow-900 dark:text-yellow-100">
                                €<span x-text="filteredSummary.debt_total.toFixed(2)"></span>
                            </div>
                            <div class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                                <i class="fas fa-filter mr-1"></i>Click to filter
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3 shadow-sm">
                            <div class="text-xs text-blue-600 dark:text-blue-400">Filtered Drawers</div>
                            <div class="text-lg font-bold text-blue-900 dark:text-blue-100" x-text="filteredSummary.drawer_opens">
                            </div>
                        </div>
                        
                        <div class="bg-red-50 dark:bg-red-900 rounded-lg p-3 shadow-sm">
                            <div class="text-xs text-red-600 dark:text-red-400">Filtered Voids</div>
                            <div class="text-lg font-bold text-red-900 dark:text-red-100" x-text="filteredSummary.voided_items_count">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                        <i class="fas fa-info-circle mr-1"></i>
                        Showing totals for <span x-text="transactionCount"></span> filtered transactions
                    </div>
                </div>
            </div>

            <!-- Transactions List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Transactions 
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                (<span x-text="transactionCount"></span> results)
                            </span>
                        </h3>
                        
                        <!-- Expand/Collapse Controls -->
                        <div x-show="transactionCount > 0" class="flex space-x-2">
                            <span x-show="filters.search && filters.search.trim() !== ''" 
                                  class="text-xs text-blue-600 dark:text-blue-400 self-center mr-2">
                                <i class="fas fa-info-circle mr-1"></i>Details auto-expanded for search
                            </span>
                            <button @@click="expandAllReceipts()"
                                    class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded-md hover:bg-blue-200 transition">
                                <i class="fas fa-expand-arrows-alt mr-1"></i>Expand All
                            </button>
                            <button @@click="collapseAllReceipts()"
                                    class="px-3 py-1 text-xs bg-gray-100 text-gray-800 rounded-md hover:bg-gray-200 transition">
                                <i class="fas fa-compress-arrows-alt mr-1"></i>Collapse All
                            </button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div x-show="loading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">Loading transactions...</p>
                    </div>

                    <!-- Transactions -->
                    <div x-show="!loading" class="space-y-2">
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400" x-show="transactions.length === 0">
                            No transactions found for the selected filters.
                        </div>
                        
                        <div x-show="transactions.length > 0">
                            <div x-html="renderTransactions()"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function tillReview() {
            return {
                selectedDate: '{{ $selectedDate->format('Y-m-d') }}',
                loading: false,
                chartLoading: false,
                showProgress: false,
                progressDone: false,
                progressTitle: 'Loading...',
                progressPercent: 0,
                progressSteps: [],
                transactions: [],
                transactionCount: 0,
                hourlySalesChart: null,
                summary: {
                    total_sales: {{ $summary->total_sales ?? 0 }},
                    total_transactions: {{ $summary->total_transactions ?? 0 }},
                    cash_total: {{ $summary->cash_total ?? 0 }},
                    card_total: {{ $summary->card_total ?? 0 }},
                    other_total: {{ $summary->other_total ?? 0 }},
                    free_total: {{ $summary->free_total ?? 0 }},
                    debt_total: {{ $summary->debt_total ?? 0 }},
                    drawer_opens: {{ $summary->drawer_opens ?? 0 }},
                    voided_items_count: {{ $summary->voided_items_count ?? 0 }}
                },
                filteredSummary: {
                    total_sales: 0,
                    total_transactions: 0,
                    cash_total: 0,
                    card_total: 0,
                    other_total: 0,
                    free_total: 0,
                    debt_total: 0,
                    drawer_opens: 0,
                    voided_items_count: 0
                },
                filters: {
                    type: '',
                    terminal: '',
                    cashier: '',
                    time_from: '',
                    time_to: '',
                    search: '',
                    min_amount: '',
                    max_amount: '',
                    payment_type: ''
                },

                // Define consistent payment type colors
                getPaymentTypeColor(paymentType) {
                    const colors = {
                        'cash': {
                            bg: 'bg-green-50 dark:bg-green-900/20',
                            border: 'border-green-200 dark:border-green-800',
                            text: 'text-green-800 dark:text-green-200',
                            badge: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        },
                        'magcard': {
                            bg: 'bg-purple-50 dark:bg-purple-900/20',
                            border: 'border-purple-200 dark:border-purple-800',
                            text: 'text-purple-800 dark:text-purple-200',
                            badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                        },
                        'free': {
                            bg: 'bg-orange-50 dark:bg-orange-900/20',
                            border: 'border-orange-200 dark:border-orange-800',
                            text: 'text-orange-800 dark:text-orange-200',
                            badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
                        },
                        'debt': {
                            bg: 'bg-yellow-50 dark:bg-yellow-900/20',
                            border: 'border-yellow-200 dark:border-yellow-800',
                            text: 'text-yellow-800 dark:text-yellow-200',
                            badge: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                        },
                        'default': {
                            bg: 'bg-gray-50 dark:bg-gray-700',
                            border: 'border-gray-200 dark:border-gray-600',
                            text: 'text-gray-800 dark:text-gray-200',
                            badge: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                        }
                    };
                    
                    return colors[paymentType] || colors['default'];
                },

                init() {
                    // Set up global toggle function
                    window.tillReviewToggleDetails = (index) => {
                        this.toggleDetails(this.transactions[index]);
                    };

                    this.loadTransactions();
                },

                changeDate(days) {
                    const d = new Date(this.selectedDate);
                    d.setDate(d.getDate() + days);
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    if (d > today) return;
                    this.selectedDate = d.toISOString().split('T')[0];
                    this.loadTransactions();
                },

                goToToday() {
                    this.selectedDate = new Date().toISOString().split('T')[0];
                    this.loadTransactions();
                },

                addStep(id, message, status = 'active') {
                    const existing = this.progressSteps.find(s => s.id === id);
                    if (existing) {
                        existing.message = message;
                        existing.status = status;
                    } else {
                        this.progressSteps.push({ id, message, status });
                    }
                },

                completeStep(id, message) {
                    this.addStep(id, message, 'done');
                },

                async checkCacheStatus() {
                    const params = new URLSearchParams({ date: this.selectedDate });
                    const response = await fetch(`/till-review/cache-status?${params}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    return await response.json();
                },

                async loadHourlySalesChart() {
                    this.chartLoading = true;
                    try {
                        const params = new URLSearchParams({ date: this.selectedDate });
                        const response = await fetch(`/till-review/hourly-sales?${params}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                        const ctx = document.getElementById('hourlySalesChart');
                        if (!ctx) return;

                        const isDark = document.documentElement.classList.contains('dark');
                        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
                        const textColor = isDark ? '#d1d5db' : '#374151';

                        if (this.hourlySalesChart) {
                            this.hourlySalesChart.destroy();
                        }

                        this.hourlySalesChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    {
                                        label: 'Total Sales (€)',
                                        data: data.total_sales,
                                        backgroundColor: isDark ? 'rgba(59, 130, 246, 0.6)' : 'rgba(59, 130, 246, 0.5)',
                                        borderColor: 'rgba(59, 130, 246, 1)',
                                        borderWidth: 1,
                                        borderRadius: 4,
                                        order: 2
                                    },
                                    {
                                        label: 'Coffee Sales (€)',
                                        data: data.coffee_sales,
                                        type: 'line',
                                        borderColor: 'rgba(180, 83, 9, 1)',
                                        backgroundColor: 'rgba(180, 83, 9, 0.15)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(180, 83, 9, 1)',
                                        pointRadius: 4,
                                        pointHoverRadius: 6,
                                        fill: true,
                                        tension: 0.3,
                                        order: 1
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false
                                },
                                plugins: {
                                    legend: {
                                        labels: { color: textColor }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            afterBody: function(tooltipItems) {
                                                const idx = tooltipItems[0].dataIndex;
                                                return 'Transactions: ' + data.transaction_counts[idx];
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { color: gridColor },
                                        ticks: { color: textColor }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: gridColor },
                                        ticks: {
                                            color: textColor,
                                            callback: function(value) { return '€' + value; }
                                        }
                                    }
                                }
                            }
                        });
                    } catch (error) {
                        console.error('Error loading hourly sales chart:', error);
                    } finally {
                        this.chartLoading = false;
                    }
                },

                async loadTransactions() {
                    this.loading = true;
                    this.showProgress = true;
                    this.progressDone = false;
                    this.progressTitle = 'Loading data...';
                    this.progressPercent = 0;
                    this.progressSteps = [];

                    try {
                        // Step 1: Check cache
                        this.addStep('cache', 'Checking cache...');
                        this.progressPercent = 10;
                        const cacheStatus = await this.checkCacheStatus();

                        if (cacheStatus.status === 'valid') {
                            this.completeStep('cache', `Cache valid — ${cacheStatus.cached} receipts`);
                        } else if (cacheStatus.status === 'mismatch') {
                            this.addStep('cache', cacheStatus.message, 'warning');
                        } else if (cacheStatus.status === 'empty') {
                            this.addStep('cache', cacheStatus.message, 'warning');
                        } else {
                            this.completeStep('cache', cacheStatus.message);
                        }
                        this.progressPercent = 25;

                        // Step 2: Load summary + transactions in parallel
                        this.addStep('summary', 'Loading summary...');
                        this.addStep('transactions', 'Loading transactions...');
                        this.progressPercent = 40;

                        await Promise.all([
                            this.loadSummary().then(() => {
                                this.completeStep('summary', `Summary loaded — €${this.summary.total_sales.toFixed(2)} total`);
                                this.progressPercent = Math.max(this.progressPercent, 55);
                            }),
                            this.loadTransactionData().then(() => {
                                this.completeStep('transactions', `${this.transactionCount} transactions loaded`);
                                this.progressPercent = Math.max(this.progressPercent, 70);
                            })
                        ]);

                        this.progressPercent = 80;

                        // Step 3: Chart
                        this.addStep('chart', 'Building chart...');
                        this.loading = false;
                        await this.$nextTick();
                        await this.loadHourlySalesChart();
                        this.completeStep('chart', 'Chart ready');
                        this.progressPercent = 100;

                        // Done
                        this.progressDone = true;
                        this.progressTitle = 'Ready';
                        setTimeout(() => { this.showProgress = false; }, 600);

                    } catch (error) {
                        console.error('Error loading data:', error);
                        this.addStep('error', 'Failed to load data: ' + error.message, 'error');
                        this.progressTitle = 'Something went wrong';
                        setTimeout(() => { this.showProgress = false; }, 3000);
                    } finally {
                        this.loading = false;
                    }
                },

                async loadSummary() {
                    const params = new URLSearchParams({ date: this.selectedDate });
                    
                    const response = await fetch(`/till-review/summary?${params}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    // Convert string amounts to numbers
                    this.summary = {
                        total_sales: parseFloat(data.summary.total_sales || 0),
                        total_transactions: parseInt(data.summary.total_transactions || 0),
                        cash_total: parseFloat(data.summary.cash_total || 0),
                        card_total: parseFloat(data.summary.card_total || 0),
                        other_total: parseFloat(data.summary.other_total || 0),
                        free_total: parseFloat(data.summary.free_total || 0),
                        debt_total: parseFloat(data.summary.debt_total || 0),
                        drawer_opens: parseInt(data.summary.drawer_opens || 0),
                        voided_items_count: parseInt(data.summary.voided_items_count || 0)
                    };
                    console.log('Summary loaded:', this.summary);
                },

                async loadTransactionData() {
                    const params = new URLSearchParams({
                        date: this.selectedDate,
                        ...this.filters
                    });

                    const response = await fetch(`/till-review/transactions?${params}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    // Auto-expand receipt details if search is active
                    const autoExpandDetails = this.filters.search && this.filters.search.trim() !== '';
                    this.transactions = data.transactions.map(t => ({...t, showDetails: autoExpandDetails && t.type === 'receipt'}));
                    this.transactionCount = data.count;
                    
                    // Calculate filtered summary
                    this.calculateFilteredSummary();
                    
                    console.log('Transactions loaded:', data.count, autoExpandDetails ? '(auto-expanded for search)' : '');
                },

                toggleDetails(transaction) {
                    transaction.showDetails = !transaction.showDetails;
                    // Force re-render by updating the transactions array
                    this.transactions = [...this.transactions];
                },

                getTypeClass(type) {
                    const classes = {
                        'receipt': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                        'drawer': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        'removed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        'card': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                    };
                    return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                },

                formatAmount(amount) {
                    return parseFloat(amount || 0).toFixed(2);
                },

                // Highlight search terms in text
                highlightSearchTerm(text, searchTerm) {
                    // Handle null/undefined text by converting to empty string
                    if (text === null || text === undefined) {
                        return '';
                    }
                    
                    // Convert to string to ensure we're working with text
                    text = String(text);
                    
                    if (!searchTerm || !searchTerm.trim()) {
                        return text;
                    }
                    
                    // Escape special regex characters in search term
                    const escaped = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const regex = new RegExp(`(${escaped})`, 'gi');
                    
                    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800 dark:text-yellow-100 px-1 rounded">$1</mark>');
                },

                renderTransactions() {
                    if (this.transactions.length === 0) {
                        return '<div class="text-center py-8 text-gray-500 dark:text-gray-400">No transactions found for the selected filters.</div>';
                    }

                    return this.transactions.map((transaction, index) => {
                        // Add defensive checks for transaction properties
                        if (!transaction) return '';
                        
                        // Get payment type color scheme for receipts
                        const paymentType = transaction.details?.payment_type;
                        const isReceipt = (transaction.type || '') === 'receipt';
                        const colorScheme = isReceipt && paymentType ? this.getPaymentTypeColor(paymentType) : this.getPaymentTypeColor('default');
                        
                        const cardClasses = isReceipt && paymentType ? 
                            'border ' + colorScheme.border + ' ' + colorScheme.bg + ' hover:shadow-md' : 
                            'border dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700';
                        
                        let html = '<div class="' + cardClasses + ' rounded-lg p-4 transition cursor-pointer" onclick="window.tillReviewToggleDetails(' + index + ')">';
                        html += '<div class="flex items-center justify-between">';
                        html += '<div class="flex items-center space-x-4">';
                        html += '<span class="text-sm font-mono text-gray-600 dark:text-gray-400">' + (transaction.time || '') + '</span>';
                        html += '<span class="px-2 py-1 text-xs font-semibold rounded-full ' + this.getTypeClassString(transaction.type || '') + '">';
                        html += (transaction.type_display || 'Unknown') + '</span>';
                        
                        if (isReceipt && paymentType) {
                            html += '<span class="px-2 py-1 text-xs font-semibold rounded-full ' + colorScheme.badge + '">';
                            html += paymentType.toUpperCase() + '</span>';
                        }
                        
                        const textColor = isReceipt && paymentType ? colorScheme.text : 'text-gray-900 dark:text-white';
                        // Highlight search term in description
                        const searchTerm = this.filters.search && this.filters.search.trim();
                        const highlightedDescription = this.highlightSearchTerm(transaction.description || '', searchTerm);
                        html += '<span class="text-sm ' + textColor + '">' + highlightedDescription + '</span>';
                        html += '</div>';
                        html += '<div class="flex items-center space-x-4">';
                        html += '<span class="font-semibold ' + textColor + '">€' + this.formatAmount(transaction.amount || 0) + '</span>';
                        html += '<i class="fas fa-chevron-down text-gray-400 transition-transform ' + (transaction.showDetails ? 'rotate-180' : '') + '"></i>';
                        html += '</div>';
                        html += '</div>';
                        
                        if (transaction.showDetails) {
                            html += this.renderTransactionDetails(transaction);
                        }
                        
                        html += '</div>';
                        return html;
                    }).join('');
                },

                renderTransactionDetails(transaction) {
                    if (!transaction || !transaction.details) {
                        return '';
                    }
                    
                    const searchTerm = this.filters.search && this.filters.search.trim();
                    let html = '<div class="mt-4 pt-4 border-t dark:border-gray-600">';
                    html += '<div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">';
                    
                    if (transaction.details.receipt_id) {
                        const highlightedReceiptId = this.highlightSearchTerm(transaction.details.receipt_id, searchTerm);
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Receipt ID:</span><span class="ml-2 font-mono">' + highlightedReceiptId + '</span></div>';
                    }
                    if (transaction.details.ticket_id) {
                        const highlightedTicketId = this.highlightSearchTerm(transaction.details.ticket_id, searchTerm);
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Ticket ID:</span><span class="ml-2 font-mono">' + highlightedTicketId + '</span></div>';
                    }
                    if (transaction.details.terminal) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Terminal:</span><span class="ml-2">' + (transaction.details.terminal || '') + '</span></div>';
                    }
                    if (transaction.details.cashier) {
                        const highlightedCashier = this.highlightSearchTerm(transaction.details.cashier, searchTerm);
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Cashier:</span><span class="ml-2">' + highlightedCashier + '</span></div>';
                    }
                    if (transaction.details.customer) {
                        const highlightedCustomer = this.highlightSearchTerm(transaction.details.customer, searchTerm);
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Customer:</span><span class="ml-2">' + highlightedCustomer + '</span></div>';
                    }
                    if (transaction.details.payment_type) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Payment:</span><span class="ml-2">' + transaction.details.payment_type + '</span></div>';
                    }
                    
                    html += '</div>';
                    html += this.renderReceiptLines(transaction);
                    html += '</div>';
                    return html;
                },

                renderReceiptLines(transaction) {
                    if (!transaction || !transaction.details || !transaction.details.lines || transaction.details.lines.length === 0) {
                        return '';
                    }

                    let linesHtml = '';
                    const searchTerm = this.filters.search && this.filters.search.trim();
                    transaction.details.lines.forEach(line => {
                        if (!line) return;
                        
                        linesHtml += '<tr class="border-b dark:border-gray-700">';
                        // Highlight search term in product name
                        const highlightedProduct = this.highlightSearchTerm(line.product || '', searchTerm);
                        linesHtml += '<td class="py-1">' + highlightedProduct + '</td>';
                        linesHtml += '<td class="text-right py-1">' + (line.units || 0) + '</td>';
                        linesHtml += '<td class="text-right py-1">€' + this.formatAmount(line.price || 0) + '</td>';
                        linesHtml += '<td class="text-right py-1">' + ((line.tax || 0) * 100).toFixed(1) + '%</td>';
                        linesHtml += '<td class="text-right py-1">€' + this.formatAmount(line.total || 0) + '</td>';
                        linesHtml += '</tr>';
                    });

                    let html = '<div class="mt-4">';
                    html += '<h4 class="font-semibold text-sm mb-2">Items:</h4>';
                    html += '<table class="w-full text-sm">';
                    html += '<thead>';
                    html += '<tr class="border-b dark:border-gray-600">';
                    html += '<th class="text-left py-1">Product</th>';
                    html += '<th class="text-right py-1">Qty</th>';
                    html += '<th class="text-right py-1">Price</th>';
                    html += '<th class="text-right py-1">Tax</th>';
                    html += '<th class="text-right py-1">Total</th>';
                    html += '</tr>';
                    html += '</thead>';
                    html += '<tbody>' + linesHtml + '</tbody>';
                    html += '</table>';
                    html += '</div>';
                    return html;
                },

                getTypeClassString(type) {
                    const classes = {
                        'receipt': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                        'drawer': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        'removed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        'card': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                    };
                    return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                },

                hasActiveFilters() {
                    return this.filters.type !== '' ||
                           this.filters.terminal !== '' ||
                           this.filters.cashier !== '' ||
                           this.filters.time_from !== '' ||
                           this.filters.time_to !== '' ||
                           this.filters.search !== '' ||
                           this.filters.min_amount !== '' ||
                           this.filters.max_amount !== '';
                },

                calculateFilteredSummary() {
                    if (!this.hasActiveFilters() || this.transactions.length === 0) {
                        this.filteredSummary = {
                            total_sales: 0,
                            total_transactions: 0,
                            cash_total: 0,
                            card_total: 0,
                            other_total: 0,
                            free_total: 0,
                            debt_total: 0,
                            drawer_opens: 0,
                            voided_items_count: 0
                        };
                        return;
                    }

                    let summary = {
                        total_sales: 0,
                        total_transactions: 0,
                        cash_total: 0,
                        card_total: 0,
                        other_total: 0,
                        free_total: 0,
                        debt_total: 0,
                        drawer_opens: 0,
                        voided_items_count: 0
                    };

                    this.transactions.forEach(transaction => {
                        const details = transaction.details;
                        const amount = parseFloat(transaction.amount || 0);

                        switch (transaction.type) {
                            case 'receipt':
                                summary.total_transactions++;
                                summary.total_sales += amount;
                                
                                if (details.payment_type === 'cash') {
                                    summary.cash_total += amount;
                                } else if (details.payment_type === 'magcard') {
                                    summary.card_total += amount;
                                } else if (details.payment_type === 'free') {
                                    summary.free_total += amount;
                                } else if (details.payment_type === 'debt') {
                                    summary.debt_total += amount;
                                } else {
                                    summary.other_total += amount;
                                }
                                break;
                                
                            case 'drawer':
                                summary.drawer_opens++;
                                break;
                                
                            case 'removed':
                                summary.voided_items_count++;
                                break;
                        }
                    });

                    this.filteredSummary = summary;
                },

                filterByPaymentType(paymentType) {
                    // Set payment type filter and clear other type-specific filters
                    this.filters.payment_type = paymentType;
                    this.filters.type = 'receipt'; // Only receipts have payment types
                    
                    // Reload transactions with new filter
                    this.loadTransactions();
                },

                clearPaymentTypeFilter() {
                    this.filters.payment_type = '';
                    this.filters.type = ''; // Also clear receipt filter
                    this.loadTransactions();
                },

                clearFilters() {
                    this.filters = {
                        type: '',
                        terminal: '',
                        cashier: '',
                        time_from: '',
                        time_to: '',
                        search: '',
                        min_amount: '',
                        max_amount: '',
                        payment_type: ''
                    };
                    this.loadTransactions();
                },

                async refreshCache() {
                    if (!confirm('This will clear and rebuild the cache for this date. Continue?')) {
                        return;
                    }

                    this.loading = true;
                    this.showProgress = true;
                    this.progressDone = false;
                    this.progressTitle = 'Refreshing cache...';
                    this.progressPercent = 0;
                    this.progressSteps = [];

                    try {
                        this.addStep('clear', 'Clearing cache...');
                        this.progressPercent = 15;

                        const response = await fetch('/till-review/refresh-cache', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ date: this.selectedDate })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.completeStep('clear', `Cache rebuilt — ${data.transaction_count} transactions`);
                            this.progressPercent = 40;

                            this.addStep('summary', 'Loading summary...');
                            this.addStep('transactions', 'Loading transactions...');

                            await Promise.all([
                                this.loadSummary().then(() => {
                                    this.completeStep('summary', `Summary loaded — €${this.summary.total_sales.toFixed(2)} total`);
                                    this.progressPercent = Math.max(this.progressPercent, 60);
                                }),
                                this.loadTransactionData().then(() => {
                                    this.completeStep('transactions', `${this.transactionCount} transactions loaded`);
                                    this.progressPercent = Math.max(this.progressPercent, 75);
                                })
                            ]);

                            this.addStep('chart', 'Building chart...');
                            this.progressPercent = 85;
                            this.loading = false;
                            await this.$nextTick();
                            await this.loadHourlySalesChart();
                            this.completeStep('chart', 'Chart ready');
                            this.progressPercent = 100;

                            this.progressDone = true;
                            this.progressTitle = 'Cache refreshed';
                            setTimeout(() => { this.showProgress = false; }, 600);
                        } else {
                            this.addStep('clear', 'Failed to refresh cache', 'error');
                            this.progressTitle = 'Something went wrong';
                            setTimeout(() => { this.showProgress = false; }, 3000);
                        }
                    } catch (error) {
                        console.error('Error refreshing cache:', error);
                        this.addStep('error', 'Failed: ' + error.message, 'error');
                        this.progressTitle = 'Something went wrong';
                        setTimeout(() => { this.showProgress = false; }, 3000);
                    } finally {
                        this.loading = false;
                    }
                },

                async exportData(format) {
                    const params = new URLSearchParams({
                        date: this.selectedDate,
                        format: format
                    });

                    window.location.href = '/till-review/export?' + params;
                },

                // Expand all receipt details
                expandAllReceipts() {
                    this.transactions = this.transactions.map(t => ({
                        ...t, 
                        showDetails: t.type === 'receipt' ? true : t.showDetails
                    }));
                },

                // Collapse all receipt details  
                collapseAllReceipts() {
                    this.transactions = this.transactions.map(t => ({
                        ...t,
                        showDetails: false
                    }));
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>