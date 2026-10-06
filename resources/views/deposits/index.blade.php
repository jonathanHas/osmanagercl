<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Bottle deposits') }}
            </h2>
            <a href="{{ route('barrel-codes.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                &larr; Back to Barrel Codes
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4 text-sm text-amber-800 dark:text-amber-200">
                Udea prints a deposit code on delivery-note lines for bottles and jars. Switch a code on to charge it to
                customers, then confirm the products below. A confirmed product gets the deposit on the till: scanning it
                adds a "Bottle deposit" line underneath, at cost and zero-rated. Refunds are the "Bottle deposit refund"
                buttons in the till's Bottle Deposits category.
            </div>

            {{-- 1. Deposit tiers --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">Deposit tiers</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Charge customers</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Till products</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Confirmed products</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($tiers as $tier)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $tier->supplier_code }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $tier->name ?: $tier->description }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">€{{ number_format((float) $tier->unit_price, 2) }}</td>
                                    <td class="px-4 py-2">
                                        <form method="POST" action="{{ route('deposits.tiers.update', $tier) }}" x-data>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="charge_customer" value="0">
                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="charge_customer" value="1" @checked($tier->charge_customer)
                                                       x-on:change="$el.form.submit()"
                                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-gray-700 dark:text-gray-300">{{ $tier->charge_customer ? 'On' : 'Off' }}</span>
                                            </label>
                                        </form>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">
                                        @if ($tier->pos_product_id && isset($products[$tier->pos_product_id]))
                                            {{ $products[$tier->pos_product_id]->CODE }}
                                            @if ($tier->pos_refund_product_id && isset($products[$tier->pos_refund_product_id]))
                                                / {{ $products[$tier->pos_refund_product_id]->CODE }}
                                            @endif
                                        @else
                                            <span class="font-sans text-gray-400">not created yet</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ $tier->confirmed_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 2. Products --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">Products</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="GET" action="{{ route('deposits.index') }}" x-data>
                            @php $statusValue = implode(',', $statuses); @endphp
                            <select name="status" x-on:change="$el.form.submit()"
                                    class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach ([
                                    'suggested,confirmed' => 'Suggested + confirmed',
                                    'suggested' => 'Suggested',
                                    'confirmed' => 'Confirmed',
                                    'rejected' => 'Rejected',
                                    'suggested,confirmed,rejected' => 'All',
                                ] as $value => $label)
                                    <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('deposits.products.confirm-all') }}">
                            @csrf
                            <button type="submit" @disabled($suggestedCount === 0)
                                    class="px-3 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
                                Confirm all suggested ({{ $suggestedCount }})
                            </button>
                        </form>
                        <form method="POST" action="{{ route('deposits.refresh') }}">
                            @csrf
                            <button type="submit" class="px-3 py-2 text-sm bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500">
                                Recompute suggestions
                            </button>
                        </form>
                        <form method="POST" action="{{ route('deposits.sync') }}">
                            @csrf
                            <button type="submit" class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Sync till now
                            </button>
                        </form>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Barcode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Udea code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Evidence</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Till</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($rows as $row)
                                @php
                                    $product = $products[$row->product_id] ?? null;
                                    $detail = $details[$row->id] ?? ['udea_code' => null, 'others' => []];
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        @if ($product)
                                            <a href="{{ route('products.edit', $product->ID) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $product->NAME }}</a>
                                        @else
                                            <span class="text-gray-500">{{ $row->product_code ?? $row->product_id }} (not on till)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $product?->CODE ?? $row->product_code }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $detail['udea_code'] ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <form method="POST" action="{{ route('deposits.products.update', $row) }}" x-data>
                                            @csrf
                                            @method('PATCH')
                                            <select name="barrel_code_id" x-on:change="$el.form.submit()"
                                                    class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                                @if (! $chargeTiers->contains('id', $row->barrel_code_id))
                                                    <option value="{{ $row->barrel_code_id }}" selected>
                                                        {{ $row->barrelCode?->supplier_code }} €{{ number_format((float) $row->barrelCode?->unit_price, 2) }} (off)
                                                    </option>
                                                @endif
                                                @foreach ($chargeTiers as $tier)
                                                    <option value="{{ $tier->id }}" @selected($tier->id == $row->barrel_code_id)>
                                                        {{ $tier->supplier_code }} €{{ number_format((float) $tier->unit_price, 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-300">
                                        @if ($row->sightings_count > 0)
                                            {{ $row->sightings_units }} {{ Str::plural('unit', $row->sightings_units) }} in {{ $row->sightings_count }} {{ Str::plural('line', $row->sightings_count) }}@if ($row->last_seen_on), last {{ $row->last_seen_on->toDateString() }}@endif
                                        @elseif ($row->isManual())
                                            added by hand
                                        @else
                                            —
                                        @endif
                                        @if ($row->conflicting_units > 0)
                                            <div class="text-red-600 dark:text-red-400">
                                                @forelse ($detail['others'] as $code => $units)
                                                    also seen as {{ $code }} ({{ $units }} {{ Str::plural('unit', $units) }})@if (! $loop->last), @endif
                                                @empty
                                                    also seen under another code ({{ $row->conflicting_units }} units)
                                                @endforelse
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @php
                                            $pill = [
                                                'suggested' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                'rejected' => 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
                                            ][$row->status] ?? 'bg-gray-100 text-gray-700';
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $pill }}">{{ $row->status }}</span>
                                        @if ($row->isManual())
                                            <span class="ml-1 text-xs text-gray-500">manual</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        @if ($row->pos_sync_error)
                                            <span class="text-red-600 dark:text-red-400">error: {{ $row->pos_sync_error }}</span>
                                        @elseif ($row->pos_synced_at)
                                            <span class="text-gray-600 dark:text-gray-400" title="{{ $row->pos_synced_at }}">synced {{ $row->pos_synced_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-gray-500">pending</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <div class="inline-flex gap-1">
                                            @if ($row->status !== 'confirmed')
                                                <form method="POST" action="{{ route('deposits.products.update', $row) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">Confirm</button>
                                                </form>
                                            @endif
                                            @if ($row->status !== 'rejected')
                                                <form method="POST" action="{{ route('deposits.products.update', $row) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="px-2 py-1 text-xs bg-gray-500 text-white rounded hover:bg-gray-600">Reject</button>
                                                </form>
                                            @endif
                                            @if ($row->isManual())
                                                <form method="POST" action="{{ route('deposits.products.destroy', $row) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                        No products here. Switch a tier on and recompute suggestions, or add one below.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 3. Add a product --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">Add a product</h3>
                </div>
                <div class="p-6">
                    @if ($chargeTiers->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">Switch a deposit tier on first.</p>
                    @else
                        <form method="POST" action="{{ route('deposits.products.store') }}" class="flex flex-wrap items-start gap-4"
                              x-data="{ picked: null }"
                              x-on:product-search:selected="picked = $event.detail"
                              x-on:product-search:cleared="picked = null">
                            @csrf
                            <div class="flex-1 min-w-[280px]">
                                <x-product-search mode="picker" name="product_id" :sync-url="false" :autofocus="$rows->isEmpty()" placeholder="Scan or type a barcode, or part of the name…" />
                                {{-- The picker clears its own box after a pick, so say what was chosen. --}}
                                <p x-show="picked" x-cloak class="mt-2 text-sm text-gray-900 dark:text-gray-100">
                                    Selected: <span class="font-medium" x-text="picked?.name"></span>
                                    (<span class="font-mono" x-text="picked?.code"></span>)
                                </p>
                                <p x-show="!picked" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Scan or type a barcode, or type part of the name, then choose a result; Enter selects the top match.
                                </p>
                            </div>
                            <select name="barrel_code_id"
                                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach ($chargeTiers as $tier)
                                    <option value="{{ $tier->id }}">{{ $tier->supplier_code }} — €{{ number_format((float) $tier->unit_price, 2) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" x-bind:disabled="!picked"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">Add</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
