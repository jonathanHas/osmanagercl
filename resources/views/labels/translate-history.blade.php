<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Translation History</h2>
            <a href="{{ route('labels.translate') }}" class="px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">New Translation</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($translations->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-400">
                    No translations yet. <a href="{{ route('labels.translate') }}" class="text-indigo-600 hover:underline">Translate a label</a> to get started.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($translations as $translation)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5"
                             x-data="{ showPreview: false, previewSrc: null, previewError: null }">
                            <div class="flex flex-col sm:flex-row gap-4">
                                {{-- Photo Thumbnail --}}
                                <div class="sm:w-1/4 flex-shrink-0">
                                    @if ($translation->original_photos && count($translation->original_photos) > 0)
                                        <img src="{{ asset('storage/' . $translation->original_photos[0]) }}" alt="Label photo"
                                             class="w-full h-32 object-cover rounded-lg shadow-sm">
                                    @else
                                        <div class="w-full h-32 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm">
                                            No photo
                                        </div>
                                    @endif
                                </div>

                                {{-- Details --}}
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-900 truncate">{{ $translation->label_data['product_name'] ?? 'Untitled' }}</p>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 mt-1">
                                        @if ($translation->product_code)
                                            <span class="font-mono">{{ $translation->product_code }}</span>
                                        @endif
                                        <span>{{ $translation->label_size }}</span>
                                        <span>{{ $translation->created_at->format('M j, Y g:ia') }}</span>
                                        @if ($translation->user)
                                            <span>by {{ $translation->user->name }}</span>
                                        @endif
                                    </div>

                                    {{-- Ingredients preview --}}
                                    @if (!empty($translation->label_data['ingredients']))
                                        <p class="text-xs text-gray-400 mt-2 line-clamp-2">{{ Str::limit($translation->label_data['ingredients'], 120) }}</p>
                                    @endif

                                    {{-- ZPL Preview --}}
                                    <div x-show="showPreview" class="mt-3">
                                        <div class="text-center min-h-[80px] flex items-center justify-center bg-gray-50 rounded">
                                            <img x-show="previewSrc" :src="previewSrc" class="max-h-32 rounded border" alt="Label preview">
                                            <p x-show="previewError" class="text-red-500 text-xs" x-text="previewError"></p>
                                            <p x-show="!previewSrc && !previewError" class="text-gray-400 text-xs">Loading preview...</p>
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <button @click="showPreview = !showPreview; if (showPreview && !previewSrc) loadPreview()"
                                            class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-200 transition">
                                            <span x-text="showPreview ? 'Hide Preview' : 'Show Preview'"></span>
                                        </button>
                                        <a href="{{ route('labels.translate', ['edit' => $translation->id]) }}"
                                            class="px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                            Edit
                                        </a>
                                        <a href="{{ route('labels.translate') }}?barcode={{ $translation->product_code }}"
                                            class="px-3 py-1.5 bg-green-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-green-500 transition">
                                            Print
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $translations->links() }}
                </div>
            @endif
        </div>
    </div>

    @vite('resources/js/zpl-preview.js')
    <script type="module">
        const labelDims = @json(collect($labelSizes)->map(fn($s) => ['w' => $s['widthMm'], 'h' => $s['heightMm']]));

        let _zplRenderer = null;
        async function getZplRenderer() {
            if (_zplRenderer) return _zplRenderer;
            const mod = await import('{{ Vite::asset("resources/js/zpl-preview.js") }}');
            _zplRenderer = (typeof mod.renderToBase64 === 'function') ? mod : window.ZplPreview;
            if (!_zplRenderer) throw new Error('ZPL renderer not available');
            return _zplRenderer;
        }

        // Make loadPreview available per-card via Alpine
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[x-data]').forEach(el => {
                const component = Alpine.$data(el);
                if (component && 'previewSrc' in component) {
                    const card = el;
                    const zplContent = @json($translations->pluck('zpl_content', 'id'));
                    const sizes = @json($translations->pluck('label_size', 'id'));
                    const id = {{ $translations->count() > 0 ? 'parseInt(card.dataset.id || 0)' : '0' }};

                    component.loadPreview = async function() {
                        // Find translation ID from DOM context
                        const editLink = card.querySelector('a[href*="edit="]');
                        if (!editLink) return;
                        const match = editLink.href.match(/edit=(\d+)/);
                        if (!match) return;
                        const translationId = match[1];

                        const zpl = zplContent[translationId];
                        const size = sizes[translationId] || 'large';
                        if (!zpl) {
                            component.previewError = 'No ZPL content';
                            return;
                        }

                        try {
                            const renderer = await getZplRenderer();
                            const dims = labelDims[size] || labelDims.large || { w: 75, h: 50 };
                            const base64 = await renderer.renderToBase64(zpl, dims.w, dims.h);
                            component.previewSrc = 'data:image/png;base64,' + base64;
                        } catch (err) {
                            component.previewError = 'Preview failed: ' + err.message;
                        }
                    };
                }
            });
        });
    </script>
</x-admin-layout>
