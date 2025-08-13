<x-admin-layout>
    <div class="p-6">
        <!-- Header with Period Selector -->
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Profit & Loss Statement</h1>
            
            <!-- Period Selection Form -->
            <form method="GET" class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">From:</label>
                    <input type="date" 
                           name="start_date"
                           value="{{ $startDate }}" 
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">To:</label>
                    <input type="date" 
                           name="end_date"
                           value="{{ $endDate }}" 
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Update
                </button>
                <button type="button" 
                        onclick="window.location.href='{{ route('management.profit-loss.index') }}'"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    This Month
                </button>
            </form>
        </div>

        <!-- Period Display -->
        <div class="mb-6 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <p class="text-lg font-medium text-gray-900 dark:text-white">
                Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">
                    ({{ \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1 }} days)
                </span>
            </p>
        </div>

        <!-- Primary Profit/Loss Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue (Ex. VAT)</h3>
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                    €{{ number_format($totalRevenue, 2) }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ number_format($revenueData['transaction_count']) }} transactions
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    VAT: €{{ number_format($revenueData['vat_on_sales'] ?? 0, 2) }}
                </p>
            </div>

            <!-- Total Costs -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Costs (Ex. VAT)</h3>
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                </div>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">
                    €{{ number_format($totalCosts, 2) }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Invoices + Cash Payments
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    VAT: €{{ number_format($costData['total_costs_vat'] ?? 0, 2) }}
                </p>
            </div>

            <!-- Net Profit/Loss -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Profit/Loss</h3>
                    <svg class="w-5 h-5 {{ $profitLoss >= 0 ? 'text-green-500' : 'text-red-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($profitLoss >= 0)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                        @endif
                    </svg>
                </div>
                <p class="text-3xl font-bold {{ $profitLoss >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    €{{ number_format($profitLoss, 2) }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ number_format($marginPercent, 1) }}% margin
                </p>
            </div>
        </div>

        <!-- Detailed Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Revenue Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue Breakdown</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 dark:text-gray-300">Net Sales (Ex. VAT)</span>
                        <span class="font-medium text-gray-900 dark:text-white">€{{ number_format($revenueData['net_revenue'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 dark:text-gray-300">VAT on Sales</span>
                        <span class="font-medium text-gray-600 dark:text-gray-400">€{{ number_format($revenueData['vat_on_sales'], 2) }}</span>
                    </div>
                    <hr class="border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center font-semibold">
                        <span class="text-gray-900 dark:text-white">Total Revenue (Ex. VAT)</span>
                        <span class="text-green-600 dark:text-green-400">€{{ number_format($totalRevenue, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                        <span>Gross Revenue (Inc. VAT)</span>
                        <span>€{{ number_format($revenueData['gross_revenue'], 2) }}</span>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Payment Methods (Ex. VAT)</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Cash</span>
                                <span>€{{ number_format($revenueData['cash_sales_net'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Card</span>
                                <span>€{{ number_format($revenueData['card_sales_net'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Debt</span>
                                <span>€{{ number_format($revenueData['debt_sales_net'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Free</span>
                                <span>€{{ number_format($revenueData['free_sales'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @if(isset($revenueData['vat_breakdown']))
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">VAT Breakdown</h4>
                        <div class="space-y-2 text-sm">
                            @if($revenueData['vat_breakdown']['standard_net'] > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">23% (Standard)</span>
                                <span>€{{ number_format($revenueData['vat_breakdown']['standard_vat'], 2) }}</span>
                            </div>
                            @endif
                            @if($revenueData['vat_breakdown']['reduced_net'] > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">13.5% (Reduced)</span>
                                <span>€{{ number_format($revenueData['vat_breakdown']['reduced_vat'], 2) }}</span>
                            </div>
                            @endif
                            @if($revenueData['vat_breakdown']['second_reduced_net'] > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">9% (Second Reduced)</span>
                                <span>€{{ number_format($revenueData['vat_breakdown']['second_reduced_vat'], 2) }}</span>
                            </div>
                            @endif
                            @if($revenueData['vat_breakdown']['zero_net'] > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">0% (Zero-rated)</span>
                                <span>€{{ number_format($revenueData['vat_breakdown']['zero_net'], 2) }} (Net)</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Average Transaction: €{{ number_format($revenueData['avg_transaction'], 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cost Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cost Breakdown</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 dark:text-gray-300">Paid Invoices (Ex. VAT)</span>
                        <span class="font-medium text-gray-900 dark:text-white">€{{ number_format($costData['paid_invoices_net'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 dark:text-gray-300">VAT on Invoices</span>
                        <span class="font-medium text-gray-600 dark:text-gray-400">€{{ number_format($costData['total_costs_vat'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 dark:text-gray-300">Cash Supplier Payments</span>
                        <span class="font-medium text-gray-900 dark:text-white">€{{ number_format($supplierPayments, 2) }}</span>
                    </div>
                    <hr class="border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center font-semibold">
                        <span class="text-gray-900 dark:text-white">Total Costs (Ex. VAT)</span>
                        <span class="text-red-600 dark:text-red-400">€{{ number_format($totalCosts, 2) }}</span>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Invoice Status (Ex. VAT)</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Paid</span>
                                <span class="text-green-600">€{{ number_format($costData['paid_invoices_net'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Pending</span>
                                <span class="text-yellow-600">€{{ number_format($costData['pending_invoices_net'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Overdue</span>
                                <span class="text-red-600">€{{ number_format($costData['overdue_invoices_net'] ?? 0, 2) }}</span>
                            </div>
                            <hr class="border-gray-200 dark:border-gray-700 my-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400 font-medium">Total Outstanding</span>
                                <span class="font-medium">€{{ number_format(($costData['pending_invoices_net'] ?? 0) + ($costData['overdue_invoices_net'] ?? 0), 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @if($costData['vat_breakdown']['total_net'] > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">VAT Breakdown (Paid)</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Net Amount</span>
                                <span>€{{ number_format($costData['vat_breakdown']['total_net'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">VAT Amount</span>
                                <span>€{{ number_format($costData['vat_breakdown']['total_vat'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Total Invoices: {{ number_format($costData['invoice_count']) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VAT Summary Section -->
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">VAT Summary</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">VAT position for this period</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">VAT Collected (Sales)</div>
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            €{{ number_format($revenueData['vat_on_sales'] ?? 0, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            On net sales of €{{ number_format($revenueData['net_revenue'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">VAT Paid (Invoices)</div>
                        <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                            €{{ number_format($costData['total_costs_vat'] ?? 0, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            On net costs of €{{ number_format($costData['paid_invoices_net'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Net VAT Position</div>
                        @php
                            $netVat = ($revenueData['vat_on_sales'] ?? 0) - ($costData['total_costs_vat'] ?? 0);
                        @endphp
                        <div class="text-2xl font-bold {{ $netVat >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            €{{ number_format($netVat, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $netVat >= 0 ? 'Payable to Revenue' : 'Refund due' }}
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="ml-3 text-sm">
                                <p class="text-blue-800 dark:text-blue-200">
                                    <strong>Note:</strong> This P&L report now shows VAT-exclusive amounts for accurate business performance analysis. 
                                    VAT is shown separately as it's a pass-through tax collected on behalf of Revenue.
                                </p>
                                <p class="text-blue-700 dark:text-blue-300 mt-2">
                                    Cash supplier payments are shown at their full amount as VAT breakdown is not available for these transactions.
                                </p>
                                <a href="{{ route('management.vat-dashboard.index') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline mt-2">
                                    View detailed VAT returns 
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Period Comparison -->
        @if($previousPeriodData['revenue'] > 0 || $previousPeriodData['costs'] > 0)
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Previous Period Comparison</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ \Carbon\Carbon::parse($previousPeriodData['start_date'])->format('M d, Y') }} - 
                    {{ \Carbon\Carbon::parse($previousPeriodData['end_date'])->format('M d, Y') }}
                </p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Revenue Change</div>
                        @php
                            $revenueChange = $previousPeriodData['revenue'] > 0 ? (($totalRevenue - $previousPeriodData['revenue']) / $previousPeriodData['revenue']) * 100 : 0;
                        @endphp
                        <div class="text-lg font-semibold {{ $revenueChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $revenueChange >= 0 ? '+' : '' }}{{ number_format($revenueChange, 1) }}%
                        </div>
                        <div class="text-xs text-gray-500">
                            €{{ number_format($totalRevenue - $previousPeriodData['revenue'], 2) }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Cost Change</div>
                        @php
                            $costChange = $previousPeriodData['costs'] > 0 ? (($totalCosts - $previousPeriodData['costs']) / $previousPeriodData['costs']) * 100 : 0;
                        @endphp
                        <div class="text-lg font-semibold {{ $costChange <= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $costChange >= 0 ? '+' : '' }}{{ number_format($costChange, 1) }}%
                        </div>
                        <div class="text-xs text-gray-500">
                            €{{ number_format($totalCosts - $previousPeriodData['costs'], 2) }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Profit Change</div>
                        @php
                            $profitChange = $previousPeriodData['profit'] != 0 ? (($profitLoss - $previousPeriodData['profit']) / abs($previousPeriodData['profit'])) * 100 : 0;
                        @endphp
                        <div class="text-lg font-semibold {{ $profitChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $profitChange >= 0 ? '+' : '' }}{{ number_format($profitChange, 1) }}%
                        </div>
                        <div class="text-xs text-gray-500">
                            €{{ number_format($profitLoss - $previousPeriodData['profit'], 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-admin-layout>