<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Delivery Legacy - Invoice Match
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Delivery to Match</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Select a supplier and a scan session to compare scanned items against the supplier's invoice data.
                    </p>

                    <form action="{{ route('delivery-legacy.match') }}" method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Supplier Selection -->
                            <div>
                                <label for="supplierID" class="block text-sm font-medium text-gray-700">Supplier</label>
                                <select name="supplierID" id="supplierID" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Select Supplier --</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->SupplierID }}">{{ $supplier->Supplier }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Scan Session ID Selection -->
                            <div>
                                <label for="delID" class="block text-sm font-medium text-gray-700">Scan Session ID</label>
                                <select name="delID" id="delID" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Select Scan Session --</option>
                                    @foreach($scanSessions as $session)
                                        @php
                                            $itemCount = $scanItemCounts[$session->ID] ?? 0;
                                        @endphp
                                        <option value="{{ $session->ID }}" data-supplier="{{ $session->supID }}">
                                            #{{ $session->ID }} - {{ $session->Supplier ?? 'Supplier ' . $session->supID }} - {{ $session->dateUpload }} ({{ $itemCount }} items)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                View Invoice Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Scan Sessions -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Scan Sessions</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($scanSessions as $session)
                                    @php
                                        $itemCount = $scanItemCounts[$session->ID] ?? 0;
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #{{ $session->ID }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $session->Supplier ?? 'Unknown' }}
                                            <span class="text-gray-400 text-xs">(ID: {{ $session->supID }})</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $session->dateUpload }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $itemCount }} items
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($session->status)
                                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                                    {{ $session->status }}
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                                    -
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button type="button"
                                                onclick="selectSession({{ $session->ID }}, '{{ $session->supID }}')"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                Select
                                            </button>
                                            <a href="{{ route('delivery-legacy.match', ['delID' => $session->ID, 'supplierID' => $session->supID]) }}"
                                               class="text-green-600 hover:text-green-900">
                                                View Match
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            No scan sessions found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function selectSession(sessionId, supplierId) {
            document.getElementById('delID').value = sessionId;
            document.getElementById('supplierID').value = supplierId;
        }

        // Filter scan sessions by supplier when supplier is selected
        document.getElementById('supplierID').addEventListener('change', function() {
            const supplierId = this.value;
            const delIdSelect = document.getElementById('delID');
            const options = delIdSelect.querySelectorAll('option[data-supplier]');

            options.forEach(option => {
                if (supplierId === '' || option.dataset.supplier === supplierId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });

            // Reset selection if current selection is hidden
            if (delIdSelect.selectedOptions[0] && delIdSelect.selectedOptions[0].style.display === 'none') {
                delIdSelect.value = '';
            }
        });
    </script>
    @endpush
</x-app-layout>
