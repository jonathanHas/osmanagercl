<x-admin-layout>
    <div class="p-6">
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <a href="{{ route('management.electricity.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Electricity</a>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Electricity bills</h1>
            </div>
            <form method="POST" action="{{ route('management.electricity.bills.import-from-invoices') }}">
                @csrf
                <button class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Reads the PDFs already attached to utility suppliers' invoices in the accounts">
                    Load from accounts invoices
                </button>
            </form>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg">{{ session('error') }}</div>
        @endif
        @if(session('problems'))
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg text-sm">
                <p class="font-medium mb-1">Not loaded:</p>
                <ul class="list-disc ml-5">@foreach(session('problems') as $problem)<li>{{ $problem }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Upload bills</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                For bills that are not in the accounts. Bills uploaded as invoices are picked up by themselves. Several PDFs can be chosen at once.
            </p>
            <form action="{{ route('management.electricity.bills.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-start sm:items-end gap-4">
                @csrf
                <div class="flex-1">
                    <label for="bill-files" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bill PDFs (up to 32 MB each)</label>
                    <input id="bill-files" type="file" name="files[]" accept="application/pdf,.pdf" multiple required
                           class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
                    @error('files')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('files.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">Upload</button>
            </form>
        </div>

        <div class="mb-4 flex items-center gap-2 text-sm">
            <a href="{{ route('management.electricity.bills.index') }}"
               class="px-3 py-1 rounded-md border {{ ! $filter ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300' }}">All</a>
            <a href="{{ route('management.electricity.bills.index', ['status' => 'needs_review']) }}"
               class="px-3 py-1 rounded-md border {{ $filter === 'needs_review' ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300' }}">To review ({{ $needsReview }})</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-6 py-3 font-medium">Issued</th>
                        <th class="px-3 py-3 font-medium">Provider</th>
                        <th class="px-3 py-3 font-medium">Bill</th>
                        <th class="px-3 py-3 font-medium">Period</th>
                        <th class="px-3 py-3 font-medium text-right">kWh billed</th>
                        <th class="px-3 py-3 font-medium text-right">Net</th>
                        <th class="px-3 py-3 font-medium text-right">Bill total</th>
                        <th class="px-3 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Accounts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                    @forelse($bills as $bill)
                        @php($failed = $bill->failedChecks())
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-6 py-2 whitespace-nowrap">{{ $bill->issue_date?->format('d/m/Y') ?? '–' }}</td>
                            <td class="px-3 py-2">{{ $bill->provider }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a class="text-blue-600 dark:text-blue-400 hover:underline font-medium" href="{{ route('management.electricity.bills.show', $bill) }}">{{ $bill->bill_number ?: 'No number' }}</a>
                                @if($bill->isCreditNote())<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">credit</span>@endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($bill->period_start && $bill->period_end){{ $bill->period_start->format('d/m/y') }} – {{ $bill->period_end->format('d/m/y') }}@else – @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($bill->import_kwh) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $bill->net_total !== null ? number_format($bill->net_total, 2) : '–' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $bill->gross_total !== null ? number_format($bill->gross_total, 2) : '–' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($bill->isConfirmed())
                                    <span class="text-green-700 dark:text-green-400">✓ Confirmed</span>
                                @else
                                    <span class="text-amber-700 dark:text-amber-300 font-medium">⚠ To review</span>
                                @endif
                                @if($failed)
                                    <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ collect($failed)->pluck('label')->implode('; ') }}">· {{ count($failed) }} {{ Str::plural('note', count($failed)) }}</span>
                                @endif
                                @if($bill->source !== 'parser')<span class="text-xs text-gray-500 dark:text-gray-400">· {{ $bill->source === 'ai' ? 'read by AI' : 'edited' }}</span>@endif
                            </td>
                            <td class="px-6 py-2 whitespace-nowrap">
                                @if($bill->invoice)
                                    <a class="text-blue-600 dark:text-blue-400 hover:underline" href="{{ route('invoices.show', $bill->invoice) }}">Invoice</a>
                                @else
                                    <span class="text-gray-400">uploaded here</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No bills yet. Upload some above, or load them from the accounts invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
