<x-admin-layout>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Cash Lodgements</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Verify bags, create lodgements, track deposits</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('cash-reconciliation.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    Cash Reconciliation
                </a>
                <a href="{{ route('management.cash-lodgements.export', request()->query()) }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded">
        {{ session('error') }}
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SECTION 1: PENDING BAGS (need counting)                       --}}
    {{-- ============================================================ --}}
    @if($pendingBags->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6" x-data="bagCounter()">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Pending Bags</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $pendingBags->count() }} bag(s) waiting to be counted</p>
                </div>
                <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">€{{ number_format($pendingBags->sum(fn($r) => $r->calculateAvailableToLodge()), 2) }}</span>
            </div>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($pendingBags as $bag)
            @php $avail = $bag->calculateAvailableToLodge(); @endphp
            <div class="px-6 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $bag->date->format('D, M j') }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $bag->till_name }}</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">€{{ number_format($avail, 2) }}</span>
                        <button type="button"
                                @click="openBag('{{ $bag->id }}', '{{ $bag->date->format('D, M j') }}', '{{ $bag->till_name }}', {{ $avail }})"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm font-medium">
                            Count Bag
                        </button>
                    </div>
                </div>

                {{-- Inline counting form (shown when this bag is selected) --}}
                <div x-show="activeBagId === '{{ $bag->id }}'" x-collapse class="mt-4">
                    <form method="POST" action="{{ route('management.cash-lodgements.verify-bag') }}">
                        @csrf
                        <input type="hidden" name="cash_reconciliation_id" value="{{ $bag->id }}">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- Denomination inputs --}}
                            <div class="space-y-2">
                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</h4>
                                @foreach([50, 20, 10, 5] as $d)
                                <div class="flex items-center justify-between">
                                    <label class="text-sm text-gray-700 dark:text-gray-300 w-10">€{{ $d }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $d }}" min="0" value="0"
                                               x-model.number="denominations.cash_{{ $d }}" @input="calculate()"
                                               class="w-16 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-xs text-gray-400 w-14 text-right">€<span x-text="(denominations.cash_{{ $d }} * {{ $d }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach

                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide pt-2">Coins</h4>
                                @foreach([2, 1] as $d)
                                <div class="flex items-center justify-between">
                                    <label class="text-sm text-gray-700 dark:text-gray-300 w-10">€{{ $d }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $d }}" min="0" value="0"
                                               x-model.number="denominations.cash_{{ $d }}" @input="calculate()"
                                               class="w-16 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-xs text-gray-400 w-14 text-right">€<span x-text="(denominations.cash_{{ $d }} * {{ $d }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                                @foreach(['50c', '20c', '10c'] as $d)
                                @php $m = $d == '50c' ? 0.5 : ($d == '20c' ? 0.2 : 0.1); @endphp
                                <div class="flex items-center justify-between">
                                    <label class="text-sm text-gray-700 dark:text-gray-300 w-10">{{ $d }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $d }}" min="0" value="0"
                                               x-model.number="denominations.cash_{{ $d }}" @input="calculate()"
                                               class="w-16 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-xs text-gray-400 w-14 text-right">€<span x-text="(denominations.cash_{{ $d }} * {{ $m }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            {{-- Summary --}}
                            <div class="md:col-span-2">
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Counted Total</span>
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">€<span x-text="countedTotal.toFixed(2)"></span></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Expected (from recon)</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">€<span x-text="expectedTotal.toFixed(2)"></span></span>
                                    </div>
                                    <div class="border-t dark:border-gray-700 pt-2 flex justify-between">
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Variance</span>
                                        <span class="text-xl font-bold"
                                              :class="bagVariance > 0.01 ? 'text-green-600 dark:text-green-400' : (bagVariance < -0.01 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400')">
                                            <span x-show="Math.abs(bagVariance) < 0.01">Exact match</span>
                                            <span x-show="Math.abs(bagVariance) >= 0.01">
                                                €<span x-text="Math.abs(bagVariance).toFixed(2)"></span>
                                                <span x-text="bagVariance > 0 ? '↑ over' : '↓ under'"></span>
                                            </span>
                                        </span>
                                    </div>

                                    <div class="flex space-x-3 pt-2">
                                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">
                                            Confirm Bag Count
                                        </button>
                                        <button type="button" @click="closeBag()" class="bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SECTION 2: VERIFIED BAGS (ready to lodge)                     --}}
    {{-- ============================================================ --}}
    @if($verifiedBags->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Verified Bags — Ready to Lodge</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Select bags to include in a bank lodgement</p>
                </div>
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">€{{ number_format($verifiedBags->sum('counted_total'), 2) }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('management.cash-lodgements.create-lodgement') }}" x-data="{ selected: [], selectAll: false }">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <input type="checkbox" x-model="selectAll"
                                       @change="selected = selectAll ? @json($verifiedBags->pluck('id')) : []"
                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Till</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Counted</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Expected</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variance</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Verified By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($verifiedBags as $v)
                        @php
                            $absVar = abs($v->variance);
                            $varClass = $absVar < 1 ? 'text-green-600 dark:text-green-400' : ($absVar > 20 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400');
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2">
                                <input type="checkbox" name="verification_ids[]" value="{{ $v->id }}"
                                       x-model="selected"
                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $v->reconciliation->date->format('D, M j') }}</td>
                            <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $v->reconciliation->till_name }}</td>
                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-white">€{{ number_format($v->counted_total, 2) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-500 dark:text-gray-400">€{{ number_format($v->expected_total, 2) }}</td>
                            <td class="px-4 py-2 text-sm text-right">
                                <span class="{{ $varClass }} font-medium">
                                    @if($absVar < 0.01) &check; @else €{{ number_format($absVar, 2) }} {{ $v->variance > 0 ? '↑' : '↓' }} @endif
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $v->verifier->name ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    <span x-text="selected.length"></span> bag(s) selected
                </span>
                <button type="submit" x-bind:disabled="selected.length === 0"
                        class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-6 rounded-md">
                    Create Lodgement
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SECTION 3: LODGEMENTS (existing table with filters)           --}}
    {{-- ============================================================ --}}

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <form method="GET" action="{{ route('management.cash-lodgements.index') }}" class="p-4">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                    <label for="start_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                    <input type="date" name="start_date" id="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                    <input type="date" name="end_date" id="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="till" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Till</label>
                    <select name="till" id="till" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All</option>
                        @foreach($tills as $till)
                        <option value="{{ $till }}" {{ request('till') == $till ? 'selected' : '' }}>{{ $till }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All</option>
                        <option value="matched" {{ request('status') == 'matched' ? 'selected' : '' }}>Matched</option>
                        <option value="unmatched" {{ request('status') == 'unmatched' ? 'selected' : '' }}>Unmatched</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md text-sm font-medium">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Lodgements Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Lodgements</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Till</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($lodgements as $lodgement)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $lodgement->lodgement_date->format('D, M j, Y') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $lodgement->till_name ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">€{{ number_format($lodgement->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($lodgement->imported_from_legacy)
                            <span class="text-xs text-gray-500 dark:text-gray-400">Legacy</span>
                            @else
                            <span class="text-xs text-indigo-600 dark:text-indigo-400">New</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            <a href="{{ route('management.cash-lodgements.show', $lodgement) }}"
                               class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No lodgements found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($lodgements->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $lodgements->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function bagCounter() {
        return {
            activeBagId: null,
            expectedTotal: 0,
            countedTotal: 0,
            bagVariance: 0,
            denominations: {
                cash_50: 0, cash_20: 0, cash_10: 0, cash_5: 0,
                cash_2: 0, cash_1: 0, cash_50c: 0, cash_20c: 0, cash_10c: 0
            },

            openBag(id, date, till, expected) {
                this.activeBagId = id;
                this.expectedTotal = expected;
                this.resetDenominations();
                this.calculate();
            },

            closeBag() {
                this.activeBagId = null;
            },

            resetDenominations() {
                Object.keys(this.denominations).forEach(k => this.denominations[k] = 0);
            },

            calculate() {
                this.countedTotal =
                    (this.denominations.cash_50 * 50) +
                    (this.denominations.cash_20 * 20) +
                    (this.denominations.cash_10 * 10) +
                    (this.denominations.cash_5 * 5) +
                    (this.denominations.cash_2 * 2) +
                    (this.denominations.cash_1 * 1) +
                    (this.denominations.cash_50c * 0.5) +
                    (this.denominations.cash_20c * 0.2) +
                    (this.denominations.cash_10c * 0.1);

                this.bagVariance = this.countedTotal - this.expectedTotal;
            }
        }
    }
</script>
@endpush
</x-admin-layout>
