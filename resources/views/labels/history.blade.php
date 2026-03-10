<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Label Translation History
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('labels.camera-test') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition">v1 (Raw ZPL)</a>
                <a href="{{ route('labels.camera-test2') }}" class="px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">v2 (JSON)</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($labels->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-400">
                    No saved label translations yet. <a href="{{ route('labels.camera-test2') }}" class="text-indigo-600 hover:underline">Scan a label</a> to get started.
                </div>
            @else
                <div class="space-y-6">
                    @foreach ($labels as $index => $label)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6"
                             x-data="{
                                showZpl: false,
                                previewLoaded: false,
                                previewSrc: null,
                                previewError: null,
                                currentSize: 'large',
                                fontScale: 100,
                                labelData: {{ json_encode($label['label_data']) }},
                                zplContent: null,
                             }">
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

                                {{-- Preview + Controls --}}
                                <div class="sm:w-2/3 space-y-3">
                                    {{-- Label Preview --}}
                                    <div class="text-center min-h-[120px] flex items-center justify-center bg-gray-50 rounded">
                                        <template x-if="previewError">
                                            <p class="text-red-500 text-sm" x-text="previewError"></p>
                                        </template>
                                        <template x-if="!previewLoaded && !previewError">
                                            <button @click="loadPreview('{{ $label['zpl_path'] }}')" class="px-4 py-2 text-sm text-indigo-600 hover:text-indigo-800">
                                                Load Preview
                                            </button>
                                        </template>
                                        <template x-if="previewSrc">
                                            <img :src="previewSrc" alt="Label preview" class="max-h-48 rounded border">
                                        </template>
                                    </div>

                                    {{-- Label Size + Font Scale (only if JSON data exists) --}}
                                    @if ($label['label_data'])
                                        <div class="flex flex-col gap-2 p-3 bg-gray-50 rounded">
                                            {{-- Size Toggle --}}
                                            <div class="flex gap-2">
                                                <button @click="currentSize = 'large'; regenerate()" class="flex-1 px-2 py-1 rounded text-xs font-medium border-2 transition"
                                                        :class="currentSize === 'large' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500'">
                                                    Large
                                                </button>
                                                <button @click="currentSize = 'small'; regenerate()" class="flex-1 px-2 py-1 rounded text-xs font-medium border-2 transition"
                                                        :class="currentSize === 'small' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500'">
                                                    Small
                                                </button>
                                            </div>
                                            {{-- Font Scale --}}
                                            <div class="flex items-center gap-2">
                                                <button @click="fontScale = Math.max(50, fontScale - 10); regenerate()" class="px-2 py-0.5 bg-gray-200 rounded text-xs font-bold text-gray-700 hover:bg-gray-300">A-</button>
                                                <input type="range" min="50" max="200" step="10" x-model="fontScale"
                                                       @change="regenerate()"
                                                       class="flex-1 accent-indigo-600 h-1.5">
                                                <button @click="fontScale = Math.min(200, fontScale + 10); regenerate()" class="px-2 py-0.5 bg-gray-200 rounded text-xs font-bold text-gray-700 hover:bg-gray-300">A+</button>
                                                <span class="text-xs text-gray-500 w-16 text-right" x-text="fontScale + '%'"></span>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Action Buttons --}}
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('labels.edit-label', $label['name']) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                            Edit
                                        </a>
                                        <button @click="showZpl = !showZpl" class="inline-flex items-center px-3 py-1.5 bg-gray-100 border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-200 transition">
                                            <span x-text="showZpl ? 'Hide ZPL' : 'Show ZPL'">Show ZPL</span>
                                        </button>
                                        <button @click="printLabel('{{ $label['zpl_path'] }}')" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-green-500 transition">
                                            Print to Zebra
                                        </button>
                                        @if ($label['label_data'])
                                            <button @click="saveZpl('{{ $label['image_path'] }}')" class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-gray-500 transition">
                                                Save
                                            </button>
                                        @endif
                                    </div>

                                    {{-- ZPL Code (collapsible) --}}
                                    <div x-show="showZpl" x-collapse>
                                        <pre class="p-3 bg-gray-900 text-green-400 text-xs rounded overflow-x-auto max-h-48 overflow-y-auto font-mono zpl-content" data-path="{{ $label['zpl_path'] }}" x-text="zplContent || 'Loading...'"></pre>
                                    </div>

                                    {{-- Status message --}}
                                    <div x-data="{ msg: '', success: true }" x-ref="status">
                                        <template x-if="msg">
                                            <p class="text-xs p-2 rounded" :class="success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" x-text="msg"></p>
                                        </template>
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

        // Render ZPL to preview image
        async function renderZplToImg(zpl) {
            const renderer = await getRenderer();
            const base64 = await renderer.renderToBase64(zpl);
            return 'data:image/png;base64,' + base64;
        }

        // Make Alpine methods available via x-data
        document.addEventListener('alpine:init', () => {
            Alpine.magic('csrf', () => csrfToken);
        });

        // Attach methods to each card's Alpine scope
        document.querySelectorAll('[x-data]').forEach(el => {
            if (!el.__x) return;
        });

        // These need to be window-level for Alpine @click bindings
        window.loadPreview = null;  // Handled per-component below
    </script>

    {{-- Per-card Alpine component logic --}}
    <script>
        const csrfToken2 = '{{ csrf_token() }}';

        // Label dimensions in mm for the renderer
        const labelDims = {
            large: { w: 75, h: 50 },
            small: { w: 56, h: 31 },
        };

        document.addEventListener('alpine:init', () => {
            // Extend each card with methods
            Alpine.directive('init', () => {});
        });

        // We use event delegation + Alpine.$data for the interactive methods
        document.addEventListener('DOMContentLoaded', () => {
            // Attach behaviors to all label cards
            document.querySelectorAll('[x-data]').forEach(cardEl => {
                const component = Alpine.$data(cardEl);
                if (!component || component.labelData === undefined) return;

                // Load preview method
                component.loadPreview = async function(zplPath) {
                    try {
                        const zplResponse = await fetch('/storage/' + zplPath);
                        const zpl = await zplResponse.text();
                        component.zplContent = zpl;

                        const zplRendererMod = await import('{{ Vite::asset("resources/js/zpl-preview.js") }}');
                        const dims = labelDims[component.currentSize] || labelDims.large;
                        const base64 = await zplRendererMod.renderToBase64(zpl, dims.w, dims.h);
                        component.previewSrc = 'data:image/png;base64,' + base64;
                        component.previewLoaded = true;
                    } catch (err) {
                        component.previewError = 'Preview failed: ' + err.message;
                    }
                };

                // Regenerate ZPL with current size + font scale
                component.regenerate = async function() {
                    if (!component.labelData) return;
                    try {
                        const response = await fetch('{{ route("labels.regenerate-zpl") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken2,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                label_data: component.labelData,
                                label_size: component.currentSize,
                                font_scale: component.fontScale / 100,
                            }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            component.zplContent = data.zpl;

                            // Update font scale if clamped
                            if (data.font_scale !== undefined) {
                                const clampedPercent = Math.round(data.font_scale * 100);
                                component.fontScale = clampedPercent;
                            }

                            // Re-render preview if already loaded
                            if (component.previewLoaded) {
                                const zplRendererMod = await import('{{ Vite::asset("resources/js/zpl-preview.js") }}');
                                const dims = labelDims[component.currentSize] || labelDims.large;
                                const base64 = await zplRendererMod.renderToBase64(data.zpl, dims.w, dims.h);
                                component.previewSrc = 'data:image/png;base64,' + base64;
                            }
                        }
                    } catch (err) {
                        console.error('Regeneration failed:', err);
                    }
                };

                // Print current ZPL
                component.printLabel = async function(zplPath) {
                    const zpl = component.zplContent || (await fetch('/storage/' + zplPath).then(r => r.text()));
                    try {
                        const response = await fetch('{{ route("labels.print-zpl") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken2,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ zpl: zpl }),
                        });
                        const data = await response.json();
                        const status = Alpine.$data(cardEl.querySelector('[x-ref="status"]'));
                        if (status) {
                            status.msg = data.success ? 'Print job sent' : 'Print failed';
                            status.success = data.success;
                            setTimeout(() => { status.msg = ''; }, 3000);
                        }
                    } catch (err) {
                        console.error('Print failed:', err);
                    }
                };

                // Save regenerated ZPL back to disk
                component.saveZpl = async function(imagePath) {
                    if (!component.zplContent) return;
                    try {
                        const response = await fetch('{{ route("labels.save-zpl") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken2,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                zpl: component.zplContent,
                                image_path: imagePath,
                                label_data: component.labelData,
                            }),
                        });
                        const data = await response.json();
                        const status = Alpine.$data(cardEl.querySelector('[x-ref="status"]'));
                        if (status) {
                            status.msg = data.success ? 'Saved' : 'Save failed';
                            status.success = data.success;
                            setTimeout(() => { status.msg = ''; }, 3000);
                        }
                    } catch (err) {
                        console.error('Save failed:', err);
                    }
                };

                // Load ZPL content when Show ZPL is first clicked
                const zplPre = cardEl.querySelector('.zpl-content');
                if (zplPre && !component.zplContent) {
                    const zplPath = zplPre.dataset.path;
                    fetch('/storage/' + zplPath)
                        .then(r => r.text())
                        .then(text => { component.zplContent = text; })
                        .catch(() => { component.zplContent = 'Failed to load ZPL'; });
                }
            });
        });
    </script>
</x-admin-layout>
