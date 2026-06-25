<x-admin-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="voucherPrint()">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Print Vouchers</h2>
            <a href="{{ route('vouchers.list') }}" class="text-blue-400 hover:text-blue-300 text-sm">Back to vouchers</a>
        </div>

        @if ($labels->isEmpty())
            <div class="bg-gray-800 rounded p-6 text-center text-gray-400">No vouchers selected to print.</div>
        @else
            <div class="bg-gray-800 rounded p-4 mb-4 flex items-center justify-between flex-wrap gap-3">
                <p class="text-gray-300 text-sm">
                    {{ $labels->count() }} {{ \Illuminate\Support\Str::plural('label', $labels->count()) }}
                    to print on the Zebra small label (56×30mm).
                </p>
                <button @click="printZebra()" :disabled="printing"
                        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-500 text-white font-bold py-2 px-5 rounded">
                    <span x-show="!printing">Print to Zebra</span>
                    <span x-show="printing">Printing…</span>
                </button>
            </div>

            <div x-show="printMessage" x-cloak class="mb-4 rounded px-4 py-3"
                 :class="printSuccess ? 'bg-green-700 text-white' : 'bg-red-700 text-white'">
                <p x-text="printMessage"></p>
                <p x-show="printOutput" class="text-xs opacity-80 mt-1 font-mono break-all" x-text="printOutput"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($labels as $label)
                    <div class="bg-white rounded p-3 flex flex-col items-center">
                        <img x-ref="preview_{{ $label['id'] }}" alt="Label preview for {{ $label['code'] }}"
                             class="max-w-full h-auto border border-gray-200" />
                        <p class="mt-2 font-mono text-sm text-gray-700">{{ $label['code'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @vite('resources/js/zpl-preview.js')
    <script>
        function voucherPrint() {
            return {
                printing: false,
                printMessage: '',
                printOutput: '',
                printSuccess: false,
                labels: @json($labels),
                ids: @json($ids),

                async init() {
                    const renderer = await this.getRenderer();
                    if (!renderer) return;
                    for (const label of this.labels) {
                        try {
                            const base64 = await renderer.renderToBase64(label.zpl, 56, 31);
                            const img = this.$refs['preview_' + label.id];
                            if (img) img.src = 'data:image/png;base64,' + base64;
                        } catch (e) {
                            console.error('Preview failed for ' + label.code, e);
                        }
                    }
                },

                async getRenderer() {
                    if (window.ZplPreview) return window.ZplPreview;
                    await new Promise(r => setTimeout(r, 500));
                    return window.ZplPreview || null;
                },

                async printZebra() {
                    if (this.printing) return;
                    this.printing = true;
                    this.printMessage = '';
                    this.printOutput = '';
                    try {
                        const res = await fetch('{{ route("vouchers.print.send") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ ids: this.ids }),
                        });
                        const data = await res.json();
                        this.printSuccess = data.success;
                        this.printMessage = data.message || (data.success ? 'Printed' : 'Print failed');
                        this.printOutput = data.success ? '' : (data.output || '');
                    } catch (err) {
                        this.printSuccess = false;
                        this.printMessage = 'Print failed: ' + err.message;
                    } finally {
                        this.printing = false;
                    }
                },
            };
        }
    </script>
</x-admin-layout>
