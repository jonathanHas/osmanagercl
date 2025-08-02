<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🔍 Sales Data Validation & Comparison
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Back to Main -->
            <div class="mb-6">
                <a href="{{ route('sales-import.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    ← Back to Sales Import
                </a>
            </div>

            <!-- Validation Controls -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🎯 Validation Controls</h3>
                    <p class="text-sm text-gray-600 mt-1">Compare imported data with original POS database to ensure accuracy</p>
                </div>
                <div class="p-6">
                    <form id="validation-form" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" name="start_date" 
                                       value="{{ $dateRange && $dateRange->earliest ? \Carbon\Carbon::parse($dateRange->earliest)->format('Y-m-d') : now()->subDays(7)->format('Y-m-d') }}" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" name="end_date" 
                                       value="{{ $dateRange && $dateRange->latest ? \Carbon\Carbon::parse($dateRange->latest)->format('Y-m-d') : now()->format('Y-m-d') }}" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            🔍 Run Validation
                        </button>
                    </form>
                </div>
            </div>

            <!-- Validation Results -->
            <div id="validation-results" class="space-y-8 hidden">
                
                <!-- Summary Card -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">📊 Validation Summary</h3>
                    </div>
                    <div class="p-6">
                        <div id="validation-summary" class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <!-- Summary cards will be populated here -->
                        </div>
                        <div id="validation-status" class="mt-6">
                            <!-- Status indicator will be populated here -->
                        </div>
                    </div>
                </div>

                <!-- Tabs for Different Views -->
                <div class="bg-white shadow rounded-lg">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                            <button class="validation-tab active py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600" data-tab="overview">
                                📈 Overview
                            </button>
                            <button class="validation-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="daily">
                                📅 Daily Comparison
                            </button>
                            <button class="validation-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="category">
                                🏷️ Category Analysis
                            </button>
                            <button class="validation-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="detailed">
                                🔬 Detailed Comparison
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Contents -->
                    <div class="p-6">
                        
                        <!-- Overview Tab -->
                        <div id="tab-overview" class="tab-content">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <!-- Totals Comparison -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-700 mb-4">💰 Totals Comparison</h4>
                                    <div id="totals-comparison" class="space-y-3">
                                        <!-- Totals will be populated here -->
                                    </div>
                                </div>
                                
                                <!-- Discrepancies Summary -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-700 mb-4">⚠️ Discrepancies</h4>
                                    <div id="discrepancies-summary" class="space-y-3">
                                        <!-- Discrepancies will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Comparison Tab -->
                        <div id="tab-daily" class="tab-content hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" id="daily-comparison-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported Revenue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">POS Revenue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="daily-comparison-tbody" class="bg-white divide-y divide-gray-200">
                                        <!-- Daily comparison data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Category Analysis Tab -->
                        <div id="tab-category" class="tab-content hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" id="category-comparison-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported Revenue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">POS Revenue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="category-comparison-tbody" class="bg-white divide-y divide-gray-200">
                                        <!-- Category comparison data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Detailed Comparison Tab -->
                        <div id="tab-detailed" class="tab-content hidden">
                            <div class="mb-4">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-md font-medium text-gray-700">🔬 Product-Level Comparison</h4>
                                    <div class="flex space-x-2">
                                        <select id="status-filter" class="border-gray-300 rounded-md shadow-sm text-sm">
                                            <option value="">All Status</option>
                                            <option value="perfect_match">Perfect Match</option>
                                            <option value="minor_variance">Minor Variance</option>
                                            <option value="significant_variance">Significant Variance</option>
                                            <option value="missing_imported">Missing in Imported</option>
                                            <option value="extra_imported">Extra in Imported</option>
                                        </select>
                                        <button id="export-detailed" class="bg-green-600 text-white px-3 py-1 text-sm rounded-md hover:bg-green-700">
                                            Export CSV
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" id="detailed-comparison-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">POS</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailed-comparison-tbody" class="bg-white divide-y divide-gray-200">
                                        <!-- Detailed comparison data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                            <div id="detailed-pagination" class="mt-4 flex justify-center">
                                <!-- Pagination will be added here if needed -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Area -->
    <div id="notification" class="fixed top-4 right-4 hidden">
        <!-- Notifications will be inserted here -->
    </div>

    <script>
        let currentValidationData = null;
        let currentDetailedData = null;

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

        function formatCurrency(amount) {
            return '€' + parseFloat(amount).toFixed(2);
        }

        function formatNumber(num, decimals = 2) {
            return parseFloat(num).toFixed(decimals);
        }

        function getStatusClass(status) {
            const classes = {
                'perfect_match': 'bg-green-100 text-green-800',
                'minor_variance': 'bg-yellow-100 text-yellow-800',
                'significant_variance': 'bg-red-100 text-red-800',
                'missing_imported': 'bg-orange-100 text-orange-800',
                'extra_imported': 'bg-purple-100 text-purple-800',
                'match': 'bg-green-100 text-green-800',
                'variance': 'bg-red-100 text-red-800',
                'no_data': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(status) {
            const texts = {
                'perfect_match': 'Perfect Match',
                'minor_variance': 'Minor Variance',
                'significant_variance': 'Significant Variance',
                'missing_imported': 'Missing in Imported',
                'extra_imported': 'Extra in Imported',
                'match': 'Match',
                'variance': 'Variance',
                'no_data': 'No Data'
            };
            return texts[status] || status;
        }

        // Tab functionality
        document.querySelectorAll('.validation-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Update active tab
                document.querySelectorAll('.validation-tab').forEach(t => {
                    t.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                tab.classList.add('active', 'border-blue-500', 'text-blue-600');
                tab.classList.remove('border-transparent', 'text-gray-500');

                // Show/hide tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById('tab-' + tab.dataset.tab).classList.remove('hidden');

                // Load tab-specific data
                loadTabData(tab.dataset.tab);
            });
        });

        // Validation form
        document.getElementById('validation-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            const hideLoading = showLoading(button);
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('{{ route('sales-import.validate-data') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    currentValidationData = result.data;
                    showValidationResults(result.data);
                    showNotification(`Validation completed! ${result.data.summary.accuracy_percentage}% accuracy`);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Validation failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        });

        function showValidationResults(data) {
            document.getElementById('validation-results').classList.remove('hidden');
            
            // Populate summary
            const summaryHTML = `
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">${data.summary.accuracy_percentage}%</div>
                    <div class="text-sm text-gray-500">Accuracy</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">${data.summary.matches}</div>
                    <div class="text-sm text-gray-500">Matches</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">${data.summary.discrepancies}</div>
                    <div class="text-sm text-gray-500">Discrepancies</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600">${data.execution_time_seconds}s</div>
                    <div class="text-sm text-gray-500">Execution Time</div>
                </div>
            `;
            document.getElementById('validation-summary').innerHTML = summaryHTML;
            
            // Status indicator
            const statusColors = {
                'excellent': 'bg-green-100 border-green-500 text-green-700',
                'good': 'bg-yellow-100 border-yellow-500 text-yellow-700',
                'needs_attention': 'bg-red-100 border-red-500 text-red-700'
            };
            const statusMessages = {
                'excellent': '🎉 Excellent! Data is perfectly synchronized',
                'good': '✅ Good! Minor discrepancies detected',
                'needs_attention': '⚠️ Attention needed! Significant discrepancies found'
            };
            
            document.getElementById('validation-status').innerHTML = `
                <div class="border-l-4 p-4 ${statusColors[data.status]}">
                    <p class="font-medium">${statusMessages[data.status]}</p>
                    <p class="text-sm mt-1">
                        Period: ${data.date_range.start} to ${data.date_range.end} (${data.date_range.days} days)
                    </p>
                </div>
            `;
            
            // Load initial tab data
            loadTabData('overview');
        }

        async function loadTabData(tab) {
            const formData = new FormData(document.getElementById('validation-form'));
            
            switch (tab) {
                case 'overview':
                    if (currentValidationData) {
                        loadOverviewTab();
                    }
                    break;
                case 'daily':
                    await loadDailyTab(formData);
                    break;
                case 'category':
                    await loadCategoryTab(formData);
                    break;
                case 'detailed':
                    await loadDetailedTab(formData);
                    break;
            }
        }

        function loadOverviewTab() {
            const data = currentValidationData;
            
            // Totals comparison
            const totalsHTML = `
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Revenue:</span>
                        <span class="text-sm font-medium">
                            Imported: ${formatCurrency(data.totals_comparison.imported.revenue)} | 
                            POS: ${formatCurrency(data.totals_comparison.pos.revenue)}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Units:</span>
                        <span class="text-sm font-medium">
                            Imported: ${formatNumber(data.totals_comparison.imported.units)} | 
                            POS: ${formatNumber(data.totals_comparison.pos.units)}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Transactions:</span>
                        <span class="text-sm font-medium">
                            Imported: ${data.totals_comparison.imported.transactions} | 
                            POS: ${data.totals_comparison.pos.transactions}
                        </span>
                    </div>
                </div>
            `;
            document.getElementById('totals-comparison').innerHTML = totalsHTML;
            
            // Discrepancies summary
            const discrepanciesHTML = `
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Missing in Imported:</span>
                        <span class="text-sm font-medium text-orange-600">${data.summary.missing_in_imported}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Extra in Imported:</span>
                        <span class="text-sm font-medium text-purple-600">${data.summary.extra_in_imported}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Revenue Variance:</span>
                        <span class="text-sm font-medium ${data.totals_comparison.variance.revenue === 0 ? 'text-green-600' : 'text-red-600'}">
                            ${formatCurrency(data.totals_comparison.variance.revenue)}
                        </span>
                    </div>
                </div>
            `;
            document.getElementById('discrepancies-summary').innerHTML = discrepanciesHTML;
        }

        async function loadDailyTab(formData) {
            try {
                console.log('Loading daily tab data...');
                const response = await fetch('{{ route('sales-import.daily-summary') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const result = await response.json();
                console.log('Daily tab response:', result);
                
                if (result.success) {
                    const tbody = document.getElementById('daily-comparison-tbody');
                    tbody.innerHTML = '';
                    
                    result.data.forEach(day => {
                        const row = document.createElement('tr');
                        const importedRevenue = day.imported ? formatCurrency(day.imported.revenue) : 'N/A';
                        const posRevenue = day.pos ? formatCurrency(day.pos.revenue) : 'N/A';
                        const variance = day.variances && day.variances.revenue !== null ? formatCurrency(day.variances.revenue) : 'N/A';
                        
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${day.date}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${importedRevenue}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${posRevenue}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${variance}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusClass(day.status)}">
                                    ${getStatusText(day.status)}
                                </span>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Daily tab error:', error);
                showNotification('Failed to load daily comparison: ' + error.message, 'error');
            }
        }

        async function loadCategoryTab(formData) {
            try {
                console.log('Loading category tab data...');
                const response = await fetch('{{ route('sales-import.category-validation') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const result = await response.json();
                console.log('Category tab response:', result);
                
                if (result.success) {
                    const tbody = document.getElementById('category-comparison-tbody');
                    tbody.innerHTML = '';
                    
                    result.data.forEach(category => {
                        const row = document.createElement('tr');
                        const importedRevenue = category.imported ? formatCurrency(category.imported.revenue) : 'N/A';
                        const posRevenue = category.pos ? formatCurrency(category.pos.revenue) : 'N/A';
                        const variance = category.variances && category.variances.revenue !== null ? formatCurrency(category.variances.revenue) : 'N/A';
                        
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${category.category_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${importedRevenue}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${posRevenue}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${variance}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusClass(category.status)}">
                                    ${getStatusText(category.status)}
                                </span>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Category tab error:', error);
                showNotification('Failed to load category comparison: ' + error.message, 'error');
            }
        }

        async function loadDetailedTab(formData) {
            try {
                console.log('Loading detailed tab data...');
                const response = await fetch('{{ route('sales-import.comparison-data') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const result = await response.json();
                console.log('Detailed tab response:', result);
                
                if (result.success) {
                    currentDetailedData = result.data;
                    displayDetailedData(result.data);
                }
            } catch (error) {
                console.error('Detailed tab error:', error);
                showNotification('Failed to load detailed comparison: ' + error.message, 'error');
            }
        }

        function displayDetailedData(data, filter = '') {
            const tbody = document.getElementById('detailed-comparison-tbody');
            tbody.innerHTML = '';
            
            const filteredData = filter ? data.filter(item => item.status === filter) : data;
            
            filteredData.slice(0, 100).forEach(item => { // Limit to 100 rows for performance
                const row = document.createElement('tr');
                const importedData = item.imported || {};
                const posData = item.pos || {};
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <div>${item.product_name}</div>
                        <div class="text-xs text-gray-500">${item.product_code}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.sale_date}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${item.imported ? formatCurrency(importedData.total_revenue) : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${item.pos ? formatCurrency(posData.total_revenue) : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${item.variances && item.variances.revenue !== null ? formatCurrency(item.variances.revenue) : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusClass(item.status)}">
                            ${getStatusText(item.status)}
                        </span>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            if (filteredData.length > 100) {
                document.getElementById('detailed-pagination').innerHTML = `
                    <p class="text-sm text-gray-500">Showing first 100 of ${filteredData.length} records</p>
                `;
            }
        }

        // Status filter
        document.getElementById('status-filter').addEventListener('change', (e) => {
            if (currentDetailedData) {
                displayDetailedData(currentDetailedData, e.target.value);
            }
        });

        // Export detailed data
        document.getElementById('export-detailed').addEventListener('click', () => {
            if (!currentDetailedData) {
                showNotification('No data to export', 'error');
                return;
            }
            
            // Simple CSV export
            const headers = ['Product Code', 'Product Name', 'Date', 'Imported Revenue', 'POS Revenue', 'Variance', 'Status'];
            const csvContent = [headers.join(',')];
            
            currentDetailedData.forEach(item => {
                const row = [
                    item.product_code,
                    `"${item.product_name}"`,
                    item.sale_date,
                    item.imported ? item.imported.total_revenue : '',
                    item.pos ? item.pos.total_revenue : '',
                    item.variances && item.variances.revenue !== null ? item.variances.revenue : '',
                    item.status
                ];
                csvContent.push(row.join(','));
            });
            
            const blob = new Blob([csvContent.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `sales_validation_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
            
            showNotification('Validation data exported successfully!');
        });
    </script>
</x-admin-layout>