<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Bank Statement Analysis & Validation
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Date Range Selector -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6" x-data="{ currentDisplay: '{{ $startDate->isSameMonth($endDate) ? $startDate->format("F Y") : $startDate->format("M Y") . " - " . $endDate->format("M Y") }}' }">
                    
                    <!-- Compact Date Navigation -->
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">📅 Quick Date Selection:</label>
                        <div class="flex items-center justify-between gap-2">
                            <!-- Quick selection buttons -->
                            <div class="flex flex-wrap gap-1.5">
                                <button onclick="setCurrentMonth()" 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                                    📅 Current Month
                                </button>
                                <button onclick="setPreviousMonth()" 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                                    ⏪ Previous Month
                                </button>
                                <button onclick="setLastThreeMonths()" 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                                    📊 Last 3 Months
                                </button>
                                <button onclick="setThisYear()" 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                                    📆 This Year
                                </button>
                            </div>
                            
                            <!-- Compact month navigator -->
                            <div class="flex items-center gap-2 ml-auto">
                                <button onclick="navigateToPreviousMonth()"
                                        class="p-1.5 rounded-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                    </svg>
                                </button>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap" x-text="currentDisplay"></div>
                                <button onclick="navigateToNextMonth()"
                                        class="p-1.5 rounded-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('management.bank-statements.analysis') }}" class="flex gap-4 items-end">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate->format('Y-m-d') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate->format('Y-m-d') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Analyze Period
                        </button>
                        <a href="{{ route('management.bank-statements.analysis.export', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}"
                           class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 2 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                            </svg>
                            <span>Export Comprehensive CSV</span>
                        </a>
                    </form>
                </div>
            </div>

            <script>
                function setCurrentMonth() {
                    const now = new Date();
                    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    
                    document.getElementById('start_date').value = formatDate(startOfMonth);
                    document.getElementById('end_date').value = formatDate(endOfMonth);
                    updateCurrentDisplay(startOfMonth, endOfMonth);
                }

                function setPreviousMonth() {
                    const now = new Date();
                    const prevMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const endOfPrevMonth = new Date(now.getFullYear(), now.getMonth(), 0);
                    
                    document.getElementById('start_date').value = formatDate(prevMonth);
                    document.getElementById('end_date').value = formatDate(endOfPrevMonth);
                    updateCurrentDisplay(prevMonth, endOfPrevMonth);
                }

                function setLastThreeMonths() {
                    const now = new Date();
                    const threeMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 3, 1);
                    const endOfCurrentMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    
                    document.getElementById('start_date').value = formatDate(threeMonthsAgo);
                    document.getElementById('end_date').value = formatDate(endOfCurrentMonth);
                    updateCurrentDisplay(threeMonthsAgo, endOfCurrentMonth);
                }

                function setThisYear() {
                    const now = new Date();
                    const startOfYear = new Date(now.getFullYear(), 0, 1);
                    const endOfYear = new Date(now.getFullYear(), 11, 31);
                    
                    document.getElementById('start_date').value = formatDate(startOfYear);
                    document.getElementById('end_date').value = formatDate(endOfYear);
                    updateCurrentDisplay(startOfYear, endOfYear);
                }

                function navigateToPreviousMonth() {
                    const currentStartStr = document.getElementById('start_date').value;
                    const [year, month, day] = currentStartStr.split('-').map(Number);
                    
                    // Calculate previous month
                    let newYear = year;
                    let newMonth = month - 1;
                    
                    if (newMonth === 0) {
                        newMonth = 12;
                        newYear = year - 1;
                    }
                    
                    // Get the last day of the previous month
                    const lastDay = new Date(newYear, newMonth, 0).getDate();
                    
                    // Format dates as YYYY-MM-DD strings
                    const newStartDate = `${newYear}-${String(newMonth).padStart(2, '0')}-01`;
                    const newEndDate = `${newYear}-${String(newMonth).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
                    
                    document.getElementById('start_date').value = newStartDate;
                    document.getElementById('end_date').value = newEndDate;
                    updateCurrentDisplay(new Date(newStartDate), new Date(newEndDate));
                }

                function navigateToNextMonth() {
                    const currentStartStr = document.getElementById('start_date').value;
                    const [year, month, day] = currentStartStr.split('-').map(Number);
                    
                    // Calculate next month
                    let newYear = year;
                    let newMonth = month + 1;
                    
                    if (newMonth === 13) {
                        newMonth = 1;
                        newYear = year + 1;
                    }
                    
                    // Get the last day of the next month
                    const lastDay = new Date(newYear, newMonth, 0).getDate();
                    
                    // Format dates as YYYY-MM-DD strings
                    const newStartDate = `${newYear}-${String(newMonth).padStart(2, '0')}-01`;
                    const newEndDate = `${newYear}-${String(newMonth).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
                    
                    document.getElementById('start_date').value = newStartDate;
                    document.getElementById('end_date').value = newEndDate;
                    updateCurrentDisplay(new Date(newStartDate), new Date(newEndDate));
                }

                function formatDate(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                }

                function updateCurrentDisplay(startDate, endDate) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    let display;
                    if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
                        // Same month
                        display = start.toLocaleString('default', { month: 'long', year: 'numeric' });
                    } else {
                        // Different months
                        const startStr = start.toLocaleString('default', { month: 'short', year: 'numeric' });
                        const endStr = end.toLocaleString('default', { month: 'short', year: 'numeric' });
                        display = startStr + ' - ' + endStr;
                    }
                    
                    // Update Alpine.js data
                    const element = document.querySelector('[x-data]');
                    if (element && element._x_dataStack && element._x_dataStack[0]) {
                        element._x_dataStack[0].currentDisplay = display;
                    }
                }
            </script>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">POS Total</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            €{{ number_format($summary['pos_total'], 2) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Cash: €{{ number_format($summary['pos_cash'], 2) }}<br>
                            Card: €{{ number_format($summary['pos_card'], 2) }}
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Total</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            €{{ number_format($summary['bank_total'], 2) }}
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Variance</div>
                        <div class="mt-1 text-2xl font-semibold {{ $summary['variance'] < 0 ? 'text-red-600' : 'text-green-600' }}">
                            €{{ number_format(abs($summary['variance']), 2) }}
                            @if($summary['variance'] < 0)
                                <span class="text-sm">↓</span>
                            @else
                                <span class="text-sm">↑</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            {{ round(($summary['variance'] / max($summary['pos_total'], 1)) * 100, 2) }}% difference
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Unmatched Days</div>
                        <div class="mt-1 text-2xl font-semibold {{ $summary['unmatched_days'] > 0 ? 'text-yellow-600' : 'text-green-600' }}">
                            {{ $summary['unmatched_days'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Requiring attention
                        </div>
                    </div>
                </div>
            </div>

            <!-- Credit Categories Breakdown -->
            @if(count($unmatchedBankByCategory) > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Credit Categories Overview</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($unmatchedBankByCategory as $categoryKey => $categoryData)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="text-xl">{{ $categoryData['icon'] }}</span>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $categoryData['display_name'] }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $categoryData['count'] }} unmatched
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                        €{{ number_format($categoryData['total_amount'], 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <!-- Category Legend -->
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex items-center space-x-1">
                                <span class="inline-block w-3 h-3 bg-blue-100 rounded-full"></span>
                                <span>💳 Card Lodgements (1-3 day delay)</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <span class="inline-block w-3 h-3 bg-green-100 rounded-full"></span>
                                <span>💰 Cash Lodgements (0-5 day delay)</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <span class="inline-block w-3 h-3 bg-purple-100 rounded-full"></span>
                                <span>🏠 Rent (property income)</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <span class="inline-block w-3 h-3 bg-gray-100 rounded-full"></span>
                                <span>📈 Other Credits (non-sales income)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Cash Reconciliation Summary -->
            @if(isset($cashReconciliation))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">💰 Cash Reconciliation Overview</h3>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Period: {{ $startDate->format('M j') }} - {{ $endDate->format('M j, Y') }}
                        </div>
                    </div>
                    
                    <!-- Enhanced Cash Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                            <div class="text-sm font-medium text-blue-700 dark:text-blue-300">POS Cash Sales</div>
                            <div class="text-2xl font-semibold text-blue-900 dark:text-blue-100">
                                €{{ number_format($cashReconciliation['pos_cash_total'], 2) }}
                            </div>
                        </div>

                        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                            <div class="text-sm font-medium text-purple-700 dark:text-purple-300">Available to Lodge</div>
                            <div class="text-2xl font-semibold text-purple-900 dark:text-purple-100">
                                €{{ number_format($cashReconciliation['available_to_lodge'], 2) }}
                            </div>
                            <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                After float & payments
                            </div>
                        </div>
                        
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <div class="text-sm font-medium text-green-700 dark:text-green-300">Actual Lodgements</div>
                            <div class="text-2xl font-semibold text-green-900 dark:text-green-100">
                                €{{ number_format($cashReconciliation['cash_lodgements_total'], 2) }}
                            </div>
                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                {{ $cashReconciliation['lodgement_count'] }} deposit{{ $cashReconciliation['lodgement_count'] > 1 ? 's' : '' }}
                            </div>
                        </div>
                        
                        <div class="bg-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-50 dark:bg-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-900/20 rounded-lg p-4">
                            <div class="text-sm font-medium text-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-700 dark:text-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-300">Variance</div>
                            <div class="text-2xl font-semibold text-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-900 dark:text-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-100">
                                {{ $cashReconciliation['variance'] < 0 ? '-' : '+' }}€{{ number_format(abs($cashReconciliation['variance']), 2) }}
                            </div>
                            <div class="text-xs text-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-600 dark:text-{{ $cashReconciliation['variance'] < 0 ? 'red' : 'gray' }}-400 mt-1">
                                Lodged vs Available
                            </div>
                        </div>
                        
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
                            <div class="text-sm font-medium text-amber-700 dark:text-amber-300">Match Rate</div>
                            <div class="text-2xl font-semibold text-amber-900 dark:text-amber-100">
                                {{ $cashReconciliation['match_rate'] }}%
                            </div>
                            <div class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                {{ $cashReconciliation['unmatched_lodgement_count'] }} unmatched
                            </div>
                        </div>
                    </div>

                    <!-- Float & Payment Impact -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">💰 Float Management</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                €{{ number_format($cashReconciliation['total_float_retained'], 2) }} retained
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Cash kept in tills for operations
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">💸 Supplier Payments</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                €{{ number_format($cashReconciliation['total_supplier_payments'], 2) }} paid
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Cash payments to suppliers
                            </div>
                        </div>
                    </div>

                    <!-- Accumulated Cash Balance -->
                    @if($cashReconciliation['accumulated_unmatched_cash'] != 0)
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    💵 Accumulated Unmatched Cash
                                </div>
                                <div class="text-lg font-semibold text-yellow-900 dark:text-yellow-100">
                                    €{{ number_format($cashReconciliation['accumulated_unmatched_cash'], 2) }}
                                </div>
                                <div class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                    This represents cash sales that haven't been matched to bank lodgements yet
                                </div>
                            </div>
                            <div class="text-4xl">💰</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Cash Match Suggestions -->
            @if(isset($cashMatchSuggestions) && count($cashMatchSuggestions) > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            🎯 Smart Cash Matching Suggestions
                        </h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ count($cashMatchSuggestions) }} potential match{{ count($cashMatchSuggestions) > 1 ? 'es' : '' }}
                        </span>
                    </div>
                    
                    <div class="space-y-3">
                        @foreach($cashMatchSuggestions as $suggestion)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border-l-4 border-{{ $suggestion['confidence'] >= 80 ? 'green' : ($suggestion['confidence'] >= 65 ? 'yellow' : 'blue') }}-500">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            Cash Lodgement: €{{ number_format($suggestion['lodgement_amount'], 2) }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            ({{ $suggestion['lodgement_date'] }})
                                        </span>
                                        <span class="px-2 py-1 text-xs rounded bg-{{ $suggestion['confidence'] >= 80 ? 'green' : ($suggestion['confidence'] >= 65 ? 'yellow' : 'blue') }}-100 text-{{ $suggestion['confidence'] >= 80 ? 'green' : ($suggestion['confidence'] >= 65 ? 'yellow' : 'blue') }}-800">
                                            {{ $suggestion['confidence'] }}% confidence
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Matches available cash of €{{ number_format($suggestion['accumulated_cash'], 2) }} up to {{ $suggestion['suggested_pos_date'] }}
                                        @if($suggestion['days_delay'] > 0)
                                            ({{ $suggestion['days_delay'] }} day{{ $suggestion['days_delay'] > 1 ? 's' : '' }} delay)
                                        @endif
                                    </div>
                                    
                                    @if(isset($suggestion['float_info']))
                                    <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>🏦 POS: €{{ number_format($suggestion['float_info']['pos_cash'], 2) }}</span>
                                        <span>💰 Float: €{{ number_format($suggestion['float_info']['float_retained'], 2) }}</span>
                                        <span>💸 Payments: €{{ number_format($suggestion['float_info']['supplier_payments'], 2) }}</span>
                                    </div>
                                    @endif
                                    
                                    @if($suggestion['variance'] > 0)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Variance: ±€{{ number_format($suggestion['variance'], 2) }} ({{ $suggestion['variance_percent'] }}%)
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <button class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                                            onclick="alert('Manual matching feature coming soon!')">
                                        Quick Match
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            💡 <strong>Tip:</strong> These suggestions are based on actual cash reconciliation data, accounting for float retained and supplier payments. 
                            Higher confidence scores indicate closer amount matches and more reasonable timing. Float and payment information helps explain variances.
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Detailed Unmatched Cash Lodgements -->
            @if(isset($cashReconciliation) && count($cashReconciliation['unmatched_lodgements']) > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        📋 Unmatched Cash Lodgements Detail
                    </h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Days Since Period End</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Potential Match</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($cashReconciliation['unmatched_lodgements'] as $lodgement)
                                @php
                                    $lodgementDate = \Carbon\Carbon::parse($lodgement->transaction_date);
                                    $daysSinceEnd = $endDate->diffInDays($lodgementDate, false);
                                    $suggestion = collect($cashMatchSuggestions ?? [])->firstWhere('lodgement_id', $lodgement->id);
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <div class="text-gray-900 dark:text-white font-medium">
                                            {{ $lodgementDate->format('M j, Y') }}
                                        </div>
                                        <div class="text-gray-500 dark:text-gray-400 text-xs">
                                            {{ $lodgementDate->format('l') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        <div class="max-w-xs truncate" title="{{ $lodgement->description }}">
                                            {{ $lodgement->description }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                                        €{{ number_format($lodgement->credit_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @if($daysSinceEnd >= 0)
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 rounded">
                                                +{{ $daysSinceEnd }} day{{ $daysSinceEnd > 1 ? 's' : '' }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 rounded">
                                                -{{ abs($daysSinceEnd) }} day{{ abs($daysSinceEnd) > 1 ? 's' : '' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @if($suggestion)
                                            <div class="flex flex-col items-center space-y-1">
                                                <span class="px-2 py-1 text-xs rounded bg-{{ $suggestion['confidence'] >= 80 ? 'green' : ($suggestion['confidence'] >= 65 ? 'yellow' : 'blue') }}-100 text-{{ $suggestion['confidence'] >= 80 ? 'green' : ($suggestion['confidence'] >= 65 ? 'yellow' : 'blue') }}-800">
                                                    {{ $suggestion['confidence'] }}% match
                                                </span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $suggestion['suggested_pos_date'] }}
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">No match</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @if($suggestion)
                                            <button class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
                                                    onclick="alert('Quick match feature coming soon!')">
                                                Match
                                            </button>
                                        @else
                                            <button class="px-2 py-1 text-xs bg-gray-400 text-white rounded cursor-not-allowed"
                                                    disabled>
                                                Manual
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary row -->
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-600 dark:text-gray-400">
                                Total Unmatched: {{ count($cashReconciliation['unmatched_lodgements']) }} lodgements
                            </span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                €{{ number_format($cashReconciliation['unmatched_cash_lodgements'], 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Cash Flow Timeline (Collapsible) -->
            @if(isset($cashReconciliation) && count($cashReconciliation['cash_flow_timeline']) > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            📈 Cash Flow Timeline
                        </h3>
                        <button onclick="toggleTimeline()" 
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            <span id="timeline-toggle-text">Show Details</span>
                        </button>
                    </div>
                    
                    <div id="cash-timeline" class="hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">POS Cash</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Lodged</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Daily Change</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Running Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($cashReconciliation['cash_flow_timeline'] as $day)
                                    <tr class="{{ $day['is_weekend'] ? 'bg-gray-50 dark:bg-gray-700/50' : '' }}">
                                        <td class="px-3 py-2 whitespace-nowrap text-sm">
                                            <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($day['date'])->format('M j') }}</div>
                                            <div class="text-gray-500 dark:text-gray-400 text-xs">{{ $day['day_name'] }}</div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                            €{{ number_format($day['pos_cash'], 2) }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right">
                                            @if(isset($day['legacy_lodged']) && $day['legacy_lodged'] > 0)
                                                <span class="text-green-600 dark:text-green-400">
                                                    -€{{ number_format($day['legacy_lodged'], 2) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right">
                                            <span class="{{ $day['daily_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $day['daily_change'] >= 0 ? '+' : '' }}€{{ number_format($day['daily_change'], 2) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right font-medium">
                                            <span class="{{ (isset($day['running_available_balance']) ? $day['running_available_balance'] : ($day['running_balance'] ?? 0)) >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600' }}">
                                                €{{ number_format(isset($day['running_available_balance']) ? $day['running_available_balance'] : ($day['running_balance'] ?? 0), 2) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                            💡 <strong>How to read:</strong> 
                            <em>POS Cash</em> = daily cash sales, 
                            <em>Lodged</em> = cash lodgements recorded for this day, 
                            <em>Running Balance</em> = accumulated available cash (after float & payments)
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function toggleTimeline() {
                    const timeline = document.getElementById('cash-timeline');
                    const toggleText = document.getElementById('timeline-toggle-text');
                    
                    if (timeline.classList.contains('hidden')) {
                        timeline.classList.remove('hidden');
                        toggleText.textContent = 'Hide Details';
                    } else {
                        timeline.classList.add('hidden');
                        toggleText.textContent = 'Show Details';
                    }
                }
            </script>
            @endif

            <!-- Enhanced Matching Info -->
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-medium text-green-900 dark:text-green-200 mb-2">🎯 Enhanced Matching Active</h3>
                <div class="text-sm text-green-700 dark:text-green-300 space-y-1">
                    <div>• <strong>Card Lodgements:</strong> MTBTS transactions auto-matched to card sales (1-3 day delay, ±15% variance)</div>
                    <div>• <strong>Cash Lodgements:</strong> LATM transactions matched to cash sales (0-5 day delay, ±10% variance)</div>
                    <div>• <strong>Weekend Combining:</strong> Friday-Sunday sales automatically combined for Monday lodgements</div>
                    <div>• <strong>Confidence Scoring:</strong> 
                        <span class="px-1 py-0.5 rounded text-xs bg-green-100 text-green-700">85%+</span> Exact matches, 
                        <span class="px-1 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">70-84%</span> Flexible matches, 
                        <span class="px-1 py-0.5 rounded text-xs bg-red-100 text-red-700">&lt;70%</span> Review needed
                    </div>
                </div>
            </div>

            <!-- Pattern Insights -->
            @if(count($patterns) > 0)
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">Pattern Insights</h3>
                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                    @foreach($patterns as $pattern)
                        <li>• {{ $pattern }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Detailed Analysis Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Daily Comparison</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Cash</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Card</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bank Lodged</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Variance</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($analysisData as $day)
                                <tr class="{{ $day['is_weekend'] ? 'bg-gray-50 dark:bg-gray-700/50' : '' }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <div class="text-gray-900 dark:text-white font-medium">{{ \Carbon\Carbon::parse($day['date'])->format('D, M j') }}</div>
                                        <div class="text-gray-500 dark:text-gray-400 text-xs">{{ $day['day_of_week'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                        €{{ number_format($day['pos']['net_cash'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                        €{{ number_format($day['pos']['net_card'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                                        €{{ number_format($day['pos']['total'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                        @if($day['bank']['total_lodged'] > 0)
                                            <div class="flex items-center justify-end space-x-2">
                                                <div>
                                                    €{{ number_format($day['bank']['total_lodged'], 2) }}
                                                    @if(isset($day['bank']['category_info']))
                                                        <div class="text-xs mt-1">
                                                            @if($day['bank']['category_info']['category'] == 'card_lodgement')
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                                    💳 Card
                                                                </span>
                                                            @elseif($day['bank']['category_info']['category'] == 'cash_lodgement')
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                                    💰 Cash
                                                                </span>
                                                            @elseif($day['bank']['category_info']['category'] == 'rent')
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                                                    🏠 Rent
                                                                </span>
                                                            @elseif($day['bank']['category_info']['category'] == 'other_credit')
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300">
                                                                    📈 Other
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if($day['bank']['match_type'] == 'combined')
                                                        <div class="text-xs text-blue-600 dark:text-blue-400">(combined)</div>
                                                    @elseif(str_contains($day['bank']['match_type'], 'card_specific'))
                                                        <div class="text-xs text-blue-600 dark:text-blue-400">
                                                            ({{ str_contains($day['bank']['match_type'], 'flexible') ? 'card ~match' : 'card match' }})
                                                            @if(isset($day['bank']['confidence']))
                                                                <span class="ml-1 px-1 py-0.5 rounded text-xs 
                                                                    {{ $day['bank']['confidence'] >= 85 ? 'bg-green-100 text-green-700' : 
                                                                       ($day['bank']['confidence'] >= 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                                                    {{ $day['bank']['confidence'] }}%
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @elseif(str_contains($day['bank']['match_type'], 'cash_specific'))
                                                        <div class="text-xs text-green-600 dark:text-green-400">
                                                            ({{ str_contains($day['bank']['match_type'], 'flexible') ? 'cash ~match' : 'cash match' }})
                                                            @if(isset($day['bank']['confidence']))
                                                                <span class="ml-1 px-1 py-0.5 rounded text-xs 
                                                                    {{ $day['bank']['confidence'] >= 85 ? 'bg-green-100 text-green-700' : 
                                                                       ($day['bank']['confidence'] >= 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                                                    {{ $day['bank']['confidence'] }}%
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        @if($day['variance']['amount'] != 0)
                                            <span class="{{ $day['variance']['amount'] < 0 ? 'text-red-600' : 'text-green-600' }}">
                                                {{ $day['variance']['amount'] < 0 ? '-' : '+' }}€{{ number_format(abs($day['variance']['amount']), 2) }}
                                            </span>
                                            <div class="text-xs text-gray-500">
                                                {{ round($day['variance']['percentage'], 1) }}%
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @switch($day['status'])
                                            @case('matched')
                                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 rounded-full">
                                                    ✅ Matched
                                                </span>
                                                @break
                                            @case('matched_with_variance')
                                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 rounded-full">
                                                    ⚠️ Check
                                                </span>
                                                @break
                                            @case('missing')
                                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 rounded-full">
                                                    ❌ Missing
                                                </span>
                                                @break
                                            @case('pending')
                                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300 rounded-full">
                                                    ⏳ Pending
                                                </span>
                                                @break
                                            @case('no_sales')
                                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300 rounded-full">
                                                    No Sales
                                                </span>
                                                @break
                                            @case('large_variance')
                                                <span class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300 rounded-full">
                                                    🔍 Review
                                                </span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @if($day['status'] != 'no_sales' && $day['status'] != 'matched')
                                            <button onclick="openMatchModal('{{ $day['date'] }}', {{ $day['pos']['total'] }})"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                Match
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                
                                @if(count($day['suggestions']) > 0)
                                <tr>
                                    <td colspan="8" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20">
                                        <div class="text-xs text-blue-700 dark:text-blue-300">
                                            💡 {{ implode(' | ', $day['suggestions']) }}
                                            @if(isset($day['bank']['category_info']) && isset($day['bank']['category_info']['variance']) && $day['bank']['category_info']['variance'] > 0)
                                                | Variance: €{{ number_format($day['bank']['category_info']['variance'], 2) }}
                                                @if($day['bank']['category_info']['pos_type'] == 'card')
                                                    (Card sales may include tips or fees not in lodgement)
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Unmatched Items -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <!-- Unmatched POS Days -->
                @if(count($unmatchedPOS) > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Unmatched POS Days ({{ count($unmatchedPOS) }})
                        </h3>
                        <div class="space-y-2">
                            @foreach(array_slice($unmatchedPOS, 0, 5) as $pos)
                                <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    <span class="text-sm text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse(is_array($pos) ? $pos['sale_date'] : $pos->sale_date)->format('M j, Y') }}
                                    </span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        @php
                                            $total = is_array($pos) 
                                                ? ($pos['cash_sales'] - $pos['cash_refunds'] + $pos['card_sales'] - $pos['card_refunds'])
                                                : ($pos->cash_sales - $pos->cash_refunds + $pos->card_sales - $pos->card_refunds);
                                        @endphp
                                        €{{ number_format($total, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- Unmatched Bank Transactions by Category -->
                @if(count($unmatchedBankByCategory) > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Unmatched Bank Lodgements by Category
                        </h3>
                        
                        @foreach($unmatchedBankByCategory as $categoryKey => $categoryData)
                        <div class="mb-4 {{ !$loop->last ? 'border-b border-gray-200 dark:border-gray-600 pb-4' : '' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg">{{ $categoryData['icon'] }}</span>
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $categoryData['display_name'] }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                            ({{ $categoryData['count'] }} transactions)
                                        </span>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    €{{ number_format($categoryData['total_amount'], 2) }}
                                </span>
                            </div>
                            
                            <div class="space-y-1">
                                @foreach(array_slice($categoryData['transactions']->toArray(), 0, 3) as $transaction)
                                <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded text-xs">
                                    <div class="flex-1">
                                        <span class="text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($transaction['transaction_date'])->format('M j, Y') }}
                                        </span>
                                        <div class="text-gray-500 dark:text-gray-400 mt-1">
                                            {{ Str::limit($transaction['description'], 35) }}
                                        </div>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white ml-2">
                                        €{{ number_format($transaction['credit_amount'], 2) }}
                                    </span>
                                </div>
                                @endforeach
                                
                                @if($categoryData['count'] > 3)
                                <div class="text-xs text-gray-500 dark:text-gray-400 text-center py-1">
                                    ... and {{ $categoryData['count'] - 3 }} more
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function openMatchModal(date, amount) {
            // This would open a modal for manual matching
            // For now, just alert
            alert('Manual matching for ' + date + ' (€' + amount + ') - To be implemented');
        }

        // Auto-suggest matches
        function suggestMatches() {
            fetch('{{ route("management.bank-statements.analysis.suggest") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    start_date: '{{ $startDate->format("Y-m-d") }}',
                    end_date: '{{ $endDate->format("Y-m-d") }}'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.suggestions.length > 0) {
                    console.log('Suggested matches:', data.suggestions);
                    // Display suggestions to user
                }
            });
        }

        // Call on page load if there are unmatched items
        @if($summary['unmatched_days'] > 0)
            document.addEventListener('DOMContentLoaded', suggestMatches);
        @endif
    </script>
    @endpush
</x-admin-layout>