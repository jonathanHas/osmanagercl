<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                VAT Dashboard
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('management.vat-returns.create') }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create VAT Return
                </a>
                <a href="{{ route('management.vat-dashboard.history') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    View History
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Outstanding Periods Alert -->
            @if(count($outstandingPeriods) > 0)
                <div class="mb-6">
                    <div class="bg-red-50 border-l-4 border-red-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Outstanding VAT Returns Required
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>The following periods have unsubmitted invoices and require VAT returns:</p>
                                    <ul class="list-disc list-inside mt-2">
                                        @foreach($outstandingPeriods as $period)
                                            <li>
                                                <a href="{{ route('management.vat-returns.create', ['end_date' => $period['end_date']->format('Y-m-d')]) }}" 
                                                   class="font-medium underline hover:text-red-900">
                                                    {{ $period['label'] }}
                                                </a>
                                                - {{ $period['invoice_count'] }} invoices 
                                                ({{ $period['days_overdue'] }} days overdue)
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Current Period & Next Deadline -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Current Period -->
                @if(!empty($currentPeriodInfo))
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Current VAT Period</h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Period:</dt>
                                    <dd class="text-sm text-gray-900">{{ $currentPeriodInfo['label'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Date Range:</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $currentPeriodInfo['start_date']->format('d M Y') }} - 
                                        {{ $currentPeriodInfo['end_date']->format('d M Y') }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Days Remaining:</dt>
                                    <dd class="text-sm text-gray-900">{{ $currentPeriodInfo['days_remaining'] }} days</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Unassigned Invoices:</dt>
                                    <dd class="text-sm text-gray-900">{{ $currentPeriodInfo['invoice_count'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Status:</dt>
                                    <dd>
                                        @if($currentPeriodInfo['return_exists'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Return Filed
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                In Progress
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif

                <!-- Next Deadline -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Next VAT Deadline</h3>
                        @if($nextDeadline)
                            <div class="text-center">
                                <div class="text-3xl font-bold text-gray-900">
                                    {{ $nextDeadline->format('d M Y') }}
                                </div>
                                <div class="text-sm text-gray-500 mt-2">
                                    {{ $nextDeadline->diffForHumans() }}
                                </div>
                                <div class="mt-4">
                                    @if($nextDeadline->diffInDays() <= 7)
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Urgent
                                        </span>
                                    @elseif($nextDeadline->diffInDays() <= 14)
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Due Soon
                                        </span>
                                    @else
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            On Track
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Unsubmitted Invoices Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Unsubmitted Invoices</h3>
                        <a href="{{ route('invoices.index', ['payment_status' => 'all', 'vat_return' => 'unassigned']) }}" 
                           class="text-sm text-indigo-600 hover:text-indigo-900">View All →</a>
                    </div>
                    
                    @if($unsubmittedSummary['total_count'] > 0)
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500">Total Invoices</div>
                                <div class="text-2xl font-bold text-gray-900">{{ number_format($unsubmittedSummary['total_count']) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500">Total Amount</div>
                                <div class="text-2xl font-bold text-gray-900">€{{ number_format($unsubmittedSummary['total_amount'], 2) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500">Total VAT</div>
                                <div class="text-2xl font-bold text-gray-900">€{{ number_format($unsubmittedSummary['total_vat'], 2) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500">Date Range</div>
                                <div class="text-sm font-bold text-gray-900">
                                    @if($unsubmittedSummary['earliest_date'])
                                        {{ $unsubmittedSummary['earliest_date']->format('d M Y') }}<br>
                                        to {{ $unsubmittedSummary['latest_date']->format('d M Y') }}
                                    @else
                                        No invoices
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <!-- Monthly Breakdown -->
                        @if($unsubmittedSummary['monthly_breakdown']->count() > 0)
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Monthly Breakdown</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($unsubmittedSummary['monthly_breakdown'] as $month)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ Carbon\Carbon::create($month->year, $month->month)->format('M Y') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        {{ $month->count }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        €{{ number_format($month->vat_total, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @else
                        <p class="text-gray-500">All invoices have been assigned to VAT returns.</p>
                    @endif
                </div>
            </div>

            <!-- Recent Submissions -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent VAT Submissions</h3>
                        <a href="{{ route('management.vat-dashboard.history') }}" 
                           class="text-sm text-indigo-600 hover:text-indigo-900">View All →</a>
                    </div>
                    
                    @if($recentSubmissions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total VAT</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentSubmissions as $return)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $return->period_start->format('M') }}-{{ $return->period_end->format('M Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($return->status === 'draft')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Draft
                                                    </span>
                                                @elseif($return->status === 'finalized')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Finalized
                                                    </span>
                                                @elseif($return->status === 'submitted')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Submitted
                                                    </span>
                                                @endif
                                                @if($return->is_historical)
                                                    <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        Historical
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                {{ $return->invoices_count ?? $return->invoices()->count() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                €{{ number_format($return->total_vat, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $return->submitted_date ? $return->submitted_date->format('d M Y') : $return->created_at->format('d M Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('management.vat-returns.show', $return) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">No VAT returns have been submitted yet.</p>
                    @endif
                </div>
            </div>

            <!-- Yearly Statistics -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Yearly Statistics</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($yearlyStats as $year => $stats)
                            <div class="border rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-3">{{ $year }}</h4>
                                <dl class="space-y-2">
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500">Returns Filed:</dt>
                                        <dd class="text-sm font-medium text-gray-900">{{ $stats['return_count'] }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500">Total Net:</dt>
                                        <dd class="text-sm font-medium text-gray-900">€{{ number_format($stats['total_net'], 2) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500">Total VAT:</dt>
                                        <dd class="text-sm font-medium text-gray-900">€{{ number_format($stats['total_vat'], 2) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500">Total Gross:</dt>
                                        <dd class="text-sm font-medium text-gray-900">€{{ number_format($stats['total_gross'], 2) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>