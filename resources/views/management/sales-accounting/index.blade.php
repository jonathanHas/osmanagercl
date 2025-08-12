<x-admin-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">Sales Accounting Report</h1>
                            <p class="text-gray-600 mt-1">VAT-compliant sales analysis with stock transfer separation</p>
                        </div>
                        @if($data)
                            <div class="flex space-x-3">
                                <a href="{{ route('management.sales-accounting.export-csv') }}?start_date={{ $startDate->format('Y-m-d') }}&end_date={{ $endDate->format('Y-m-d') }}" 
                                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Export CSV
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Date Range Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <form method="GET" action="{{ route('management.sales-accounting.index') }}" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" 
                                   name="start_date" 
                                   id="start_date" 
                                   value="{{ $startDate->format('Y-m-d') }}"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="flex-1">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" 
                                   name="end_date" 
                                   id="end_date" 
                                   value="{{ $endDate->format('Y-m-d') }}"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                                Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Error Messages -->
            @if(!empty($errors))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Errors occurred while generating the report:</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($errors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Report Results -->
            @if($data)
                <!-- Data Source Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-blue-800">
                            Report for {{ $startDate->format('M j, Y') }} to {{ $endDate->format('M j, Y') }}
                            @if($data['data_source'] === 'aggregated')
                                <span class="text-green-700 font-medium">(Using optimized data - Fast)</span>
                            @else
                                <span class="text-amber-700 font-medium">(Using real-time data - May be slower)</span>
                            @endif
                        </span>
                    </div>
                </div>

                @if(empty($data['payment_types']) && empty($data['departments']))
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v6a2 2 0 002 2h6a2 2 0 002-2v-7m0 0V8a2 2 0 00-2-2h-6a2 2 0 00-2 2v5z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Data Found</h3>
                        <p class="text-gray-600">No sales or stock transfer data found for the selected date range.</p>
                    </div>
                @else
                    <!-- Main Sales Section -->
                    @if(!empty($data['payment_types']))
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-xl font-semibold text-green-800">Customer Sales (Revenue)</h2>
                                <p class="text-sm text-gray-600 mt-1">Actual sales to customers - included in revenue calculations</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-green-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                                            @foreach($data['all_active_rates'] as $rate)
                                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ number_format($rate * 100, 1) }}% Net</th>
                                                @if($rate != 0)
                                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ number_format($rate * 100, 1) }}% VAT</th>
                                                @endif
                                            @endforeach
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Net Total</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Total</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @php
                                            $grandNetTotal = 0;
                                            $grandVatTotal = 0;
                                            $grandGrossTotal = 0;
                                            $grandTotalsByRate = array_fill_keys($data['all_active_rates'], ['net' => 0, 'vat' => 0]);
                                        @endphp
                                        
                                        @foreach($data['payment_types'] as $paymentType)
                                            @php
                                                $rowNetTotal = 0;
                                                $rowVatTotal = 0;
                                                $rowGrossTotal = 0;
                                            @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 capitalize">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                        @if($paymentType === 'cash') bg-green-100 text-green-800
                                                        @elseif($paymentType === 'magcard') bg-purple-100 text-purple-800
                                                        @elseif($paymentType === 'debt') bg-yellow-100 text-yellow-800
                                                        @elseif($paymentType === 'free') bg-orange-100 text-orange-800
                                                        @else bg-gray-100 text-gray-800
                                                        @endif">
                                                        {{ $paymentType }}
                                                    </span>
                                                </td>
                                                
                                                @foreach($data['all_active_rates'] as $rate)
                                                    @php
                                                        // Convert rate to string to match controller array keys
                                                        $rateKey = (string) $rate;
                                                        $sale = $data['main_sales'][$paymentType][$rateKey] ?? null;
                                                        $net = $sale ? $sale->net_amount : 0;
                                                        $vat = $sale ? $sale->vat_amount : 0;
                                                        $rowNetTotal += $net;
                                                        $rowVatTotal += $vat;
                                                        $grandTotalsByRate[$rate]['net'] += $net;
                                                        $grandTotalsByRate[$rate]['vat'] += $vat;
                                                    @endphp
                                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                        €{{ number_format($net, 2) }}
                                                    </td>
                                                    @if($rate != 0)
                                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                            €{{ number_format($vat, 2) }}
                                                        </td>
                                                    @endif
                                                @endforeach
                                                
                                                @php
                                                    $rowGrossTotal = $rowNetTotal + $rowVatTotal;
                                                    $grandNetTotal += $rowNetTotal;
                                                    $grandVatTotal += $rowVatTotal;
                                                    $grandGrossTotal += $rowGrossTotal;
                                                @endphp
                                                
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                    €{{ number_format($rowNetTotal, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                    €{{ number_format($rowVatTotal, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-800 text-center">
                                                    €{{ number_format($rowGrossTotal, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        
                                        <!-- Paperin Adjust Row (to cancel out gift voucher double-counting) -->
                                        @php
                                            $paperinTotal = 0;
                                            if (isset($data['main_sales']['paperin'])) {
                                                foreach ($data['main_sales']['paperin'] as $rateKey => $sale) {
                                                    $paperinTotal += $sale->gross_amount;
                                                }
                                            }
                                        @endphp
                                        
                                        @if($paperinTotal > 0)
                                            <tr class="hover:bg-gray-50 border-t border-gray-300">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                        paperin adjust*
                                                    </span>
                                                </td>
                                                
                                                @foreach($data['all_active_rates'] as $rate)
                                                    @php
                                                        if ($rate == 0) {
                                                            $adjustNet = -$paperinTotal;
                                                            $adjustVat = 0;
                                                            $grandTotalsByRate[$rate]['net'] += $adjustNet;
                                                            $grandTotalsByRate[$rate]['vat'] += $adjustVat;
                                                            $grandNetTotal += $adjustNet;
                                                            $grandVatTotal += $adjustVat;
                                                            $grandGrossTotal += $adjustNet;
                                                        } else {
                                                            $adjustNet = 0;
                                                            $adjustVat = 0;
                                                        }
                                                    @endphp
                                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                        €{{ number_format($adjustNet, 2) }}
                                                    </td>
                                                    @if($rate != 0)
                                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                            €{{ number_format($adjustVat, 2) }}
                                                        </td>
                                                    @endif
                                                @endforeach
                                                
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                    €{{ number_format(-$paperinTotal, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                    €0.00
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-800 text-center">
                                                    €{{ number_format(-$paperinTotal, 2) }}
                                                </td>
                                            </tr>
                                        @endif
                                        
                                        <!-- Totals Row -->
                                        <tr class="bg-gradient-to-r from-green-100 to-green-200 font-bold text-green-900 border-t-2 border-green-400">
                                            <td class="px-6 py-4 text-sm font-bold">Total Sales</td>
                                            @foreach($data['all_active_rates'] as $rate)
                                                <td class="px-3 py-4 text-sm text-center">€{{ number_format($grandTotalsByRate[$rate]['net'], 2) }}</td>
                                                @if($rate != 0)
                                                    <td class="px-3 py-4 text-sm text-center">€{{ number_format($grandTotalsByRate[$rate]['vat'], 2) }}</td>
                                                @endif
                                            @endforeach
                                            <td class="px-6 py-4 text-sm text-center">€{{ number_format($grandNetTotal, 2) }}</td>
                                            <td class="px-6 py-4 text-sm text-center">€{{ number_format($grandVatTotal, 2) }}</td>
                                            <td class="px-6 py-4 text-sm text-center">€{{ number_format($grandGrossTotal, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Stock Transfers Section -->
                    @if(!empty($data['departments']))
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6" x-data="{ transfersExpanded: false }">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-xl font-semibold text-blue-800">Internal Stock Transfers</h2>
                                        <p class="text-sm text-gray-600 mt-1">Stock movements between departments - not included in revenue</p>
                                    </div>
                                    <button @click="transfersExpanded = !transfersExpanded" 
                                            class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                        <span x-text="transfersExpanded ? 'Collapse' : 'Expand'"></span>
                                        <svg class="w-4 h-4 transform transition-transform" 
                                             :class="transfersExpanded ? 'rotate-180' : 'rotate-0'"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div x-show="transfersExpanded" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                                 x-transition:enter-end="opacity-100 transform translate-y-0"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100 transform translate-y-0"
                                 x-transition:leave-end="opacity-0 transform -translate-y-2">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            @foreach($data['all_active_rates'] as $rate)
                                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ number_format($rate * 100, 1) }}% Net</th>
                                                @if($rate != 0)
                                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ number_format($rate * 100, 1) }}% VAT</th>
                                                @endif
                                            @endforeach
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Net Total</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Total</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @php
                                            $transferNetTotal = 0;
                                            $transferVatTotal = 0;
                                            $transferGrossTotal = 0;
                                            $transferTotalsByRate = array_fill_keys($data['all_active_rates'], ['net' => 0, 'vat' => 0]);
                                        @endphp
                                        
                                        @foreach($data['departments'] as $department)
                                            @php
                                                $rowNetTotal = 0;
                                                $rowVatTotal = 0;
                                                $rowGrossTotal = 0;
                                            @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 capitalize">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        {{ $department }}
                                                    </span>
                                                </td>
                                                
                                                @foreach($data['all_active_rates'] as $rate)
                                                    @php
                                                        // Convert rate to string to match controller array keys
                                                        $rateKey = (string) $rate;
                                                        $transfer = $data['stock_transfers'][$department][$rateKey] ?? null;
                                                        $net = $transfer ? $transfer->net_amount : 0;
                                                        $vat = $transfer ? $transfer->vat_amount : 0;
                                                        $rowNetTotal += $net;
                                                        $rowVatTotal += $vat;
                                                        $transferTotalsByRate[$rate]['net'] += $net;
                                                        $transferTotalsByRate[$rate]['vat'] += $vat;
                                                    @endphp
                                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                        €{{ number_format($net, 2) }}
                                                    </td>
                                                    @if($rate != 0)
                                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                            €{{ number_format($vat, 2) }}
                                                        </td>
                                                    @endif
                                                @endforeach
                                                
                                                @php
                                                    $rowGrossTotal = $rowNetTotal + $rowVatTotal;
                                                    $transferNetTotal += $rowNetTotal;
                                                    $transferVatTotal += $rowVatTotal;
                                                    $transferGrossTotal += $rowGrossTotal;
                                                @endphp
                                                
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                    €{{ number_format($rowNetTotal, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                    €{{ number_format($rowVatTotal, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-800 text-center">
                                                    €{{ number_format($rowGrossTotal, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        
                                        <!-- Transfers Totals Row -->
                                        <tr class="bg-gradient-to-r from-blue-100 to-blue-200 font-bold text-blue-900 border-t-2 border-blue-400">
                                            <td class="px-6 py-4 text-sm font-bold">Total Transfers</td>
                                            @foreach($data['all_active_rates'] as $rate)
                                                <td class="px-3 py-4 text-sm text-center">€{{ number_format($transferTotalsByRate[$rate]['net'], 2) }}</td>
                                                @if($rate != 0)
                                                    <td class="px-3 py-4 text-sm text-center">€{{ number_format($transferTotalsByRate[$rate]['vat'], 2) }}</td>
                                                @endif
                                            @endforeach
                                            <td class="px-6 py-4 text-sm text-center">€{{ number_format($transferNetTotal, 2) }}</td>
                                            <td class="px-6 py-4 text-sm text-center">€{{ number_format($transferVatTotal, 2) }}</td>
                                            <td class="px-6 py-4 text-sm text-center">€{{ number_format($transferGrossTotal, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    @endif

                    <!-- Summary Section -->
                    @if(!empty($data['payment_types']))
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="p-3 bg-green-500 rounded-full">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m-3-5.5H9m1.5 0H12"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-green-800">Total Revenue</p>
                                        <p class="text-2xl font-bold text-green-900">€{{ number_format($grandGrossTotal ?? 0, 2) }}</p>
                                        <p class="text-xs text-green-600">Net: €{{ number_format($grandNetTotal ?? 0, 2) }} + VAT: €{{ number_format($grandVatTotal ?? 0, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            @if(!empty($data['departments']))
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="p-3 bg-blue-500 rounded-full">
                                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-blue-800">Stock Transfers</p>
                                            <p class="text-2xl font-bold text-blue-900">€{{ number_format($transferGrossTotal ?? 0, 2) }}</p>
                                            <p class="text-xs text-blue-600">Internal movements only</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="p-3 bg-indigo-500 rounded-full">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-indigo-800">VAT for Returns</p>
                                        <p class="text-2xl font-bold text-indigo-900">€{{ number_format($grandVatTotal ?? 0, 2) }}</p>
                                        <p class="text-xs text-indigo-600">Sales VAT only</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>