<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Receipts
        </h2>
    </x-slot>

    <div class="py-6" x-data="tillReview()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Date Selector and Summary Cards -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-wrap items-center justify-between mb-6">
                        <div class="flex items-center space-x-4">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date:</label>
                            <input type="date" 
                                   x-model="selectedDate"
                                   @@change="loadTransactions()"
                                   value="{{ $selectedDate->format('Y-m-d') }}"
                                   max="{{ now()->format('Y-m-d') }}"
                                   class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            
                            <button @@click="refreshCache()"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh Cache
                            </button>
                        </div>
                        
                        <div class="flex space-x-2 mt-4 sm:mt-0">
                            <button @@click="exportData('csv')"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                <i class="fas fa-file-csv mr-2"></i>Export CSV
                            </button>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-4">
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
                                   placeholder="Receipt ID, product, etc..."
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
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                        Transactions 
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            (<span x-text="transactionCount"></span> results)
                        </span>
                    </h3>

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
    <script>
        function tillReview() {
            return {
                selectedDate: '{{ $selectedDate->format('Y-m-d') }}',
                loading: false,
                transactions: [],
                transactionCount: 0,
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
                    console.log('Till Review initialized');
                    console.log('Page data:', {
                        selectedDate: this.selectedDate,
                        summaryFromServer: {
                            total_sales: {{ $summary->total_sales ?? 0 }},
                            total_transactions: {{ $summary->total_transactions ?? 0 }},
                            cash_total: {{ $summary->cash_total ?? 0 }},
                            card_total: {{ $summary->card_total ?? 0 }}
                        }
                    });
                    
                    // Set up global toggle function
                    window.tillReviewToggleDetails = (index) => {
                        this.toggleDetails(this.transactions[index]);
                    };
                    
                    this.loadTransactions();
                },

                async loadTransactions() {
                    this.loading = true;
                    
                    try {
                        // Load both summary and transactions in parallel
                        const [summaryResponse, transactionsResponse] = await Promise.all([
                            this.loadSummary(),
                            this.loadTransactionData()
                        ]);
                        
                    } catch (error) {
                        console.error('Error loading data:', error);
                        alert('Failed to load data');
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
                    this.transactions = data.transactions.map(t => ({...t, showDetails: false}));
                    this.transactionCount = data.count;
                    
                    // Calculate filtered summary
                    this.calculateFilteredSummary();
                    
                    console.log('Transactions loaded:', data.count);
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

                renderTransactions() {
                    if (this.transactions.length === 0) {
                        return '<div class="text-center py-8 text-gray-500 dark:text-gray-400">No transactions found for the selected filters.</div>';
                    }

                    return this.transactions.map((transaction, index) => {
                        // Get payment type color scheme for receipts
                        const paymentType = transaction.details?.payment_type;
                        const isReceipt = transaction.type === 'receipt';
                        const colorScheme = isReceipt && paymentType ? this.getPaymentTypeColor(paymentType) : this.getPaymentTypeColor('default');
                        
                        const cardClasses = isReceipt && paymentType ? 
                            'border ' + colorScheme.border + ' ' + colorScheme.bg + ' hover:shadow-md' : 
                            'border dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700';
                        
                        let html = '<div class="' + cardClasses + ' rounded-lg p-4 transition cursor-pointer" onclick="window.tillReviewToggleDetails(' + index + ')">';
                        html += '<div class="flex items-center justify-between">';
                        html += '<div class="flex items-center space-x-4">';
                        html += '<span class="text-sm font-mono text-gray-600 dark:text-gray-400">' + transaction.time + '</span>';
                        html += '<span class="px-2 py-1 text-xs font-semibold rounded-full ' + this.getTypeClassString(transaction.type) + '">';
                        html += transaction.type_display + '</span>';
                        
                        if (isReceipt && paymentType) {
                            html += '<span class="px-2 py-1 text-xs font-semibold rounded-full ' + colorScheme.badge + '">';
                            html += paymentType.toUpperCase() + '</span>';
                        }
                        
                        const textColor = isReceipt && paymentType ? colorScheme.text : 'text-gray-900 dark:text-white';
                        html += '<span class="text-sm ' + textColor + '">' + transaction.description + '</span>';
                        html += '</div>';
                        html += '<div class="flex items-center space-x-4">';
                        html += '<span class="font-semibold ' + textColor + '">€' + this.formatAmount(transaction.amount) + '</span>';
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
                    let html = '<div class="mt-4 pt-4 border-t dark:border-gray-600">';
                    html += '<div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">';
                    
                    if (transaction.details.receipt_id) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Receipt ID:</span><span class="ml-2 font-mono">' + transaction.details.receipt_id + '</span></div>';
                    }
                    if (transaction.details.ticket_id) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Ticket ID:</span><span class="ml-2 font-mono">' + transaction.details.ticket_id + '</span></div>';
                    }
                    if (transaction.details.terminal) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Terminal:</span><span class="ml-2">' + transaction.details.terminal + '</span></div>';
                    }
                    if (transaction.details.cashier) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Cashier:</span><span class="ml-2">' + transaction.details.cashier + '</span></div>';
                    }
                    if (transaction.details.customer) {
                        html += '<div><span class="text-gray-600 dark:text-gray-400">Customer:</span><span class="ml-2">' + transaction.details.customer + '</span></div>';
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
                    if (!transaction.details.lines || transaction.details.lines.length === 0) {
                        return '';
                    }

                    let linesHtml = '';
                    transaction.details.lines.forEach(line => {
                        linesHtml += '<tr class="border-b dark:border-gray-700">';
                        linesHtml += '<td class="py-1">' + line.product + '</td>';
                        linesHtml += '<td class="text-right py-1">' + line.units + '</td>';
                        linesHtml += '<td class="text-right py-1">€' + this.formatAmount(line.price) + '</td>';
                        linesHtml += '<td class="text-right py-1">' + (line.tax * 100).toFixed(1) + '%</td>';
                        linesHtml += '<td class="text-right py-1">€' + this.formatAmount(line.total) + '</td>';
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
                    
                    try {
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
                            alert(`Cache refreshed successfully. ${data.transaction_count} transactions cached.`);
                            this.loadTransactions();
                        } else {
                            alert('Failed to refresh cache');
                        }
                    } catch (error) {
                        console.error('Error refreshing cache:', error);
                        alert('Failed to refresh cache');
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
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>