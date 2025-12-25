<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🚀 Sales Data Import System
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Performance Banner -->
            @if($performanceData)
            <div class="bg-gradient-to-r from-green-500 to-blue-600 rounded-lg p-6 mb-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold">🚀 Lightning Fast Analytics</h3>
                        <p class="text-green-100">Last 7-day query executed in <span class="font-bold text-yellow-300">{{ $performanceData['execution_time_ms'] }}ms</span></p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold">{{ number_format($performanceData['total_units'], 0) }}</div>
                        <div class="text-green-100">Units Sold</div>
                    </div>
                </div>
            </div>
            @endif

            <!-- System Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Daily Records -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm">📊</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Daily Records</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($dailyRecordCount) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Records -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm">📅</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Monthly Records</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($monthlyRecordCount) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Range -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm">📈</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Data Range</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        @if($dateRange && $dateRange->earliest)
                                            {{ \Carbon\Carbon::parse($dateRange->earliest)->format('M j, Y') }} - 
                                            {{ \Carbon\Carbon::parse($dateRange->latest)->format('M j, Y') }}
                                        @else
                                            No data
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Last Import -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm">⏰</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Last Import</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        @if($latestImport)
                                            {{ $latestImport->created_at->diffForHumans() }}
                                        @else
                                            Never
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Quick Access -->
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 mb-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold">🔍 Data Validation & Comparison</h3>
                        <p class="text-blue-100 mt-1">Compare imported data with original POS database to ensure accuracy</p>
                    </div>
                    <div>
                        <a href="{{ route('sales-import.validation') }}" class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors">
                            Open Validation Interface
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Validation Tools -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🔍 Quick Validation Tools</h3>
                    <p class="text-sm text-gray-600 mt-1">Fast checks that work on production (unlike full validation)</p>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button class="quick-val-tab active w-1/2 py-4 px-1 text-center border-b-2 border-blue-500 font-medium text-sm text-blue-600" data-tab="gap-finder">
                            Gap Finder
                            <span class="block text-xs text-gray-500 font-normal">Find missing days</span>
                        </button>
                        <button class="quick-val-tab w-1/2 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="daily-totals">
                            Daily Totals Check
                            <span class="block text-xs text-gray-500 font-normal">Find value discrepancies</span>
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Gap Finder Tab -->
                    <div id="tab-gap-finder" class="quick-val-content">
                        <form id="gap-finder-form" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" name="start_date"
                                           value="{{ $dateRange && $dateRange->earliest ? \Carbon\Carbon::parse($dateRange->earliest)->format('Y-m-d') : now()->subYear()->format('Y-m-d') }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" name="end_date"
                                           value="{{ now()->format('Y-m-d') }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        Find Missing Days
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Gap Finder Results -->
                        <div id="gap-finder-results" class="hidden mt-6">
                            <div id="gap-finder-summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            </div>
                            <div id="gap-finder-status" class="mb-4">
                            </div>
                            <div id="gap-finder-missing" class="hidden">
                                <h4 class="text-md font-medium text-red-700 mb-3">Missing Days (POS has data, not imported)</h4>
                                <div id="missing-days-list" class="space-y-2 max-h-64 overflow-y-auto">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Totals Tab -->
                    <div id="tab-daily-totals" class="quick-val-content hidden">
                        <form id="daily-totals-form" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" name="start_date"
                                           value="{{ $dateRange && $dateRange->earliest ? \Carbon\Carbon::parse($dateRange->earliest)->format('Y-m-d') : now()->subYear()->format('Y-m-d') }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" name="end_date"
                                           value="{{ now()->format('Y-m-d') }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                        Check Daily Totals
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Daily Totals Results -->
                        <div id="daily-totals-results" class="hidden mt-6">
                            <div id="daily-totals-summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            </div>
                            <div id="daily-totals-status" class="mb-4">
                            </div>
                            <div id="daily-totals-discrepancies" class="hidden">
                                <h4 class="text-md font-medium text-red-700 mb-3">Days with Discrepancies</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Imported</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">POS</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Diff</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="discrepancies-tbody" class="bg-white divide-y divide-gray-200">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="daily-totals-missing" class="hidden mt-4">
                                <h4 class="text-md font-medium text-orange-700 mb-3">Missing Days (not imported)</h4>
                                <div id="daily-missing-list" class="space-y-2 max-h-48 overflow-y-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Panels -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Import Controls -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">📥 Import Controls</h3>
                    </div>
                    <div class="p-6">
                        
                        <!-- Daily Import -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Daily Sales Import</h4>
                            <form id="daily-import-form" class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                        <input type="date" name="start_date" value="{{ now()->subDays(1)->format('Y-m-d') }}" 
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">End Date</label>
                                        <input type="date" name="end_date" value="{{ now()->subDays(1)->format('Y-m-d') }}" 
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Import Daily Sales
                                </button>
                            </form>
                        </div>

                        <!-- Monthly Summaries -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Monthly Summaries</h4>
                            <form id="monthly-summaries-form" class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Year</label>
                                        <input type="number" name="year" value="{{ date('Y') }}" min="2020" max="{{ date('Y') + 1 }}" 
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Month (Optional)</label>
                                        <select name="month" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">All Months</option>
                                            @for($i = 1; $i <= 12; $i++)
                                                <option value="{{ $i }}" {{ $i == date('n') ? 'selected' : '' }}>
                                                    {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    Generate Monthly Summaries
                                </button>
                            </form>
                        </div>

                        <!-- Test Data -->
                        <div>
                            <h4 class="text-md font-medium text-gray-700 mb-3">Create Test Data</h4>
                            <form id="test-data-form" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Number of Days</label>
                                    <input type="number" name="days" value="30" min="1" max="365" 
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                    Create Test Data
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Performance Testing -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">⚡ Performance Testing</h3>
                    </div>
                    <div class="p-6">
                        
                        <div class="mb-6">
                            <p class="text-sm text-gray-600 mb-4">Test the performance of the optimized sales repository. Expected results:</p>
                            <ul class="text-sm text-gray-500 space-y-1 mb-6">
                                <li>• Sales Statistics: &lt; 20ms</li>
                                <li>• Daily Sales Chart: &lt; 5ms</li>
                                <li>• Top Products: &lt; 5ms</li>
                                <li>• Category Performance: &lt; 5ms</li>
                            </ul>
                            
                            <button id="run-performance-test" class="w-full bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                🚀 Run Performance Test
                            </button>
                        </div>

                        <!-- Performance Results -->
                        <div id="performance-results" class="hidden">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Test Results</h4>
                            <div id="performance-data"></div>
                        </div>

                        <!-- Dangerous Actions -->
                        <div class="border-t pt-6">
                            <h4 class="text-md font-medium text-red-700 mb-3">⚠️ Danger Zone</h4>
                            <button id="clear-data" class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                Clear All Imported Data
                            </button>
                            <p class="text-xs text-red-500 mt-2">This will delete all imported sales data. Use with caution!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Logs -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">📋 Import Logs</h3>
                    <button id="refresh-logs" class="bg-gray-600 text-white px-3 py-1 text-sm rounded-md hover:bg-gray-700">
                        Refresh
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="import-logs-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inserted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody id="logs-tbody" class="bg-white divide-y divide-gray-200">
                            <!-- Logs will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Area -->
    <div id="notification" class="fixed top-4 right-4 hidden">
        <!-- Notifications will be inserted here -->
    </div>

    <script>
        // Utility functions
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            
            notification.innerHTML = `
                <div class="${bgColor} text-white px-6 py-4 rounded-lg shadow-lg max-w-sm">
                    <div class="flex items-center">
                        <span class="mr-2">${type === 'success' ? '✅' : '❌'}</span>
                        <span>${message}</span>
                    </div>
                </div>
            `;
            
            notification.classList.remove('hidden');
            
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 5000);
        }

        function showLoading(button) {
            const originalText = button.textContent;
            button.textContent = 'Loading...';
            button.disabled = true;
            
            return () => {
                button.textContent = originalText;
                button.disabled = false;
            };
        }

        // Daily Import Form
        document.getElementById('daily-import-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            const hideLoading = showLoading(button);
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('{{ route('sales-import.run-daily') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`Import completed! ${data.data.records_processed} processed, ${data.data.records_inserted} inserted, ${data.data.records_updated} updated in ${data.data.execution_time}s`);
                    loadImportLogs();
                    // Refresh page to update stats
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Import failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        // Monthly Summaries Form
        document.getElementById('monthly-summaries-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            const hideLoading = showLoading(button);
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('{{ route('sales-import.run-monthly') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`Monthly summaries completed! ${data.data.records_processed} processed, ${data.data.records_inserted} inserted, ${data.data.records_updated} updated in ${data.data.execution_time}s`);
                    loadImportLogs();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Monthly summaries failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        // Test Data Form
        document.getElementById('test-data-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            const hideLoading = showLoading(button);
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('{{ route('sales-import.create-test-data') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Test data creation failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        // Performance Test
        document.getElementById('run-performance-test').addEventListener('click', async (e) => {
            const button = e.target;
            const hideLoading = showLoading(button);
            
            try {
                const response = await fetch('{{ route('sales-import.performance-test') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`Performance test completed in ${data.total_time_ms}ms!`);
                    
                    // Show results
                    const resultsDiv = document.getElementById('performance-results');
                    const dataDiv = document.getElementById('performance-data');
                    
                    let html = `<div class="space-y-3">`;
                    html += `<div class="text-sm font-medium text-green-600">Total Time: ${data.total_time_ms}ms</div>`;
                    
                    Object.values(data.tests).forEach(test => {
                        const isGood = test.execution_time_ms < 20;
                        const colorClass = isGood ? 'text-green-600' : 'text-red-600';
                        html += `<div class="flex justify-between text-sm">
                            <span>${test.name}</span>
                            <span class="${colorClass} font-medium">${test.execution_time_ms}ms</span>
                        </div>`;
                    });
                    
                    html += `</div>`;
                    dataDiv.innerHTML = html;
                    resultsDiv.classList.remove('hidden');
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Performance test failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        // Clear Data
        document.getElementById('clear-data').addEventListener('click', async (e) => {
            if (!confirm('Are you sure you want to clear ALL imported data? This cannot be undone!')) {
                return;
            }
            
            const button = e.target;
            const hideLoading = showLoading(button);
            
            try {
                const response = await fetch('{{ route('sales-import.clear-data') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Clear data failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        // Load Import Logs
        async function loadImportLogs() {
            try {
                const response = await fetch('{{ route('sales-import.logs') }}');
                const logs = await response.json();
                
                const tbody = document.getElementById('logs-tbody');
                tbody.innerHTML = '';
                
                logs.forEach(log => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${log.type}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.date_range}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.records_processed}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.records_inserted}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.records_updated}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.execution_time}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${log.status_class}">
                                ${log.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.created_at}</td>
                    `;
                    tbody.appendChild(row);
                });
            } catch (error) {
                console.error('Failed to load import logs:', error);
            }
        }

        // Refresh Logs Button
        document.getElementById('refresh-logs').addEventListener('click', loadImportLogs);

        // Load logs on page load
        document.addEventListener('DOMContentLoaded', loadImportLogs);

        // Quick Validation Tabs
        document.querySelectorAll('.quick-val-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Update active tab
                document.querySelectorAll('.quick-val-tab').forEach(t => {
                    t.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                tab.classList.add('active', 'border-blue-500', 'text-blue-600');
                tab.classList.remove('border-transparent', 'text-gray-500');

                // Show/hide content
                document.querySelectorAll('.quick-val-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById('tab-' + tab.dataset.tab).classList.remove('hidden');
            });
        });

        // Gap Finder Form
        document.getElementById('gap-finder-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            const hideLoading = showLoading(button);

            const formData = new FormData(e.target);

            try {
                const response = await fetch('{{ route('sales-import.find-gaps') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    displayGapFinderResults(result.data);
                    showNotification(`Scan completed in ${result.data.execution_time_seconds}s - ${result.data.missing_count} missing days found`);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Gap finder failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        function displayGapFinderResults(data) {
            const resultsDiv = document.getElementById('gap-finder-results');
            const summaryDiv = document.getElementById('gap-finder-summary');
            const statusDiv = document.getElementById('gap-finder-status');
            const missingDiv = document.getElementById('gap-finder-missing');
            const missingList = document.getElementById('missing-days-list');

            // Show results container
            resultsDiv.classList.remove('hidden');

            // Summary cards
            summaryDiv.innerHTML = `
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">${data.pos_count}</div>
                    <div class="text-sm text-blue-700">Days in POS</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">${data.imported_count}</div>
                    <div class="text-sm text-green-700">Days Imported</div>
                </div>
                <div class="bg-${data.missing_count > 0 ? 'red' : 'green'}-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-${data.missing_count > 0 ? 'red' : 'green'}-600">${data.missing_count}</div>
                    <div class="text-sm text-${data.missing_count > 0 ? 'red' : 'green'}-700">Missing Days</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-gray-600">${data.execution_time_seconds}s</div>
                    <div class="text-sm text-gray-700">Scan Time</div>
                </div>
            `;

            // Status indicator
            if (data.status === 'complete') {
                statusDiv.innerHTML = `
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                        <p class="font-medium">✅ All Clear! No missing days found.</p>
                        <p class="text-sm mt-1">All sales data from POS has been imported for the selected period.</p>
                    </div>
                `;
                missingDiv.classList.add('hidden');
            } else {
                statusDiv.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                        <p class="font-medium">⚠️ Gaps Found! ${data.missing_count} day(s) need to be imported.</p>
                        <p class="text-sm mt-1">Click "Import" next to each missing day to import the data.</p>
                    </div>
                `;

                // Show missing days with import buttons
                missingDiv.classList.remove('hidden');
                missingList.innerHTML = '';

                data.missing_days.forEach(date => {
                    const dayDiv = document.createElement('div');
                    dayDiv.className = 'flex items-center justify-between bg-red-50 rounded-lg p-3';
                    dayDiv.innerHTML = `
                        <div>
                            <span class="font-medium text-red-700">${date}</span>
                            <span class="text-sm text-red-600 ml-2">(${new Date(date).toLocaleDateString('en-IE', { weekday: 'long' })})</span>
                        </div>
                        <button onclick="importSingleDay('${date}')" class="bg-blue-600 text-white px-3 py-1 text-sm rounded hover:bg-blue-700">
                            Import
                        </button>
                    `;
                    missingList.appendChild(dayDiv);
                });

                // Add "Import All" button if there are multiple missing days
                if (data.missing_days.length > 1) {
                    const importAllDiv = document.createElement('div');
                    importAllDiv.className = 'mt-4 pt-4 border-t border-gray-200';
                    importAllDiv.innerHTML = `
                        <button onclick="importAllMissingDays()" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            Import All ${data.missing_days.length} Missing Days
                        </button>
                    `;
                    missingList.appendChild(importAllDiv);
                }
            }
        }

        // Store missing days for bulk import
        let currentMissingDays = [];

        async function importSingleDay(date) {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Importing...';
            button.disabled = true;

            try {
                const formData = new FormData();
                formData.append('start_date', date);
                formData.append('end_date', date);

                const response = await fetch('{{ route('sales-import.run-daily') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(`Imported ${date}: ${data.data.records_processed} records`);
                    // Update button to show success
                    button.textContent = '✓ Done';
                    button.className = 'bg-green-600 text-white px-3 py-1 text-sm rounded cursor-default';
                    button.onclick = null;

                    // Refresh logs
                    loadImportLogs();
                } else {
                    showNotification(`Failed to import ${date}: ${data.message}`, 'error');
                    button.textContent = originalText;
                    button.disabled = false;
                }
            } catch (error) {
                showNotification(`Failed to import ${date}: ${error.message}`, 'error');
                button.textContent = originalText;
                button.disabled = false;
            }
        }

        async function importAllMissingDays() {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Importing...';
            button.disabled = true;

            // Get all missing days from the list
            const missingDays = Array.from(document.querySelectorAll('#missing-days-list > div:not(.border-t) .font-medium'))
                .map(el => el.textContent);

            let imported = 0;
            let failed = 0;

            for (const date of missingDays) {
                try {
                    const formData = new FormData();
                    formData.append('start_date', date);
                    formData.append('end_date', date);

                    const response = await fetch('{{ route('sales-import.run-daily') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        imported++;
                        button.textContent = `Importing... (${imported}/${missingDays.length})`;
                    } else {
                        failed++;
                    }
                } catch (error) {
                    failed++;
                }
            }

            showNotification(`Import complete: ${imported} succeeded, ${failed} failed`);
            button.textContent = '✓ All Done';
            button.className = 'w-full bg-green-600 text-white px-4 py-2 rounded-md cursor-default';
            button.onclick = null;

            // Refresh the gap finder to show updated results
            setTimeout(() => {
                document.getElementById('gap-finder-form').dispatchEvent(new Event('submit'));
            }, 1000);

            loadImportLogs();
        }

        // Daily Totals Form
        document.getElementById('daily-totals-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            const hideLoading = showLoading(button);

            const formData = new FormData(e.target);

            try {
                const response = await fetch('{{ route('sales-import.find-daily-discrepancies') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    displayDailyTotalsResults(result.data);
                    const issueCount = result.data.summary.discrepancies + result.data.summary.missing;
                    showNotification(`Check completed in ${result.data.execution_time_seconds}s - ${issueCount} issue(s) found`);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Daily totals check failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        function displayDailyTotalsResults(data) {
            const resultsDiv = document.getElementById('daily-totals-results');
            const summaryDiv = document.getElementById('daily-totals-summary');
            const statusDiv = document.getElementById('daily-totals-status');
            const discrepanciesDiv = document.getElementById('daily-totals-discrepancies');
            const missingDiv = document.getElementById('daily-totals-missing');

            resultsDiv.classList.remove('hidden');

            // Summary cards
            summaryDiv.innerHTML = `
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">${data.summary.total_days}</div>
                    <div class="text-sm text-blue-700">Days Checked</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">${data.summary.matches}</div>
                    <div class="text-sm text-green-700">Matches</div>
                </div>
                <div class="bg-${data.summary.discrepancies > 0 ? 'red' : 'green'}-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-${data.summary.discrepancies > 0 ? 'red' : 'green'}-600">${data.summary.discrepancies}</div>
                    <div class="text-sm text-${data.summary.discrepancies > 0 ? 'red' : 'green'}-700">Discrepancies</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-gray-600">${data.summary.accuracy_percentage}%</div>
                    <div class="text-sm text-gray-700">Accuracy</div>
                </div>
            `;

            // Status indicator
            if (data.status === 'complete') {
                statusDiv.innerHTML = `
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                        <p class="font-medium">✅ All Clear! Daily totals match between POS and imported data.</p>
                        <p class="text-sm mt-1">Tolerance: €${data.tolerance_used} per day</p>
                    </div>
                `;
                discrepanciesDiv.classList.add('hidden');
                missingDiv.classList.add('hidden');
            } else {
                statusDiv.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                        <p class="font-medium">⚠️ Issues Found! ${data.summary.discrepancies} day(s) with value discrepancies, ${data.summary.missing} missing.</p>
                        <p class="text-sm mt-1">Re-import the affected days to fix. Tolerance: €${data.tolerance_used}</p>
                    </div>
                `;

                // Show discrepancies table
                if (data.discrepancies.length > 0) {
                    discrepanciesDiv.classList.remove('hidden');
                    const tbody = document.getElementById('discrepancies-tbody');
                    tbody.innerHTML = '';

                    data.discrepancies.forEach(d => {
                        const row = document.createElement('tr');
                        const diffClass = d.revenue_diff > 0 ? 'text-green-600' : 'text-red-600';
                        row.innerHTML = `
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">${d.date}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-right">€${d.imported_revenue.toFixed(2)}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-right">€${d.pos_revenue.toFixed(2)}</td>
                            <td class="px-4 py-2 text-sm font-medium ${diffClass} text-right">${d.revenue_diff > 0 ? '+' : ''}€${d.revenue_diff.toFixed(2)}</td>
                            <td class="px-4 py-2 text-center">
                                <button onclick="reimportDay('${d.date}')" class="bg-blue-600 text-white px-2 py-1 text-xs rounded hover:bg-blue-700">
                                    Re-import
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    discrepanciesDiv.classList.add('hidden');
                }

                // Show missing days
                if (data.missing_days.length > 0) {
                    missingDiv.classList.remove('hidden');
                    const list = document.getElementById('daily-missing-list');
                    list.innerHTML = '';

                    data.missing_days.forEach(d => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between bg-orange-50 rounded-lg p-3';
                        div.innerHTML = `
                            <div>
                                <span class="font-medium text-orange-700">${d.date}</span>
                                <span class="text-sm text-orange-600 ml-2">(€${d.pos_revenue.toFixed(2)} in POS)</span>
                            </div>
                            <button onclick="importSingleDay('${d.date}')" class="bg-blue-600 text-white px-2 py-1 text-xs rounded hover:bg-blue-700">
                                Import
                            </button>
                        `;
                        list.appendChild(div);
                    });
                } else {
                    missingDiv.classList.add('hidden');
                }
            }
        }

        async function reimportDay(date) {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = '...';
            button.disabled = true;

            try {
                const formData = new FormData();
                formData.append('start_date', date);
                formData.append('end_date', date);

                const response = await fetch('{{ route('sales-import.run-daily') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(`Re-imported ${date}: ${data.data.records_processed} records`);
                    button.textContent = '✓';
                    button.className = 'bg-green-600 text-white px-2 py-1 text-xs rounded cursor-default';
                    button.onclick = null;
                    loadImportLogs();
                } else {
                    showNotification(`Failed to re-import ${date}: ${data.message}`, 'error');
                    button.textContent = originalText;
                    button.disabled = false;
                }
            } catch (error) {
                showNotification(`Failed to re-import ${date}: ${error.message}`, 'error');
                button.textContent = originalText;
                button.disabled = false;
            }
        }
    </script>
</x-admin-layout>