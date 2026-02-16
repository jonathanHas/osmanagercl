<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">RTD Submissions</h2>
                <p class="text-gray-400 text-sm mt-1">Track invoices submitted to Revenue as part of RTD filings</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('rtd.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to RTD
                </a>
                <a href="{{ route('rtd.submissions.create') }}"
                   class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Submission
                </a>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-green-900/50 border border-green-500 text-green-300 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm">Total Submissions</div>
                <div class="text-2xl font-bold text-white">{{ $submissions->count() }}</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm">Draft</div>
                <div class="text-2xl font-bold text-yellow-400">{{ $submissions->where('status', 'draft')->count() }}</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm">Unsubmitted Frozen Invoices</div>
                <div class="text-2xl font-bold text-orange-400">{{ $unsubmittedCount }}</div>
            </div>
        </div>

        {{-- Submissions Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            @if($submissions->isEmpty())
                <div class="p-12 text-center">
                    <svg class="w-12 h-12 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-gray-400 text-lg">No submissions yet</p>
                    <p class="text-gray-500 text-sm mt-1">Create your first submission to start tracking RTD filings with Revenue.</p>
                    <a href="{{ route('rtd.submissions.create') }}"
                       class="inline-flex items-center mt-4 bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Submission
                    </a>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Period</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Invoices</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Goods (T1)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Service (T2)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Submitted</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Reference</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($submissions as $submission)
                            @php
                                $totals = $submission->totals_snapshot;
                            @endphp
                            <tr class="hover:bg-gray-750">
                                <td class="px-4 py-3 text-gray-200">
                                    {{ $submission->period_start->format('d/m/Y') }} - {{ $submission->period_end->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($submission->isSubmitted())
                                        <span class="px-2 py-0.5 rounded text-xs bg-green-900 text-green-300">Submitted</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-yellow-900 text-yellow-300">Draft</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ $submission->invoices_count }}</td>
                                <td class="px-4 py-3 text-right text-green-400 font-mono">{{ number_format($totals['goods_total'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-yellow-400 font-mono">{{ number_format($totals['service_total'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-gray-300">
                                    {{ $submission->submitted_date?->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-300 font-mono text-sm">
                                    {{ $submission->reference_number ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center flex items-center justify-center gap-2">
                                    <a href="{{ route('rtd.submissions.show', $submission) }}"
                                       class="text-blue-400 hover:text-blue-300 text-sm">
                                        View
                                    </a>
                                    @if($submission->isDraft())
                                        <form action="{{ route('rtd.submissions.destroy', $submission) }}" method="POST"
                                              onsubmit="return confirm('Delete this draft submission? Its invoices will be released back for new submissions.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin-layout>
