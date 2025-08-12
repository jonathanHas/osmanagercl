<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                VAT Returns History
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('management.vat-dashboard.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Back to Dashboard
                </a>
                <a href="{{ route('management.vat-returns.create') }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create VAT Return
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('management.vat-dashboard.history') }}" class="flex gap-4">
                        <div class="flex-1">
                            <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" id="year" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    onchange="this.form.submit()">
                                <option value="">All Years</option>
                                @foreach($availableYears as $year)
                                    <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="flex-1">
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="finalized" {{ request('status') == 'finalized' ? 'selected' : '' }}>Finalized</option>
                                <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            @if(request('year') || request('status'))
                                <a href="{{ route('management.vat-dashboard.history') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Clear Filters
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- VAT Returns Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($vatReturns->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Amount</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($vatReturns as $return)
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
                                                {{ $return->invoices()->count() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                €{{ number_format($return->total_net, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                €{{ number_format($return->total_vat, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                €{{ number_format($return->total_gross, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $return->created_at->format('d M Y') }}
                                                @if($return->creator)
                                                    <br><span class="text-xs">by {{ $return->creator->name }}</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($return->submitted_date)
                                                    {{ $return->submitted_date->format('d M Y') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex justify-end space-x-2">
                                                    <a href="{{ route('management.vat-returns.show', $return) }}" 
                                                       class="text-indigo-600 hover:text-indigo-900">View</a>
                                                    @if($return->status === 'finalized' || $return->status === 'submitted')
                                                        <a href="{{ route('management.vat-returns.export', $return) }}" 
                                                           class="text-green-600 hover:text-green-900">Export</a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $vatReturns->links() }}
                        </div>

                        <!-- Summary Statistics -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Summary Statistics</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-sm font-medium text-gray-500">Total Returns</div>
                                    <div class="text-2xl font-bold text-gray-900">{{ $vatReturns->total() }}</div>
                                </div>
                                @php
                                    $pageStats = $vatReturns->reduce(function ($carry, $return) {
                                        $carry['net'] += $return->total_net;
                                        $carry['vat'] += $return->total_vat;
                                        $carry['gross'] += $return->total_gross;
                                        return $carry;
                                    }, ['net' => 0, 'vat' => 0, 'gross' => 0]);
                                @endphp
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-sm font-medium text-gray-500">Page Total Net</div>
                                    <div class="text-2xl font-bold text-gray-900">€{{ number_format($pageStats['net'], 2) }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-sm font-medium text-gray-500">Page Total VAT</div>
                                    <div class="text-2xl font-bold text-gray-900">€{{ number_format($pageStats['vat'], 2) }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-sm font-medium text-gray-500">Page Total Gross</div>
                                    <div class="text-2xl font-bold text-gray-900">€{{ number_format($pageStats['gross'], 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500">No VAT returns found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>