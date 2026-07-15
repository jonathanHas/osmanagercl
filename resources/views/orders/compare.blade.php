{{-- Compares two order sessions, focused on what is absent from each. --}}
{{-- Rows cover only products actually ordered (final_quantity > 0) in that session. --}}
{{-- Expects: $orderA, $orderB (OrderSession), $onlyInA, $onlyInB, $changed, $unchanged --}}
{{-- (Collections of array{a, product, name, supplierCode, stock, ...}), $suppliersDiffer (bool) --}}
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

    $qtyLabel = function ($item) use ($num) {
        $qty = $num($item->final_quantity);

        return $item->isOrderedByCases()
            ? $num($item->final_cases).' cases ('.$qty.' units)'
            : $qty.' units';
    };
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Compare Orders') }}
            </h2>
            <a href="{{ route('orders.index') }}"
               class="inline-flex items-center rounded bg-gray-200 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-300">
                Back to Orders
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($suppliersDiffer)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3 text-sm text-amber-800">
                            <span class="font-medium">These orders are from different suppliers.</span>
                            Their product ranges barely overlap, so almost everything will appear as
                            &ldquo;only in&rdquo; one order. Comparing orders from the same supplier is usually more useful.
                        </div>
                    </div>
                </div>
            @endif

            {{-- Order summary --}}
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="grid grid-cols-1 gap-px bg-gray-200 md:grid-cols-2">
                    @foreach([['label' => 'Order A', 'order' => $orderA], ['label' => 'Order B', 'order' => $orderB]] as $side)
                        @php $o = $side['order']; @endphp
                        <div class="bg-white p-6">
                            <div class="flex items-baseline justify-between">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $side['label'] }}</h3>
                                <a href="{{ route('orders.show', $o) }}" class="text-xs text-indigo-600 hover:underline">
                                    Open order &rarr;
                                </a>
                            </div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">Order #{{ $o->id }}</div>
                            <div class="mt-3 space-y-1.5 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Supplier</span>
                                    <span class="font-medium text-gray-900">{{ $o->supplier->Supplier ?? 'Unknown Supplier' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Delivery date</span>
                                    <span class="text-gray-700">{{ $o->order_date ? $o->order_date->format('M j, Y') : 'Not set' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Created</span>
                                    <span class="text-gray-700">{{ $o->created_at->format('M j, Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Products ordered</span>
                                    <span class="text-gray-700">
                                        {{ $o->items->filter(fn ($i) => (float) $i->final_quantity > 0)->count() }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total value</span>
                                    <span class="font-medium text-gray-900">&euro;{{ number_format($o->total_value, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- The headline: ordered in one, not the other --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach([
                    ['title' => 'Only in Order A', 'subtitle' => 'Not ordered in Order B', 'rows' => $onlyInA, 'order' => $orderA, 'tone' => 'red'],
                    ['title' => 'Only in Order B', 'subtitle' => 'Not ordered in Order A', 'rows' => $onlyInB, 'order' => $orderB, 'tone' => 'green'],
                ] as $panel)
                    @php
                        $tone = $panel['tone'];
                        $headClass = $tone === 'red' ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200';
                        $countClass = $tone === 'red' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
                    @endphp
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b {{ $headClass }} px-6 py-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $panel['title'] }}</h3>
                                    <p class="text-xs text-gray-600">
                                        {{ $panel['subtitle'] }} &mdash; Order #{{ $panel['order']->id }}
                                    </p>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $countClass }}">
                                    {{ $panel['rows']->count() }}
                                </span>
                            </div>
                        </div>

                        @if($panel['rows']->isEmpty())
                            <p class="px-6 py-8 text-center text-sm text-gray-500">
                                Nothing here &mdash; everything ordered here is ordered in the other too.
                            </p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Stock</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Ordered</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($panel['rows'] as $row)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2">
                                                    <div class="text-sm font-medium text-gray-900">{{ $row['name'] }}</div>
                                                    <div class="text-xs text-gray-500">{{ $row['supplierCode'] }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                                    <x-order-compare-stock :stock="$row['stock']" />
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-gray-700">
                                                    {{ $qtyLabel($row['a']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- In both, quantity changed --}}
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">In both, quantity changed</h3>
                            <p class="text-xs text-gray-600">Change from Order A to Order B</p>
                        </div>
                        <span class="inline-flex rounded-full bg-gray-200 px-2 py-1 text-xs font-semibold text-gray-800">
                            {{ $changed->count() }}
                        </span>
                    </div>
                </div>

                @if($changed->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">
                        Every product common to both orders was ordered in the same quantity.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order A</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order B</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Change</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($changed as $row)
                                    @php
                                        $delta = $row['delta'];
                                        $deltaText = $num(abs($delta));
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $row['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['supplierCode'] }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-3 text-right">
                                            <x-order-compare-stock :stock="$row['stock']" />
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-3 text-sm text-gray-700">
                                            {{ $qtyLabel($row['a']) }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-3 text-sm text-gray-700">
                                            {{ $qtyLabel($row['b']) }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-3">
                                            <span class="text-sm font-semibold {{ $delta > 0 ? 'text-green-600' : 'text-orange-600' }}">
                                                {{ $delta > 0 ? '↑' : '↓' }} {{ $deltaText }} units
                                            </span>
                                            @if($row['caseUnitsDiffer'])
                                                <div class="text-xs text-amber-700">
                                                    Case size changed ({{ (int) $row['a']->case_units }} &rarr; {{ (int) $row['b']->case_units }} units/case)
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Identical in both: collapsed, it is the noise against the "what changed" question --}}
            @if($unchanged->isNotEmpty())
                <div class="overflow-hidden rounded-lg bg-white shadow-sm" x-data="{ open: false }">
                    <button type="button"
                            @click="open = !open"
                            class="flex w-full items-center justify-between px-6 py-3 text-left hover:bg-gray-50">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">
                                Identical in both orders
                            </h3>
                            <p class="text-xs text-gray-600">
                                {{ $unchanged->count() }} {{ \Illuminate\Support\Str::plural('product', $unchanged->count()) }}
                                ordered at the same quantity
                            </p>
                        </div>
                        <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-transform"
                             :class="open && 'rotate-180'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak class="border-t border-gray-200">
                        <ul class="divide-y divide-gray-200">
                            @foreach($unchanged as $row)
                                <li class="flex items-center justify-between px-6 py-2">
                                    <div class="min-w-0 pr-4">
                                        <span class="text-sm text-gray-900">{{ $row['name'] }}</span>
                                        <span class="ml-2 text-xs text-gray-500">{{ $row['supplierCode'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-4 whitespace-nowrap">
                                        <span class="text-xs text-gray-500">
                                            stock <x-order-compare-stock :stock="$row['stock']" />
                                        </span>
                                        <span class="text-sm text-gray-500">{{ $qtyLabel($row['a']) }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-admin-layout>
