<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Harvest Log') }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('fruit-veg.harvest.history') }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800">View history &rarr;</a>
                <a href="{{ route('fruit-veg.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to F&amp;V</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Date selector -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Harvest date</h3>
                </div>
                <form method="GET" action="{{ route('fruit-veg.harvest') }}" class="p-6 flex items-end gap-4">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="date" name="date" value="{{ $selectedDate }}"
                               max="{{ now()->toDateString() }}"
                               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800 transition">
                        Load
                    </button>
                </form>
            </div>

            <!-- Harvest entry -->
            <form method="POST" action="{{ route('fruit-veg.harvest.store') }}"
                  x-data="harvestForm({ available: {{ Js::from($availableProducts) }} })">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            What was harvested on {{ \Carbon\Carbon::parse($selectedDate)->format('D j M Y') }}?
                        </h3>
                        <span class="text-sm text-gray-500">Supplier: Jon (own farm)</span>
                    </div>

                    <!-- Add product search -->
                    <div class="px-6 py-4 border-b border-gray-200 relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Add a product</label>
                        <input type="text" x-model="search" @focus="open = true" @click.away="open = false"
                               placeholder="Search Jon's products to add a row..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <ul x-show="open && filtered.length > 0" x-cloak
                            class="absolute z-10 mt-1 w-[calc(100%-3rem)] max-h-64 overflow-auto bg-white border border-gray-200 rounded-md shadow-lg">
                            <template x-for="p in filtered" :key="p.code">
                                <li @click="addRow(p)"
                                    class="px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 cursor-pointer flex justify-between">
                                    <span x-text="p.name"></span>
                                    <span class="text-gray-400" x-text="p.unit"></span>
                                </li>
                            </template>
                        </ul>
                        <p x-show="open && search.length > 0 && filtered.length === 0" x-cloak
                           class="mt-1 text-sm text-gray-400">No matching products.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-72">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>

                            <!-- Recently harvested / already-entered rows (server-rendered) -->
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($recentRows as $row)
                                    <tr>
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900">
                                            {!! $row['name'] !!}
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <input type="number" step="0.01" min="0"
                                                       name="items[{{ $row['code'] }}]"
                                                       value="{{ old('items.'.$row['code'], $row['quantity']) }}"
                                                       class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @php $rowUnit = old('units.'.$row['code'], $row['unit']); @endphp
                                                <label class="inline-flex items-center text-sm text-gray-600">
                                                    <input type="radio" name="units[{{ $row['code'] }}]" value="kg"
                                                           @checked($rowUnit === 'kg') class="mr-1 text-indigo-600 focus:ring-indigo-500"> kg
                                                </label>
                                                <label class="inline-flex items-center text-sm text-gray-600">
                                                    <input type="radio" name="units[{{ $row['code'] }}]" value="unit"
                                                           @checked($rowUnit === 'unit') class="mr-1 text-indigo-600 focus:ring-indigo-500"> unit
                                                </label>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="text" name="notes[{{ $row['code'] }}]"
                                                   value="{{ old('notes.'.$row['code']) }}"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                    </tr>
                                @empty
                                    <tr x-show="addedRows.length === 0">
                                        <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-400">
                                            Nothing harvested recently. Use the search above to add products.
                                        </td>
                                    </tr>
                                @endforelse

                                <!-- Newly added rows (client-side) -->
                                <template x-for="row in addedRows" :key="row.code">
                                    <tr>
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900" x-html="row.name"></td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <input type="number" step="0.01" min="0"
                                                       :name="`items[${row.code}]`" x-model="row.quantity"
                                                       class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <label class="inline-flex items-center text-sm text-gray-600">
                                                    <input type="radio" :name="`units[${row.code}]`" value="kg"
                                                           x-model="row.unit" class="mr-1 text-indigo-600 focus:ring-indigo-500"> kg
                                                </label>
                                                <label class="inline-flex items-center text-sm text-gray-600">
                                                    <input type="radio" :name="`units[${row.code}]`" value="unit"
                                                           x-model="row.unit" class="mr-1 text-indigo-600 focus:ring-indigo-500"> unit
                                                </label>
                                                <button type="button" @click="removeRow(row.code)"
                                                        class="text-gray-400 hover:text-red-600" title="Remove row">&times;</button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="text" :name="`notes[${row.code}]`" x-model="row.notes"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button type="submit"
                                class="px-5 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700 transition">
                            Save harvest log
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function harvestForm({ available }) {
            return {
                search: '',
                open: false,
                available,          // [{ code, name, category, unit }]
                addedRows: [],
                get filtered() {
                    const q = this.search.toLowerCase();
                    const taken = new Set(this.addedRows.map(r => r.code));
                    return this.available
                        .filter(p => !taken.has(p.code))
                        .filter(p => p.name.toLowerCase().includes(q) || p.code.includes(q))
                        .slice(0, 25);
                },
                addRow(p) {
                    this.addedRows.push({ ...p, quantity: '', notes: '' });
                    this.search = '';
                    this.open = false;
                },
                removeRow(code) {
                    this.addedRows = this.addedRows.filter(r => r.code !== code);
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
