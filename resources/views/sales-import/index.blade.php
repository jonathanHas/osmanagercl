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
    </script>
</x-admin-layout>