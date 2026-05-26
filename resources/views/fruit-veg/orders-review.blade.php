<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    F&V Order Review
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Sales Period: {{ $salesPeriod['start']->format('M j, Y') }} – {{ $salesPeriod['end']->format('M j, Y') }}
                    ({{ $salesPeriod['days'] }} days)
                    • Coverage: {{ $coverageDays }} days
                </p>
            </div>
            <div class="flex flex-wrap gap-2 justify-end">
                <a href="{{ route('fruit-veg.orders') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-medium text-sm transition">
                    ← New Order
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Products</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $statistics['total_products'] }}</div>
                </div>
                <div class="bg-red-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-red-600">Fruits</div>
                    <div class="mt-1 text-2xl font-semibold text-red-700">{{ $fruitProducts->count() }}</div>
                </div>
                <div class="bg-green-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-green-600">Vegetables</div>
                    <div class="mt-1 text-2xl font-semibold text-green-700">{{ $vegetableProducts->count() }}</div>
                </div>
                <div class="bg-blue-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-blue-600">Barcoded</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-700">{{ $barcodedProducts->count() }}</div>
                </div>
            </div>

            <!-- Sort Bar -->
            <div class="bg-white rounded-lg shadow mb-6 p-4 flex items-center justify-between">
                <div class="flex space-x-2">
                    <span class="text-sm text-gray-500">
                        Showing products with sales in the selected period
                    </span>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                        <label for="order-sort" class="font-medium text-gray-500">Sort</label>
                        <select id="order-sort" class="border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="sales">Sales ↓</option>
                            <option value="name">Name A-Z</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Fruits Section -->
            @if($fruitProducts->count() > 0)
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 px-2 py-2 bg-red-50 rounded-t-lg border-b-2 border-red-400 flex items-center gap-2">
                    <span class="text-2xl">🍎</span> Fruits ({{ $fruitProducts->count() }})
                </h3>
                <div class="bg-white rounded-b-lg shadow overflow-hidden">
                    @include('fruit-veg.partials.order-table', ['products' => $fruitProducts, 'categoryColor' => 'red'])
                </div>
            </div>
            @endif

            <!-- Vegetables Section -->
            @if($vegetableProducts->count() > 0)
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 px-2 py-2 bg-green-50 rounded-t-lg border-b-2 border-green-400 flex items-center gap-2">
                    <span class="text-2xl">🥬</span> Vegetables ({{ $vegetableProducts->count() }})
                </h3>
                <div class="bg-white rounded-b-lg shadow overflow-hidden">
                    @include('fruit-veg.partials.order-table', ['products' => $vegetableProducts, 'categoryColor' => 'green'])
                </div>
            </div>
            @endif

            <!-- Barcoded Section -->
            @if($barcodedProducts->count() > 0)
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 px-2 py-2 bg-blue-50 rounded-t-lg border-b-2 border-blue-400 flex items-center gap-2">
                    <span class="text-2xl">📦</span> Veg Barcoded ({{ $barcodedProducts->count() }})
                </h3>
                <div class="bg-white rounded-b-lg shadow overflow-hidden">
                    @include('fruit-veg.partials.order-table', ['products' => $barcodedProducts, 'categoryColor' => 'blue'])
                </div>
            </div>
            @endif

            @if($fruitProducts->count() === 0 && $vegetableProducts->count() === 0 && $barcodedProducts->count() === 0)
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            No F&V products with sales found in the selected period.
                            Try extending your date range.
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Client-side sorting
            const sortSelect = document.getElementById('order-sort');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    const sortBy = this.value;

                    // Sort each category table
                    document.querySelectorAll('table tbody').forEach(tbody => {
                        const rows = Array.from(tbody.querySelectorAll('tr'));

                        rows.sort((a, b) => {
                            if (sortBy === 'name') {
                                const nameA = a.querySelector('td:nth-child(2) a')?.textContent?.trim() || '';
                                const nameB = b.querySelector('td:nth-child(2) a')?.textContent?.trim() || '';
                                return nameA.localeCompare(nameB);
                            } else {
                                // Sort by sales (descending)
                                const salesA = parseInt(a.querySelector('td:nth-child(3) .text-2xl')?.textContent?.replace(/,/g, '') || '0');
                                const salesB = parseInt(b.querySelector('td:nth-child(3) .text-2xl')?.textContent?.replace(/,/g, '') || '0');
                                return salesB - salesA;
                            }
                        });

                        // Re-append rows in sorted order
                        rows.forEach(row => tbody.appendChild(row));
                    });
                });
            }

            // Initialize mini charts - matching order system style
            document.querySelectorAll('canvas[id^="chart_"]').forEach(canvas => {
                const weekLabels = JSON.parse(canvas.dataset.weekLabels || '[]');
                const weekUnits = JSON.parse(canvas.dataset.weekUnits || '[]');
                const avgWeekly = parseFloat(canvas.dataset.avgWeekly) || 0;
                const peakWeekly = parseFloat(canvas.dataset.peakWeekly) || 0;

                // Create average line data (same value for each week)
                const averageLineData = weekLabels.map(() => avgWeekly);

                // Calculate chart max
                const salesMax = weekUnits.length ? Math.max(...weekUnits) : 0;
                const chartMax = Math.max(salesMax, avgWeekly, peakWeekly, 1) * 1.15;

                // Set point colors - grey for 0 sales, blue otherwise
                const primaryColor = 'rgb(59, 130, 246)';
                const greyColor = 'rgb(203, 213, 225)';
                const pointBackgroundColors = weekUnits.map(v => v === 0 ? greyColor : primaryColor);

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: weekLabels,
                        datasets: [
                            {
                                label: 'Average weekly',
                                data: averageLineData,
                                borderColor: 'rgba(100, 116, 139, 0.7)',
                                borderWidth: 1.5,
                                borderDash: [6, 4],
                                pointRadius: 0,
                                pointHoverRadius: 0,
                                fill: false,
                                tension: 0,
                                order: 1
                            },
                            {
                                label: 'Sales',
                                data: weekUnits,
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderColor: primaryColor,
                                borderWidth: 2,
                                pointBackgroundColor: pointBackgroundColors,
                                pointBorderColor: 'white',
                                pointBorderWidth: 1,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                fill: true,
                                tension: 0.3,
                                order: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: 0 },
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 10,
                                cornerRadius: 4,
                                titleFont: { size: 12, weight: 'bold' },
                                bodyFont: { size: 12 },
                                displayColors: false,
                                callbacks: {
                                    label: function(context) {
                                        if (context.dataset.label === 'Average weekly') {
                                            return `Avg weekly: ${avgWeekly.toFixed(1)} units`;
                                        }
                                        if (context.dataset.label === 'Sales') {
                                            const value = parseFloat(context.parsed.y.toFixed(2));
                                            if (value === 0) {
                                                return '0 units sold';
                                            }
                                            return value + ' units sold';
                                        }
                                        return null;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: { display: false },
                                ticks: {
                                    font: { size: 9 },
                                    color: 'rgb(107, 114, 128)'
                                }
                            },
                            y: {
                                display: false,
                                beginAtZero: true,
                                max: chartMax
                            }
                        }
                    }
                });
            });
        });
    </script>
    @endpush
</x-admin-layout>
