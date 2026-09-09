<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="palletSync()">

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Udea Pallet Volumes</h2>
            @if($lastSyncedAt)
                <span class="text-sm text-gray-400">
                    Last synced {{ \Carbon\Carbon::parse($lastSyncedAt)->diffForHumans() }}
                </span>
            @endif
        </div>

        {{-- Coverage --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-5">
                <div class="text-3xl font-bold text-gray-100">
                    {{ $coveredCount === null ? '—' : number_format($coveredCount) }}
                </div>
                <div class="text-sm text-gray-400 mt-1">stocked products with pallet data</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-5">
                @if($stockedCount === null)
                    <div class="text-3xl font-bold text-gray-500">—</div>
                    <div class="text-sm text-gray-400 mt-1">stocked totals unavailable (POS database unreachable)</div>
                @else
                    <div class="text-3xl font-bold {{ $stockedCount - $coveredCount > 0 ? 'text-amber-400' : 'text-green-400' }}">
                        {{ number_format(max($stockedCount - $coveredCount, 0)) }}
                    </div>
                    <div class="text-sm text-gray-400 mt-1">still missing (of {{ number_format($stockedCount) }} stocked)</div>
                @endif
            </div>
            <div class="bg-gray-800 rounded-lg p-5">
                <div class="text-3xl font-bold text-gray-100">{{ number_format($totalWithData) }}</div>
                <div class="text-sm text-gray-400 mt-1">rows stored in total</div>
            </div>
        </div>

        {{-- How it works --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-100 mb-3">How to refresh</h3>
            <p class="text-sm text-gray-400 mb-3">
                Udea only publishes pallet volumes for products that are actually in the basket,
                so the data has to be read out of a basket containing the range. The figures are
                per-product constants, so this only needs repeating when the product range changes.
            </p>
            <ol class="list-decimal list-inside text-sm text-gray-300 space-y-1">
                <li>Empty the Udea basket (back up the live order first if one is pending).</li>
                <li>Upload the product list through Udea's Excel importer, quantity 1 each.</li>
                <li>Press <span class="text-gray-100 font-medium">Sync from basket</span> below.</li>
                <li>Empty the basket again and restore the real order.</li>
            </ol>
            <p class="text-xs text-gray-500 mt-3">
                This page only reads the basket &mdash; it never adds, changes or removes anything on Udea.
                Pallet capacities in use: Europallet {{ $capacities['euro'] }}, blockpallet {{ $capacities['block'] }}.
            </p>
        </div>

        {{-- Sync --}}
        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-100">Sync from basket</h3>
                    <p class="text-sm text-gray-400 mt-1">Reads whatever is in the basket right now.</p>
                </div>
                <button @click="run()"
                        :disabled="running"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors">
                    <svg x-show="!running" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <svg x-show="running" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="running ? 'Syncing...' : 'Sync from basket'"></span>
                </button>
            </div>

            <p x-show="running" x-cloak class="text-xs text-amber-400 mt-3">
                A basket holding the full range is a large page &mdash; this can take a few minutes. Leave the tab open.
            </p>

            <div x-show="lines.length > 0" x-cloak class="mt-5">
                <pre class="bg-gray-900 text-gray-300 text-xs rounded p-4 overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap"><template x-for="line in lines" :key="line.id"><span :class="line.cls" x-text="line.text + '\n'"></span></template></pre>
            </div>

            <div x-show="failed" x-cloak class="mt-4 p-4 bg-red-900/30 border border-red-600 rounded-lg">
                <span class="text-red-300 text-sm" x-text="failed"></span>
            </div>

            <div x-show="finished && !failed" x-cloak class="mt-4 p-4 bg-green-900/30 border border-green-600 rounded-lg flex items-center justify-between">
                <span class="text-green-300 text-sm">Sync complete.</span>
                <button @click="window.location.reload()" class="text-sm text-green-200 underline">Refresh coverage</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function palletSync() {
            return {
                running: false,
                finished: false,
                failed: null,
                lines: [],
                nextId: 0,

                push(text, cls) {
                    this.lines.push({ id: this.nextId++, text: text, cls: cls || 'text-gray-300' });
                },

                async run() {
                    this.running = true;
                    this.finished = false;
                    this.failed = null;
                    this.lines = [];

                    try {
                        const response = await fetch('{{ route('tools.udea-pallet-volumes.sync') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'text/event-stream',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Server returned ' + response.status);
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';

                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) break;

                            buffer += decoder.decode(value, { stream: true });
                            const parts = buffer.split('\n\n');
                            buffer = parts.pop();

                            for (const part of parts) {
                                if (!part.startsWith('data: ')) continue;
                                let payload;
                                try {
                                    payload = JSON.parse(part.slice(6));
                                } catch (e) {
                                    continue;
                                }
                                if (payload.event === 'error') {
                                    this.failed = payload.message;
                                    this.push(payload.message, 'text-red-400');
                                } else if (payload.event === 'done') {
                                    this.finished = true;
                                } else {
                                    this.push(payload.message);
                                }
                            }
                        }
                    } catch (e) {
                        this.failed = 'Sync failed: ' + e.message;
                    } finally {
                        this.running = false;
                    }
                },
            }
        }
    </script>
    @endpush
</x-admin-layout>
