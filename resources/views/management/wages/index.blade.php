<x-admin-layout>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Wages Management</h1>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 rounded-lg">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <!-- Upload Form -->
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Import Payroll File</h3>
            <form action="{{ route('management.wages.upload') }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-start sm:items-end gap-4">
                @csrf
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Gross to Net Total By Week Number (.xls/.xlsx)
                    </label>
                    <input type="file" name="file" accept=".xls,.xlsx" required
                           class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
                    @error('file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                    Import
                </button>
            </form>
        </div>

        <!-- Date Range Filter -->
        @if($years->count() > 0)
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                    <input type="date" name="start_date" value="{{ $startDate }}"
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                    <input type="date" name="end_date" value="{{ $endDate }}"
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                    Filter
                </button>
                <a href="{{ route('management.wages.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-medium">
                    Reset
                </a>

                <!-- Year quick filters -->
                <div class="flex items-center gap-2 ml-auto">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Year:</span>
                    @foreach($years as $year)
                        <a href="{{ route('management.wages.index', ['year' => $year]) }}"
                           class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterMode === 'year' && (string)$selectedYear === (string)$year ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                            {{ $year }}
                        </a>
                    @endforeach
                </div>
            </form>
        </div>

        <!-- Active filter info -->
        @if($filterMode === 'date_range')
        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm text-blue-800 dark:text-blue-200">
            Showing {{ $entries->count() }} weeks from {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
        </div>
        @endif
        @endif

        <!-- Chart -->
        @if($entries->count() > 1)
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Weekly Wages</h3>
            <div style="height: 300px;">
                <canvas id="wagesChart"></canvas>
            </div>
        </div>
        @endif

        <!-- Delete Year -->
        @if($filterMode === 'year' && $selectedYear && $entries->count() > 0)
        <div class="mb-4 flex justify-end">
            <form action="{{ route('management.wages.destroy-year', ['year' => $selectedYear]) }}" method="POST"
                  onsubmit="return confirm('Delete ALL wage entries for {{ $selectedYear }}? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                    Delete All {{ $selectedYear }}
                </button>
            </form>
        </div>
        @endif

        <!-- Wage Entries Table -->
        @if($entries->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Week</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Dates</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Gross Pay</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tax</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">USC+Levy</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">PRSI(EE)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">LPT</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net Pay</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">PRSI(ER)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Employer Cost</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($entries as $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">{{ $entry->week_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ $entry->week_start_date->format('d M') }} - {{ $entry->week_end_date->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($entry->gross_pay, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($entry->tax, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($entry->usc_levy, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($entry->prsi_ee, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($entry->lpt, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right font-medium">&euro;{{ number_format($entry->net_pay, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($entry->prsi_er, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-400">&euro;{{ number_format($entry->total_employer_cost, 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                <form action="{{ route('management.wages.destroy', $entry) }}" method="POST"
                                      onsubmit="return confirm('Delete Week {{ $entry->week_number }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <!-- Totals Row -->
                    <tfoot class="bg-gray-100 dark:bg-gray-900 font-semibold">
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white" colspan="2">Totals ({{ $entries->count() }} weeks)</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['gross_pay'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['tax'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['usc_levy'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['prsi_ee'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['lpt'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['net_pay'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right">&euro;{{ number_format($totals['prsi_er'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-400">&euro;{{ number_format($totals['employer_cost'], 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @elseif($years->count() === 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No wage data</h3>
            <p class="mt-2 text-gray-500 dark:text-gray-400">Upload a "Gross to Net Total By Week Number" XLS file to get started.</p>
        </div>
        @endif
    </div>

    @if($entries->count() > 1)
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartData = @json($chartData);
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            const ctx = document.getElementById('wagesChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.map(d => d.label),
                    datasets: [
                        {
                            label: 'Gross Pay',
                            data: chartData.map(d => d.gross_pay),
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1,
                            stack: 'cost',
                        },
                        {
                            label: 'Employer PRSI',
                            data: chartData.map(d => d.prsi_er),
                            backgroundColor: 'rgba(249, 115, 22, 0.7)',
                            borderColor: 'rgb(249, 115, 22)',
                            borderWidth: 1,
                            stack: 'cost',
                        },
                        {
                            label: 'Net Pay',
                            data: chartData.map(d => d.net_pay),
                            type: 'line',
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            borderWidth: 2,
                            pointRadius: 2,
                            tension: 0.3,
                            fill: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': \u20AC' + context.parsed.y.toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        },
                        legend: {
                            labels: { color: textColor }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: textColor, maxRotation: 45 },
                            grid: { color: gridColor }
                        },
                        y: {
                            ticks: {
                                color: textColor,
                                callback: function(value) { return '\u20AC' + value.toLocaleString(); }
                            },
                            grid: { color: gridColor }
                        }
                    }
                }
            });
        });
    </script>
    @endif
</x-admin-layout>
