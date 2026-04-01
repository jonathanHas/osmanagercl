<x-admin-layout>
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

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Synced Delivery Documents --}}
            @if($syncedDelivery && $syncedDelivery->documents->count() > 0)
                <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-medium text-blue-900 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Synced Delivery Documents
                        </h3>
                        <span class="text-sm text-blue-600">
                            From: {{ $syncedDelivery->supplier->Supplier ?? 'Unknown' }} - {{ $syncedDelivery->delivery_date->format('d/m/Y') }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach($syncedDelivery->documents as $document)
                            <div class="flex items-center bg-white rounded-lg px-3 py-2 shadow-sm border border-blue-100">
                                @if($document->isPdf())
                                    <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                @endif
                                <span class="text-sm text-gray-700 mr-3">{{ $document->original_filename }}</span>
                                <a href="{{ $document->viewer_minimal_url }}"
                                   onclick="window.open(this.href, 'documentViewer', 'width=900,height=700,scrollbars=yes,resizable=yes'); return false;"
                                   class="text-blue-600 hover:text-blue-800 text-sm font-medium mr-2">
                                    View
                                </a>
                                <a href="{{ $document->download_url }}"
                                   class="text-gray-500 hover:text-gray-700 text-sm">
                                    Download
                                </a>
                            </div>
                        @endforeach
                    </div>
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

                    {{-- Create New Scan Session --}}
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-md font-medium text-gray-900 mb-3">Or Create New Scan Session</h4>
                        <form action="{{ route('delivery-legacy.create-session') }}" method="POST" class="flex items-end gap-4">
                            @csrf
                            <div class="flex-1">
                                <label for="newSessionSupplier" class="block text-sm font-medium text-gray-700">Supplier</label>
                                <select name="supplierID" id="newSessionSupplier" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <option value="">-- Select Supplier --</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->SupplierID }}">{{ $supplier->Supplier }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create New Session
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Scan Sessions -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="sessionManager()">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Scan Sessions</h3>
                        <button type="button"
                            x-show="selectedSessions.length === 2"
                            x-cloak
                            @click="showMergeDialog = true"
                            class="inline-flex items-center px-3 py-1.5 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            Merge Selected Sessions
                        </button>
                    </div>

                    {{-- Mobile Card Layout --}}
                    <div class="md:hidden divide-y divide-gray-200">
                        @forelse($scanSessions as $session)
                            @php
                                $itemCount = $scanItemCounts[$session->ID] ?? 0;
                                $isPending = $session->status == 0;
                            @endphp
                            <div class="p-3" x-data="{ editing: false }">
                                <div class="flex items-start justify-between mb-1.5">
                                    <div class="flex items-center gap-2">
                                        @if($isPending)
                                            <input type="checkbox"
                                                value="{{ $session->ID }}"
                                                @change="toggleSession('{{ $session->ID }}', '{{ $session->supID }}', '{{ addslashes($session->Supplier ?? 'Unknown') }}')"
                                                :checked="selectedSessions.includes('{{ $session->ID }}')"
                                                :disabled="selectedSessions.length >= 2 && !selectedSessions.includes('{{ $session->ID }}')"
                                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500 mt-0.5">
                                        @endif
                                        <span class="text-sm font-medium text-gray-900">#{{ Str::limit($session->ID, 8, '...') }}</span>
                                    </div>
                                    @if($session->status)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">Completed</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="min-w-0">
                                        <span x-show="!editing" class="text-sm text-gray-900">
                                            {{ $session->Supplier ?? 'Unknown' }}
                                            @if($isPending)
                                                <button x-show="!editing" @click="editing = true" class="text-gray-400 hover:text-indigo-600 ml-1 inline-block align-middle" title="Change supplier">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </span>
                                        @if($isPending)
                                            <form x-show="editing" x-cloak @click.away="editing = false"
                                                action="{{ route('delivery-legacy.change-supplier') }}" method="POST"
                                                @submit="return confirm('Change supplier from {{ addslashes($session->Supplier ?? 'Unknown') }} to ' + $el.querySelector('select').selectedOptions[0].text + '?')"
                                                class="flex items-center gap-1">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="sessionId" value="{{ $session->ID }}">
                                                <select name="newSupplierID" required class="text-xs rounded border-gray-300 py-1 pr-6 flex-1">
                                                    @foreach($suppliers as $supplier)
                                                        <option value="{{ $supplier->SupplierID }}" @selected($supplier->SupplierID == $session->supID)>
                                                            {{ $supplier->Supplier }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="text-green-600 hover:text-green-800" title="Save">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                                <button type="button" @click="editing = false" class="text-gray-400 hover:text-gray-600" title="Cancel">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-500 ml-2 flex-shrink-0">{{ $session->dateUpload }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mb-2">{{ $itemCount }} items</div>
                                <div class="flex gap-2">
                                    <button type="button"
                                        onclick="selectSession('{{ $session->ID }}', '{{ $session->supID }}')"
                                        class="flex-1 py-2 px-3 text-xs font-semibold text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 touch-manipulation text-center">
                                        Select
                                    </button>
                                    <a href="{{ route('delivery-legacy.match', ['delID' => $session->ID, 'supplierID' => $session->supID]) }}"
                                       class="flex-1 py-2 px-3 text-xs font-semibold text-green-600 bg-green-50 rounded-md hover:bg-green-100 touch-manipulation text-center">
                                        View Match
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-gray-500">No scan sessions found</div>
                        @endforelse
                    </div>

                    {{-- Desktop Table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">Merge</th>
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
                                        $isPending = $session->status == 0;
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm">
                                            @if($isPending)
                                                <input type="checkbox"
                                                    value="{{ $session->ID }}"
                                                    @change="toggleSession('{{ $session->ID }}', '{{ $session->supID }}', '{{ addslashes($session->Supplier ?? 'Unknown') }}')"
                                                    :checked="selectedSessions.includes('{{ $session->ID }}')"
                                                    :disabled="selectedSessions.length >= 2 && !selectedSessions.includes('{{ $session->ID }}')"
                                                    class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #{{ Str::limit($session->ID, 8, '...') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div x-data="{ editing: false }" class="flex items-center gap-1">
                                                <span x-show="!editing">
                                                    {{ $session->Supplier ?? 'Unknown' }}
                                                    <span class="text-gray-400 text-xs">(ID: {{ $session->supID }})</span>
                                                </span>
                                                @if($isPending)
                                                    <button x-show="!editing" @click="editing = true" class="text-gray-400 hover:text-indigo-600 ml-1" title="Change supplier">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </button>
                                                    <form x-show="editing" x-cloak @click.away="editing = false"
                                                        action="{{ route('delivery-legacy.change-supplier') }}" method="POST"
                                                        @submit="return confirm('Change supplier from {{ addslashes($session->Supplier ?? 'Unknown') }} to ' + $el.querySelector('select').selectedOptions[0].text + '?')"
                                                        class="flex items-center gap-1">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="sessionId" value="{{ $session->ID }}">
                                                        <select name="newSupplierID" required class="text-xs rounded border-gray-300 py-1 pr-6">
                                                            @foreach($suppliers as $supplier)
                                                                <option value="{{ $supplier->SupplierID }}" @selected($supplier->SupplierID == $session->supID)>
                                                                    {{ $supplier->Supplier }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="text-green-600 hover:text-green-800" title="Save">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="editing = false" class="text-gray-400 hover:text-gray-600" title="Cancel">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
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
                                                    Completed
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                                    Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button type="button"
                                                onclick="selectSession('{{ $session->ID }}', '{{ $session->supID }}')"
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
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No scan sessions found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-2 text-xs text-gray-500" x-show="selectedSessions.length > 0 && selectedSessions.length < 2">
                        Select one more pending session to merge.
                    </p>
                </div>

                {{-- Merge Sessions Dialog --}}
                <div x-show="showMergeDialog" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @keydown.escape.window="showMergeDialog = false">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6" @click.away="showMergeDialog = false">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Merge Sessions</h3>
                        <p class="text-sm text-gray-600 mb-4">Which session do you want to <strong>keep</strong>? The other session's items will be merged into it, and the other session will be deleted.</p>

                        <template x-if="differentSuppliers">
                            <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 rounded text-sm">
                                These sessions have different suppliers. The kept session's supplier will be used.
                            </div>
                        </template>

                        <div class="space-y-2 mb-4">
                            <template x-for="s in sessionDetails" :key="s.id">
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50" :class="keepSessionId === s.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                                    <input type="radio" name="keepSession" :value="s.id" x-model="keepSessionId" class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-3 text-sm">
                                        <span class="font-medium" x-text="'#' + s.id.substring(0, 8) + '...'"></span>
                                        <span class="text-gray-500" x-text="' - ' + s.supplier"></span>
                                    </span>
                                </label>
                            </template>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button @click="showMergeDialog = false" class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900">Cancel</button>
                            <form action="{{ route('delivery-legacy.merge-sessions') }}" method="POST">
                                @csrf
                                <input type="hidden" name="sourceSessionId" :value="sourceSessionId">
                                <input type="hidden" name="targetSessionId" :value="keepSessionId">
                                <button type="submit" :disabled="!keepSessionId"
                                    class="px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Merge Sessions
                                </button>
                            </form>
                        </div>
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

        function sessionManager() {
            return {
                selectedSessions: [],
                sessionDetails: [],
                showMergeDialog: false,
                keepSessionId: '',

                get differentSuppliers() {
                    if (this.sessionDetails.length < 2) return false;
                    return this.sessionDetails[0].supID !== this.sessionDetails[1].supID;
                },

                get sourceSessionId() {
                    return this.selectedSessions.find(id => id !== this.keepSessionId) || '';
                },

                toggleSession(id, supID, supplier) {
                    const idx = this.selectedSessions.indexOf(id);
                    if (idx > -1) {
                        this.selectedSessions.splice(idx, 1);
                        this.sessionDetails = this.sessionDetails.filter(s => s.id !== id);
                    } else if (this.selectedSessions.length < 2) {
                        this.selectedSessions.push(id);
                        this.sessionDetails.push({ id, supID, supplier });
                    }
                    this.keepSessionId = '';
                }
            };
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
</x-admin-layout>
