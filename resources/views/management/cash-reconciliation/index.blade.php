<x-admin-layout>
    @push('head')
        {{-- The Shop bundle, evaluated before app.js starts Alpine, so the count form
             below is the same component the Shop screen uses. --}}
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@500;600;700;800&display=swap">
        @vite(['resources/css/shop.css', 'resources/js/shop.js'])
    @endpush

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Till count</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">End-of-day cash reconciliation. Staff count on the Shop tablet; this page shows the same form with the history.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('manager'))
                    <a href="{{ route('management.cash-lodgements.index') }}"
                       class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">Lodgements</a>
                    @if ($session)
                        <form method="POST" action="{{ route('cash-reconciliation.sync') }}"
                              onsubmit="return confirm('Replace this count with the old system\'s figures for this till close?');">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="till_id" value="{{ $till->id }}">
                            <input type="hidden" name="session" value="{{ $session->MONEY }}">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-md"
                                    title="For days before the new count went live: re-import the old system's count for this close">Import from old system</button>
                        </form>
                    @endif
                @endif
                @if (auth()->user()?->can('cash_reconciliation.export'))
                    <form method="GET" action="{{ route('cash-reconciliation.export') }}" class="flex items-center gap-1">
                        <input type="date" name="start_date" value="{{ $date->copy()->subDays(30)->toDateString() }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <input type="date" name="end_date" value="{{ $date->toDateString() }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-md">CSV</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            <div class="shop shop--embed">
                <div class="shop-page shop-page--wide" x-data="shopTillCount()"
                     data-state="{{ json_encode($clientState) }}"
                     data-identify-url="{{ route('till-count.identify') }}"
                     data-forget-url="{{ route('till-count.forget') }}"
                     data-store-url="{{ route('till-count.store') }}"
                     x-on:keydown.window="onKey($event)">
                    @include('shop.partials.till-count', ['navRoute' => 'cash-reconciliation.index', 'doneUrl' => route('cash-reconciliation.index')])
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-4 py-3 border-b dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Recent counts</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Variance: green under €{{ number_format(config('till_count.variance_ok'), 0) }}, amber under €{{ number_format(config('till_count.variance_warn'), 0) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Till</th>
                                <th class="px-4 py-2">Counted by</th>
                                <th class="px-4 py-2 text-right">Counted</th>
                                <th class="px-4 py-2 text-right">Float</th>
                                <th class="px-4 py-2 text-right">Bag</th>
                                <th class="px-4 py-2 text-right">Cash variance</th>
                                <th class="px-4 py-2 text-right">Card variance</th>
                                <th class="px-4 py-2">Bag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                            @forelse ($history as $row)
                                @php
                                    $v = (float) $row->variance;
                                    $cv = (float) $row->card - (float) $row->pos_card_total;
                                    $toneClass = fn ($x) => abs($x) < config('till_count.variance_ok') ? 'text-green-600 dark:text-green-400' : (abs($x) < config('till_count.variance_warn') ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400');
                                @endphp
                                <tr class="{{ $session && $row->closed_cash_id === $session->MONEY ? 'bg-indigo-50 dark:bg-indigo-900/20' : '' }}">
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <a href="{{ route('cash-reconciliation.index', ['till' => $row->till_id, 'date' => $row->date->toDateString(), 'session' => $row->closed_cash_id]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->date->format('D j M') }}</a>
                                    </td>
                                    <td class="px-4 py-2">{{ $row->till_name }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row->counted_at)
                                            {{ $row->counter?->name ?? 'Old system' }}
                                            <span class="text-xs text-gray-400">{{ $row->counted_at->format('H:i') }}</span>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400">Not counted</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">€{{ number_format((float) $row->total_cash_counted, 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono">€{{ number_format((float) $row->total_float, 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono">€{{ number_format($row->calculateAvailableToLodge(), 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono {{ $toneClass($v) }}">{{ $v > 0 ? '+' : '' }}{{ number_format($v, 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono {{ (float) $row->card > 0 ? $toneClass($cv) : 'text-gray-400' }}">{{ (float) $row->card > 0 ? (($cv > 0 ? '+' : '').number_format($cv, 2)) : '–' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row->bagVerification)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Verified</span>
                                        @elseif ($row->counted_at)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">No counts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
