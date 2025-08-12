<x-admin-layout>
    <div class="p-6">
        <!-- Header with Date Selector -->
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Financial Dashboard</h1>
            <div class="flex items-center gap-4">
                <input type="date" 
                       value="{{ $date }}" 
                       max="{{ date('Y-m-d') }}"
                       onchange="window.location.href='{{ route('management.financial.dashboard') }}?date=' + this.value"
                       class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                <button onclick="window.location.href='{{ route('management.financial.dashboard') }}'"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Today
                </button>
            </div>
        </div>

        <!-- Financial Alerts -->
        @if(count($alerts) > 0)
        <div class="mb-6 space-y-2">
            @foreach($alerts as $alert)
            <div class="p-4 rounded-lg flex justify-between items-center
                @if($alert['type'] === 'danger') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                @elseif($alert['type'] === 'warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                @else bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                @endif">
                <span class="font-medium">{{ $alert['message'] }}</span>
                <a href="{{ $alert['action'] }}" class="underline hover:no-underline">View Details →</a>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Primary KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Today's Sales -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Sales</h3>
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex items-baseline">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        €{{ number_format($todayMetrics['net_sales'], 2) }}
                    </p>
                    @if($yesterdayMetrics['net_sales'] > 0)
                    <span class="ml-2 text-sm {{ $todayMetrics['net_sales'] >= $yesterdayMetrics['net_sales'] ? 'text-green-600' : 'text-red-600' }}">
                        {{ $todayMetrics['net_sales'] >= $yesterdayMetrics['net_sales'] ? '↑' : '↓' }}
                        {{ number_format(abs((($todayMetrics['net_sales'] - $yesterdayMetrics['net_sales']) / $yesterdayMetrics['net_sales']) * 100), 1) }}%
                    </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $todayMetrics['transactions'] }} transactions
                </p>
            </div>

            <!-- Cash Position -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Cash Position</h3>
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="flex items-baseline">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        €{{ number_format($cashPosition['expected_today'], 2) }}
                    </p>
                    @if($cashPosition['days_since_count'] > 0)
                    <span class="ml-2 text-sm text-yellow-600">
                        {{ $cashPosition['days_since_count'] }}d unreconciled
                    </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Float: €{{ number_format($cashPosition['current_float'], 2) }}
                </p>
            </div>

            <!-- Week Performance -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Week to Date</h3>
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="flex items-baseline">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        €{{ number_format($weekMetrics['net_sales'], 2) }}
                    </p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Avg: €{{ number_format($weekMetrics['daily_average'], 2) }}/day
                </p>
            </div>

            <!-- Month Performance -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Month to Date</h3>
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="flex items-baseline">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        €{{ number_format($monthMetrics['net_sales'], 2) }}
                    </p>
                    @if($monthMetrics['growth'] != 0)
                    <span class="ml-2 text-sm {{ $monthMetrics['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $monthMetrics['growth'] >= 0 ? '↑' : '↓' }}
                        {{ number_format(abs($monthMetrics['growth']), 1) }}%
                    </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    vs €{{ number_format($monthMetrics['last_month_sales'], 2) }} last month
                </p>
            </div>
        </div>

        <!-- Payment Breakdown & Cash Flow -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Payment Methods Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Today's Payment Methods</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Cash</span>
                        <div class="flex items-center gap-2">
                            <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" 
                                     style="width: {{ $todayMetrics['net_sales'] > 0 ? ($todayMetrics['cash_sales'] / $todayMetrics['net_sales'] * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                €{{ number_format($todayMetrics['cash_sales'], 2) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Card</span>
                        <div class="flex items-center gap-2">
                            <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-purple-500 h-2 rounded-full" 
                                     style="width: {{ $todayMetrics['net_sales'] > 0 ? ($todayMetrics['card_sales'] / $todayMetrics['net_sales'] * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                €{{ number_format($todayMetrics['card_sales'], 2) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Account</span>
                        <div class="flex items-center gap-2">
                            <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" 
                                     style="width: {{ $todayMetrics['net_sales'] > 0 ? ($todayMetrics['debt_sales'] / $todayMetrics['net_sales'] * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                €{{ number_format($todayMetrics['debt_sales'], 2) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t dark:border-gray-700">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Average Transaction</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            €{{ number_format($todayMetrics['avg_transaction'], 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Cash Flow Summary -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Today's Cash Flow</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <span class="text-sm font-medium text-green-800 dark:text-green-300">Cash In (Sales)</span>
                        <span class="text-lg font-bold text-green-800 dark:text-green-300">
                            +€{{ number_format($todayMetrics['cash_sales'], 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <span class="text-sm font-medium text-red-800 dark:text-red-300">Cash Out (Suppliers)</span>
                        <span class="text-lg font-bold text-red-800 dark:text-red-300">
                            -€{{ number_format($todayMetrics['supplier_payments'], 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <span class="text-sm font-medium text-blue-800 dark:text-blue-300">Net Cash</span>
                        <span class="text-lg font-bold {{ $todayMetrics['net_cash'] >= 0 ? 'text-blue-800 dark:text-blue-300' : 'text-red-800 dark:text-red-300' }}">
                            {{ $todayMetrics['net_cash'] >= 0 ? '+' : '' }}€{{ number_format($todayMetrics['net_cash'], 2) }}
                        </span>
                    </div>
                    @if($todayMetrics['reconciled'])
                    <div class="flex justify-between items-center p-3 {{ abs($todayMetrics['variance']) <= 10 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20' }} rounded-lg">
                        <span class="text-sm font-medium {{ abs($todayMetrics['variance']) <= 10 ? 'text-green-800 dark:text-green-300' : 'text-yellow-800 dark:text-yellow-300' }}">
                            Cash Variance
                        </span>
                        <span class="text-lg font-bold {{ abs($todayMetrics['variance']) <= 10 ? 'text-green-800 dark:text-green-300' : 'text-yellow-800 dark:text-yellow-300' }}">
                            {{ $todayMetrics['variance'] >= 0 ? '+' : '' }}€{{ number_format($todayMetrics['variance'], 2) }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Trends Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Sales Trend -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">7-Day Sales Trend</h3>
                <div class="h-48 flex items-end justify-between gap-2">
                    @foreach($salesTrend as $day)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-blue-500 rounded-t" 
                             style="height: {{ $day['sales'] > 0 ? ($day['sales'] / max(array_column($salesTrend, 'sales')) * 100) : 2 }}%"
                             title="€{{ number_format($day['sales'], 2) }}"></div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $day['date'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Cash Flow Trend -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">7-Day Cash Flow</h3>
                <div class="h-48 flex items-center justify-between gap-2">
                    @foreach($cashFlowTrend as $day)
                    <div class="flex-1 flex flex-col items-center justify-center">
                        <div class="w-full flex flex-col items-center">
                            @if($day['net'] >= 0)
                            <div class="w-full bg-green-500 rounded-t" 
                                 style="height: {{ abs($day['net']) > 0 ? (abs($day['net']) / max(array_map(function($d) { return abs($d['net']); }, $cashFlowTrend)) * 50) : 2 }}px"
                                 title="+€{{ number_format($day['net'], 2) }}"></div>
                            <div class="w-full h-0.5 bg-gray-300 dark:bg-gray-600"></div>
                            <div class="w-full h-2"></div>
                            @else
                            <div class="w-full h-2"></div>
                            <div class="w-full h-0.5 bg-gray-300 dark:bg-gray-600"></div>
                            <div class="w-full bg-red-500 rounded-b" 
                                 style="height: {{ abs($day['net']) > 0 ? (abs($day['net']) / max(array_map(function($d) { return abs($d['net']); }, $cashFlowTrend)) * 50) : 2 }}px"
                                 title="-€{{ number_format(abs($day['net']), 2) }}"></div>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $day['date'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="flex justify-center gap-4 mt-4 text-xs">
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 bg-green-500 rounded"></span>
                        Positive
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 bg-red-500 rounded"></span>
                        Negative
                    </span>
                </div>
            </div>
        </div>

        <!-- Action Items -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Pending Reconciliations -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Tasks</h3>
                    <span class="px-2 py-1 text-xs font-medium {{ $pendingReconciliations > 0 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }} rounded-full">
                        {{ $pendingReconciliations }}
                    </span>
                </div>
                <div class="space-y-2">
                    @if($pendingReconciliations > 0)
                    <a href="{{ route('cash-reconciliation.index') }}" 
                       class="block p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg hover:bg-yellow-100 dark:hover:bg-yellow-900/30">
                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                            {{ $pendingReconciliations }} days need reconciliation
                        </p>
                        <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">Click to reconcile</p>
                    </a>
                    @else
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <p class="text-sm font-medium text-green-800 dark:text-green-300">
                            All reconciliations complete
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Outstanding Invoices -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Outstanding</h3>
                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 rounded-full">
                        {{ $outstandingInvoices['count'] }}
                    </span>
                </div>
                <div class="space-y-2">
                    @if($outstandingInvoices['count'] > 0)
                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            €{{ number_format($outstandingInvoices['total_amount'], 2) }} outstanding
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            Oldest: {{ $outstandingInvoices['oldest_days'] }} days
                        </p>
                    </div>
                    @else
                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            No outstanding invoices
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('cash-reconciliation.index') }}" 
                       class="block w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center text-sm font-medium">
                        Count Cash
                    </a>
                    <a href="{{ route('till-review.index') }}" 
                       class="block w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-center text-sm font-medium">
                        View Receipts
                    </a>
                    <button onclick="alert('Export functionality coming soon')" 
                            class="block w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-center text-sm font-medium">
                        Export Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>