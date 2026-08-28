<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Sales Review
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Last updated: {{ $lastUpdated->format('H:i:s') }}
                </p>
            </div>
            <div class="flex items-center space-x-2">
                {{-- Date Navigation --}}
                <a href="{{ route('management.sales-review.index', [
                    'start_date' => $startDate->copy()->subDays($periodDays)->format('Y-m-d'),
                    'end_date' => $endDate->copy()->subDays($periodDays)->format('Y-m-d')
                ]) }}"
                   class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                   title="Previous Period">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>

                {{-- Quick Presets --}}
                <div class="flex items-center space-x-1 bg-gray-100 rounded-lg p-1">
                    <a href="{{ route('management.sales-review.index', [
                        'start_date' => now()->subDays(6)->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d')
                    ]) }}"
                       class="px-3 py-1 text-xs font-medium rounded {{ $periodDays == 7 && $endDate->isToday() ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        7 Days
                    </a>
                    <a href="{{ route('management.sales-review.index', [
                        'start_date' => now()->startOfWeek()->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d')
                    ]) }}"
                       class="px-3 py-1 text-xs font-medium rounded {{ $startDate->isSameDay(now()->startOfWeek()) && $endDate->isToday() ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        This Week
                    </a>
                    <a href="{{ route('management.sales-review.index', [
                        'start_date' => now()->startOfMonth()->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d')
                    ]) }}"
                       class="px-3 py-1 text-xs font-medium rounded {{ $startDate->isSameDay(now()->startOfMonth()) && $endDate->isToday() ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        This Month
                    </a>
                    <a href="{{ route('management.sales-review.index', [
                        'start_date' => now()->subDays(29)->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d')
                    ]) }}"
                       class="px-3 py-1 text-xs font-medium rounded {{ $periodDays == 30 && $endDate->isToday() ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        30 Days
                    </a>
                </div>

                <a href="{{ route('management.sales-review.index', [
                    'start_date' => $startDate->copy()->addDays($periodDays)->format('Y-m-d'),
                    'end_date' => $endDate->copy()->addDays($periodDays)->format('Y-m-d')
                ]) }}"
                   class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors {{ $endDate->isFuture() ? 'opacity-50 pointer-events-none' : '' }}"
                   title="Next Period">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Date Range Display --}}
            <div class="mb-6 text-center">
                <span class="text-lg font-medium text-gray-700">
                    {{ $startDate->format('M j, Y') }} - {{ $endDate->format('M j, Y') }}
                </span>
                <span class="text-sm text-gray-500 ml-2">({{ $periodDays }} days)</span>
            </div>

            {{-- KPI Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
                {{-- Total Revenue --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500">Total Revenue (ex-VAT)</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $yoyChanges['revenue'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $yoyChanges['revenue'] >= 0 ? '+' : '' }}{{ number_format($yoyChanges['revenue'], 1) }}%
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        &euro;{{ number_format($stats['total_revenue'], 2) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        vs &euro;{{ number_format($lastYearStats['total_revenue'], 2) }} last year
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Net of VAT, excludes Kitchen/Coffee transfers
                    </p>
                </div>

                {{-- Internal Transfers --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500">Internal Transfers</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $yoyChanges['transfers'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $yoyChanges['transfers'] >= 0 ? '+' : '' }}{{ number_format($yoyChanges['transfers'], 1) }}%
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        &euro;{{ number_format($transfers['net'], 2) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        @forelse($transfers['by_department'] as $department => $net)
                            {{ $department }} &euro;{{ number_format($net, 2) }}@if(!$loop->last) &middot; @endif
                        @empty
                            No transfers in period
                        @endforelse
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Stock to Kitchen/Coffee &mdash; not customer sales
                    </p>
                </div>

                {{-- Total Units --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500">Total Units</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $yoyChanges['units'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $yoyChanges['units'] >= 0 ? '+' : '' }}{{ number_format($yoyChanges['units'], 1) }}%
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        {{ number_format($stats['total_units']) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        vs {{ number_format($lastYearStats['total_units']) }} last year
                    </p>
                </div>

                {{-- Transactions --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500">Transactions</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $yoyChanges['transactions'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $yoyChanges['transactions'] >= 0 ? '+' : '' }}{{ number_format($yoyChanges['transactions'], 1) }}%
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        {{ number_format($stats['total_transactions']) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        vs {{ number_format($lastYearStats['total_transactions']) }} last year
                    </p>
                </div>

                {{-- Avg Transaction --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500">Avg Transaction</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $yoyChanges['avg_transaction'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $yoyChanges['avg_transaction'] >= 0 ? '+' : '' }}{{ number_format($yoyChanges['avg_transaction'], 1) }}%
                        </span>
                    </div>
                    @php
                        $avgTransaction = $stats['total_transactions'] > 0 ? $stats['total_revenue'] / $stats['total_transactions'] : 0;
                        $lastYearAvg = $lastYearStats['total_transactions'] > 0 ? $lastYearStats['total_revenue'] / $lastYearStats['total_transactions'] : 0;
                    @endphp
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        &euro;{{ number_format($avgTransaction, 2) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        vs &euro;{{ number_format($lastYearAvg, 2) }} last year
                    </p>
                </div>
            </div>

            {{-- Sales Trend Chart --}}
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Sales Trend</h3>
                <div class="flex items-end justify-between h-48 gap-1">
                    @php
                        // Merge current and last year data for comparison
                        $currentData = $salesTrend->keyBy(function($item) {
                            return \Carbon\Carbon::parse($item->sale_date)->format('Y-m-d');
                        });
                        $lastYearData = $lastYearTrend->keyBy(function($item) {
                            return \Carbon\Carbon::parse($item->sale_date)->format('Y-m-d');
                        });

                        $maxRevenue = max(
                            $salesTrend->max('daily_revenue') ?? 1,
                            $lastYearTrend->max('daily_revenue') ?? 1
                        );
                        $maxRevenue = $maxRevenue > 0 ? $maxRevenue : 1;
                    @endphp

                    @for($i = 0; $i < $periodDays; $i++)
                        @php
                            $currentDate = $startDate->copy()->addDays($i);
                            $dateKey = $currentDate->format('Y-m-d');
                            $lastYearDate = $currentDate->copy()->subYear()->format('Y-m-d');

                            $currentRevenue = $currentData->get($dateKey)?->daily_revenue ?? 0;
                            $lastYearRevenue = $lastYearData->get($lastYearDate)?->daily_revenue ?? 0;
                        @endphp
                        <div class="flex-1 flex flex-col items-center group relative">
                            <div class="w-full flex items-end justify-center gap-0.5 h-40">
                                {{-- Last Year Bar (gray) --}}
                                <div class="w-1/3 bg-gray-200 rounded-t transition-all"
                                     style="height: {{ $lastYearRevenue > 0 ? max(4, ($lastYearRevenue / $maxRevenue) * 160) : 4 }}px"
                                     title="Last Year: {{ number_format($lastYearRevenue, 2) }}">
                                </div>
                                {{-- Current Year Bar (blue) --}}
                                <div class="w-1/3 bg-blue-500 rounded-t transition-all"
                                     style="height: {{ $currentRevenue > 0 ? max(4, ($currentRevenue / $maxRevenue) * 160) : 4 }}px"
                                     title="Current: {{ number_format($currentRevenue, 2) }}">
                                </div>
                            </div>
                            <span class="text-xs text-gray-500 mt-1">{{ $currentDate->format('j') }}</span>

                            {{-- Tooltip --}}
                            <div class="absolute bottom-full mb-2 hidden group-hover:block z-10">
                                <div class="bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                    <div>{{ $currentDate->format('M j, Y') }}</div>
                                    <div class="text-blue-300">Current: {{ number_format($currentRevenue, 2) }}</div>
                                    <div class="text-gray-400">Last Year: {{ number_format($lastYearRevenue, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
                <div class="flex items-center justify-center mt-4 space-x-6 text-sm">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded mr-2"></div>
                        <span class="text-gray-600">Current Period</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-gray-200 rounded mr-2"></div>
                        <span class="text-gray-600">Same Period Last Year</span>
                    </div>
                </div>
                {{-- Summary Stats --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Period Total</p>
                        <p class="text-lg font-semibold text-gray-900">&euro;{{ number_format($stats['total_revenue'], 2) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Daily Average</p>
                        <p class="text-lg font-semibold text-gray-900">&euro;{{ number_format($stats['total_revenue'] / max(1, $periodDays), 2) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Last Year Total</p>
                        <p class="text-lg font-semibold text-gray-500">&euro;{{ number_format($lastYearStats['total_revenue'], 2) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Last Year Avg</p>
                        <p class="text-lg font-semibold text-gray-500">&euro;{{ number_format($lastYearStats['total_revenue'] / max(1, $periodDays), 2) }}</p>
                    </div>
                </div>
            </div>

            {{-- Top Sellers Tables --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Top 10 by Volume --}}
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Top 10 by Volume</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($topByVolume as $index => $product)
                            <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-50">
                                <div class="flex items-center">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-600 mr-3">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $product->product_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $product->product_code }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900">{{ number_format($product->total_units) }} units</p>
                                    <p class="text-xs text-gray-500">&euro;{{ number_format($product->total_revenue, 2) }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-gray-500">
                                No sales data available for this period
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Top 10 by Revenue --}}
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Top 10 by Revenue</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($topByRevenue as $index => $product)
                            <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-50">
                                <div class="flex items-center">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-600 mr-3">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $product->product_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $product->product_code }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900">&euro;{{ number_format($product->total_revenue, 2) }}</p>
                                    <p class="text-xs text-gray-500">{{ number_format($product->total_units) }} units</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-gray-500">
                                No sales data available for this period
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
