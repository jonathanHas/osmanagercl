<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Label Translation History
            </h2>
            <a href="{{ route('labels.camera-test') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-semibold text-white hover:bg-indigo-500 transition">
                Scan New Label
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($labels->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-400">
                    No saved label translations yet. <a href="{{ route('labels.camera-test') }}" class="text-indigo-600 hover:underline">Scan a label</a> to get started.
                </div>
            @else
                <div class="space-y-6">
                    @foreach ($labels as $label)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="{ showZpl: false, previewLoaded: false, previewSrc: null, previewError: null }">
                            <div class="flex flex-col sm:flex-row gap-4">
                                {{-- Original Image --}}
                                <div class="sm:w-1/3">
                                    @if ($label['image_url'])
                                        <img src="{{ $label['image_url'] }}" alt="{{ $label['name'] }}" class="w-full h-48 object-cover rounded shadow">
                                    @else
                                        <div class="w-full h-48 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-sm">
                                            No image
                                        </div>
                                    @endif
                                    <p class="mt-1 text-xs text-gray-500 truncate">{{ $label['name'] }}</p>
                                    <p class="text-xs text-gray-400">{{ $label['date'] }} &middot; {{ $label['zpl_size'] }} KB ZPL</p>
                                </div>

                                {{-- Preview + Actions --}}
                                <div class="sm:w-2/3 space-y-3">
                                    {{-- Label Preview --}}
                                    <div class="text-center min-h-[120px] flex items-center justify-center bg-gray-50 rounded">
                                        <template x-if="previewError">
                                            <p class="text-red-500 text-sm" x-text="previewError"></p>
                                        </template>
                                        <template x-if="!previewLoaded && !previewError">
                                            <button @click="loadPreview($el, '{{ $label['zpl_path'] }}')" class="px-4 py-2 text-sm text-indigo-600 hover:text-indigo-800">
                                                Load Preview
                                            </button>
                                        </template>
                                        <template x-if="previewSrc">
                                            <img :src="previewSrc" alt="Label preview" class="max-h-48 rounded border">
                                        </template>
                                    </div>

                                    {{-- Action Buttons --}}
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('labels.edit-label', $label['name']) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                            Edit
                                        </a>
                                        <button @click="showZpl = !showZpl" class="inline-flex items-center px-3 py-1.5 bg-gray-100 border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-200 transition">
                                            <span x-text="showZpl ? 'Hide ZPL' : 'Show ZPL'">Show ZPL</span>
                                        </button>
                                        <button @click="printLabel('{{ $label['zpl_path'] }}', $el)" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-green-500 transition">
                                            Print to Zebra
                                        </button>
                                    </div>

                                    {{-- ZPL Code (collapsible) --}}
                                    <div x-show="showZpl" x-collapse>
                                        <pre class="p-3 bg-gray-900 text-green-400 text-xs rounded overflow-x-auto max-h-48 overflow-y-auto font-mono zpl-content" data-path="{{ $label['zpl_path'] }}">Loading...</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @vite('resources/js/zpl-preview.js')
    <script type="module">
        const csrfToken = '{{ csrf_token() }}';
        let zplRenderer = null;

        async function getRenderer() {
            if (!zplRenderer) {
                zplRenderer = await import('{{ Vite::asset("resources/js/zpl-preview.js") }}');
            }
            return zplRenderer;
        }

        // Load ZPL content when expanding
        document.querySelectorAll('.zpl-content').forEach(pre => {
            const observer = new MutationObserver(() => {
                if (pre.closest('[x-show]')?.style.display !== 'none' && pre.textContent === 'Loading...') {
                    fetch('/storage/' + pre.dataset.path)
                        .then(r => r.text())
                        .then(text => pre.textContent = text)
                        .catch(() => pre.textContent = 'Failed to load ZPL');
                }
            });
            observer.observe(pre.closest('[x-show]'), { attributes: true, attributeFilter: ['style'] });
        });

        // Load preview using local WASM renderer
        window.loadPreview = async function(el, zplPath) {
            const component = Alpine.$data(el.closest('[x-data]'));

            try {
                const zplResponse = await fetch('/storage/' + zplPath);
                const zpl = await zplResponse.text();

                const renderer = await getRenderer();
                const base64 = await renderer.renderToBase64(zpl);
                component.previewSrc = 'data:image/png;base64,' + base64;
                component.previewLoaded = true;
            } catch (err) {
                component.previewError = 'Preview failed: ' + err.message;
            }
        }

        // Print a saved ZPL file
        window.printLabel = async function(zplPath, el) {
            const btn = el;
            const originalText = btn.textContent;
            btn.textContent = 'Sending...';
            btn.disabled = true;

            try {
                const zplResponse = await fetch('/storage/' + zplPath);
                const zpl = await zplResponse.text();

                const response = await fetch('{{ route("labels.print-zpl") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ zpl: zpl }),
                });
                const data = await response.json();

                btn.textContent = data.success ? 'Sent!' : 'Failed';
                setTimeout(() => { btn.textContent = originalText; btn.disabled = false; }, 2000);
            } catch (err) {
                btn.textContent = 'Error';
                setTimeout(() => { btn.textContent = originalText; btn.disabled = false; }, 2000);
            }
        }
    </script>
</x-admin-layout>
