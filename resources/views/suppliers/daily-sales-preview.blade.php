<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Daily Sales Email Preview</h2>
                <p class="text-gray-400 mt-1">See exactly what opted-in suppliers would receive — nothing is sent.</p>
            </div>
            <a href="{{ route('suppliers.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back to Suppliers
            </a>
        </div>

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="bg-green-800/40 border border-green-600 text-green-200 rounded-lg p-3 mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-800/40 border border-red-600 text-red-200 rounded-lg p-3 mb-4 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Date controls --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <form method="GET" action="{{ route('suppliers.daily-sales-preview') }}" class="flex items-end gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Report date</label>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
                               class="bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                    </div>
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-sm">
                        View
                    </button>
                </form>

                <form method="POST" action="{{ route('suppliers.daily-sales-preview.refresh') }}"
                      onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerText='Refreshing…';">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                    <button type="submit"
                            class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-4 rounded text-sm">
                        <i class="fas fa-sync mr-2"></i>Refresh sales for this date
                    </button>
                </form>
            </div>
            <p class="text-gray-500 text-xs mt-3">
                Today's sales are only complete after the 20:00 import. Use <strong>Refresh</strong> to pull the latest
                figures now (may take a few seconds), or pick a past date. Showing
                <strong>{{ $date->format('D j M Y') }}</strong>.
            </p>
        </div>

        {{-- Supplier list --}}
        @if ($rows->isEmpty())
            <div class="bg-gray-800 rounded-lg p-8 text-center">
                <p class="text-gray-300 mb-2">No suppliers are opted in for the daily sales email.</p>
                <p class="text-gray-500 text-sm">
                    Open a POS-linked supplier's edit page and tick
                    <em>"Email this supplier a same-day sales report each evening"</em> (a valid email is required).
                </p>
            </div>
        @else
            <div class="bg-gray-800 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Products</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Units</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Sales (ex-VAT)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($rows as $row)
                            @php($supplier = $row['supplier'])
                            <tr class="hover:bg-gray-700/40">
                                <td class="px-4 py-3 text-sm text-gray-100 font-medium">
                                    {{ $supplier->name }}
                                    @unless ($supplier->include_sales_values)
                                        <span class="inline-block bg-gray-700 text-amber-300 rounded px-2 py-0.5 text-xs ml-1"
                                              title="This supplier's email hides all € values">€ hidden from supplier</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-400">{{ $supplier->email }}</td>
                                @if ($row['has_sales'])
                                    <td class="px-4 py-3 text-sm text-gray-300 text-right">{{ $row['totals']['lines'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-300 text-right">
                                        {{ rtrim(rtrim(number_format($row['totals']['units'], 2), '0'), '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-100 text-right">€{{ number_format($row['totals']['revenue'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                        <a href="{{ route('suppliers.daily-sales-preview.show', ['supplier' => $supplier, 'date' => $date->format('Y-m-d')]) }}"
                                           target="_blank"
                                           class="text-indigo-400 hover:text-indigo-300 font-medium mr-4">
                                            <i class="fas fa-eye mr-1"></i>Preview email
                                        </a>
                                        <a href="{{ route('suppliers.daily-sales-preview.csv', ['supplier' => $supplier, 'date' => $date->format('Y-m-d')]) }}"
                                           class="text-green-400 hover:text-green-300 font-medium mr-4">
                                            <i class="fas fa-file-csv mr-1"></i>CSV
                                        </a>
                                        <form method="POST" action="{{ route('suppliers.daily-sales-preview.send', $supplier) }}" class="inline"
                                              onsubmit="if (!confirm('This will email {{ $supplier->email }} the {{ $date->format('D j M Y') }} report. Send now?')) return false; setTimeout(() => this.querySelector('button').disabled = true, 0);">
                                            @csrf
                                            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                                            <button type="submit" class="text-red-400 hover:text-red-300 font-medium">
                                                <i class="fas fa-paper-plane mr-1"></i>Send
                                            </button>
                                        </form>
                                    </td>
                                @else
                                    <td colspan="3" class="px-4 py-3 text-sm text-gray-500 text-right">
                                        <span class="inline-block bg-gray-700 text-gray-400 rounded px-2 py-1 text-xs">No sales this date</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">—</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-gray-500 text-xs mt-3">
                "Preview email" opens the exact HTML email in a new tab. Suppliers with no sales on the selected date are
                skipped by the nightly job (no email sent).
            </p>
        @endif
    </div>
</x-admin-layout>
