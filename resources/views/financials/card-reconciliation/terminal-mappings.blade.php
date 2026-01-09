<x-admin-layout>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Terminal-Till Mappings</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Configure which card terminal should match with which POS till. This ensures transactions only match payments from the correct till.
                </p>
            </div>
            <a href="{{ route('management.card-reconciliation.index') }}"
               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Reconciliation
            </a>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Add New Mapping -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Terminal Mapping</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Map a card terminal to a POS till
                    </p>
                </div>
                <form action="{{ route('management.card-reconciliation.save-terminal-mapping') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <div>
                        <label for="terminal_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Terminal ID (TID)
                        </label>
                        @if($availableTerminals->isNotEmpty())
                        <select name="terminal_id" id="terminal_id" required
                                onchange="updateTerminalName(this)"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a terminal...</option>
                            @foreach($availableTerminals as $terminal)
                            <option value="{{ $terminal->terminal_id }}" data-name="{{ $terminal->terminal_name }}">
                                {{ $terminal->terminal_id }} - {{ $terminal->terminal_name ?: 'Unnamed' }}
                            </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Or enter manually:
                        </p>
                        <input type="text" name="terminal_id_manual" placeholder="Enter terminal ID"
                               onchange="document.getElementById('terminal_id').value = this.value"
                               class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @else
                        <input type="text" name="terminal_id" id="terminal_id" required placeholder="e.g., 90282976"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            No terminals found. Upload card transactions first to see available terminals.
                        </p>
                        @endif
                    </div>

                    <div>
                        <label for="terminal_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Terminal Name (optional)
                        </label>
                        <input type="text" name="terminal_name" id="terminal_name" placeholder="e.g., Shop Terminal"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="pos_host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            POS Till (HOST)
                        </label>
                        @if($availableTills->isNotEmpty())
                        <select name="pos_host" id="pos_host" required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a till...</option>
                            @foreach($availableTills as $till)
                            <option value="{{ $till }}">{{ $till }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="text" name="pos_host" id="pos_host" required placeholder="e.g., Till 1"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            No tills found in POS database.
                        </p>
                        @endif
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Save Mapping
                        </button>
                    </div>
                </form>
            </div>

            <!-- Current Mappings -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Current Mappings</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $mappings->count() }} terminal(s) mapped to tills
                    </p>
                </div>
                <div class="p-6">
                    @if($mappings->isEmpty())
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                        <p>No terminal mappings configured yet.</p>
                        <p class="text-sm mt-1">Add a mapping to enforce terminal-to-till pairing.</p>
                    </div>
                    @else
                    <div class="space-y-3">
                        @foreach($mappings as $mapping)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ $mapping->terminal_name ?: $mapping->terminal_id }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            TID: {{ $mapping->terminal_id }}
                                        </p>
                                    </div>
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="font-medium text-blue-600 dark:text-blue-400">
                                            {{ $mapping->pos_host }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">POS Till</p>
                                    </div>
                                </div>
                            </div>
                            <form action="{{ route('management.card-reconciliation.delete-terminal-mapping', $mapping) }}" method="POST" class="ml-4">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        onclick="return confirm('Remove this terminal mapping?')"
                                        class="p-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                        title="Remove mapping">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6">
            <div class="flex items-start gap-4">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-blue-800 dark:text-blue-200">
                    <p class="font-medium mb-2">How Terminal Mappings Work</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-700 dark:text-blue-300">
                        <li><strong>Strict Matching:</strong> When a terminal is mapped, card transactions from that terminal will ONLY match payments from the mapped till.</li>
                        <li><strong>Orphan Detection:</strong> If a terminal is temporarily used at a different till, those transactions will become orphans for manual review.</li>
                        <li><strong>No Mapping:</strong> Terminals without a mapping can match payments from any till (existing behavior).</li>
                        <li><strong>Auto-Match:</strong> The auto-match feature respects terminal mappings by default.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTerminalName(select) {
            const option = select.options[select.selectedIndex];
            const name = option.dataset.name || '';
            document.getElementById('terminal_name').value = name;
        }
    </script>
</x-admin-layout>
