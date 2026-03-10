<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Label Translation Review
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('labels.camera-test') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition">v1 (Raw ZPL)</a>
                <a href="{{ route('labels.camera-test2') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition">v2 (JSON)</a>
                <a href="{{ route('labels.translation-history') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition">History</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Original Image --}}
            @if ($original_image)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-lg text-gray-800 mb-3">Original Label</h3>
                    <img src="{{ $original_image }}" alt="Original label" class="mx-auto max-h-80 rounded shadow">
                </div>
            @endif

            {{-- Label Size Switcher (only when label_data is available from v2 flow) --}}
            @if (!empty($label_data))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-lg text-gray-800 mb-3">Label Size</h3>
                    <div class="flex gap-3" id="size-switcher">
                        <button type="button" data-size="large"
                            class="size-btn flex-1 px-4 py-2 rounded-md border-2 text-sm font-medium transition {{ ($label_size ?? 'large') === 'large' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}">
                            Large <span class="text-xs text-gray-400">76x50mm</span>
                        </button>
                        <button type="button" data-size="small"
                            class="size-btn flex-1 px-4 py-2 rounded-md border-2 text-sm font-medium transition {{ ($label_size ?? 'large') === 'small' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}">
                            Small <span class="text-xs text-gray-400">56x30mm</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Font Size Control (v2 flow only) --}}
            @if (!empty($label_data))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-lg text-gray-800 mb-3">Text Size</h3>
                    <div class="flex items-center gap-4">
                        <button type="button" id="btn-font-down" class="px-3 py-1.5 bg-gray-200 rounded-md text-lg font-bold text-gray-700 hover:bg-gray-300 transition leading-none">A-</button>
                        <div class="flex-1 text-center">
                            <input type="range" id="font-scale-slider" min="50" max="200" value="{{ round(($font_scale ?? 1.0) * 100) }}" step="10" class="w-full accent-indigo-600">
                            <span id="font-scale-label" class="text-sm text-gray-500">{{ round(($font_scale ?? 1.0) * 100) }}%</span>
                        </div>
                        <button type="button" id="btn-font-up" class="px-3 py-1.5 bg-gray-200 rounded-md text-lg font-bold text-gray-700 hover:bg-gray-300 transition leading-none">A+</button>
                    </div>
                </div>
            @endif

            {{-- Label Preview --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-lg text-gray-800 mb-3">Label Preview</h3>
                <div id="preview-container" class="text-center">
                    <p id="preview-loading" class="text-gray-500 text-sm py-8">Generating preview...</p>
                    <img id="preview-image" class="mx-auto rounded shadow border hidden" alt="Label preview">
                    <p id="preview-error" class="text-red-600 text-sm py-4 hidden"></p>
                </div>
            </div>

            {{-- ZPL Editor + Live Preview --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="{ editing: false }">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-lg text-gray-800">ZPL Code</h3>
                    <button @click="editing = !editing" class="text-sm text-indigo-600 hover:text-indigo-800">
                        <span x-text="editing ? 'Close Editor' : 'Edit ZPL'"></span>
                    </button>
                </div>

                {{-- Read-only view (default) --}}
                <div x-show="!editing">
                    <pre id="zpl-code" class="p-3 bg-gray-900 text-green-400 text-xs rounded overflow-x-auto max-h-48 overflow-y-auto font-mono cursor-pointer" @click="editing = true; $nextTick(() => $refs.editor.focus())">{{ $zpl }}</pre>
                </div>

                {{-- Editable view --}}
                <div x-show="editing" x-cloak>
                    <textarea x-ref="editor" id="zpl-editor"
                        class="w-full p-3 bg-gray-900 text-green-400 text-xs rounded font-mono border-0 focus:ring-2 focus:ring-indigo-500"
                        rows="12" spellcheck="false">{{ $zpl }}</textarea>
                    <div class="mt-2 flex gap-2">
                        <button type="button" id="btn-refresh-preview"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition">
                            Refresh Preview
                        </button>
                        <button @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800">Cancel</button>
                    </div>
                </div>
            </div>

            {{-- Extracted Data (only from v2 flow) --}}
            @if (!empty($label_data))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center justify-between w-full text-left">
                        <h3 class="font-semibold text-lg text-gray-800">Extracted Data (JSON)</h3>
                        <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="mt-3">
                        <pre class="p-3 bg-gray-900 text-yellow-300 text-xs rounded overflow-x-auto max-h-48 overflow-y-auto font-mono">{{ json_encode($label_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex flex-wrap gap-3">
                <button type="button" id="btn-print"
                        class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-white hover:bg-green-500 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print to Zebra
                </button>
                <button type="button" id="btn-save"
                        class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-white hover:bg-indigo-500 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Save ZPL
                </button>
                <a href="{{ route('labels.camera-test2') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                    Scan Another
                </a>
                <a href="{{ route('labels.translation-history') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                    View History
                </a>
            </div>

            <div id="action-result" class="hidden p-4 rounded text-sm"></div>
        </div>
    </div>

    @vite('resources/js/zpl-preview.js')
    <script type="module">
        const zplDisplay = document.getElementById('zpl-code');
        const zplEditor = document.getElementById('zpl-editor');
        const previewImg = document.getElementById('preview-image');
        const previewLoading = document.getElementById('preview-loading');
        const previewError = document.getElementById('preview-error');
        const printBtn = document.getElementById('btn-print');
        const saveBtn = document.getElementById('btn-save');
        const refreshBtn = document.getElementById('btn-refresh-preview');
        const resultDiv = document.getElementById('action-result');

        // Label data from v2 flow (if available)
        const labelData = @json($label_data ?? null);
        let currentSize = '{{ $label_size ?? "large" }}';
        let currentFontScale = {{ $font_scale ?? 1.0 }};

        // Label dimensions in mm for the renderer (must match ^PW/^LL in ZPL)
        const labelDims = {
            large: { w: 75, h: 50 },   // 900/12=75mm, 600/12=50mm
            small: { w: 56, h: 31 },    // 673/12≈56mm, 366/12≈31mm
        };

        function getCurrentDims() {
            return labelDims[currentSize] || labelDims.large;
        }

        // Font scale controls
        const fontSlider = document.getElementById('font-scale-slider');
        const fontLabel = document.getElementById('font-scale-label');
        const fontDownBtn = document.getElementById('btn-font-down');
        const fontUpBtn = document.getElementById('btn-font-up');

        function getZpl() {
            return (zplEditor && zplEditor.value) || zplDisplay.textContent;
        }

        // Load ZPL renderer (ES module import with window global fallback)
        async function getZplRenderer() {
            // Try ES module import first, fall back to window global
            const mod = await import('{{ Vite::asset("resources/js/zpl-preview.js") }}');
            if (typeof mod.renderToBase64 === 'function') return mod;
            if (window.ZplPreview) return window.ZplPreview;
            throw new Error('ZPL renderer not available');
        }

        // Render preview using local WASM renderer
        async function renderPreview(zpl) {
            previewLoading.classList.remove('hidden');
            previewError.classList.add('hidden');
            previewImg.classList.add('hidden');

            try {
                const renderer = await getZplRenderer();
                const dims = getCurrentDims();
                await renderer.renderToImg(zpl, previewImg, dims.w, dims.h);
                previewImg.classList.remove('hidden');
                previewLoading.classList.add('hidden');
            } catch (err) {
                previewLoading.classList.add('hidden');
                previewError.textContent = 'Preview failed: ' + err.message;
                previewError.classList.remove('hidden');
            }
        }

        // Regenerate ZPL with current size + font scale
        async function regenerate() {
            if (!labelData) return;
            try {
                const response = await fetch('{{ route("labels.regenerate-zpl") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        label_data: labelData,
                        label_size: currentSize,
                        font_scale: currentFontScale,
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    zplDisplay.textContent = data.zpl;
                    if (zplEditor) zplEditor.value = data.zpl;
                    renderPreview(data.zpl);

                    // Update slider if scale was clamped to fit
                    if (data.font_scale !== undefined && fontSlider) {
                        const requestedPercent = Math.round(currentFontScale * 100);
                        const clampedPercent = Math.round(data.font_scale * 100);
                        currentFontScale = data.font_scale;
                        fontSlider.value = clampedPercent;
                        fontLabel.textContent = clampedPercent + (clampedPercent < requestedPercent ? '% (max fit)' : '%');
                    }
                }
            } catch (err) {
                showResult(false, 'Regeneration failed: ' + err.message);
            }
        }

        // Initial render
        renderPreview(getZpl());

        // Refresh preview from editor
        refreshBtn?.addEventListener('click', function() {
            const zpl = getZpl();
            zplDisplay.textContent = zpl;
            renderPreview(zpl);
        });

        // Font scale slider
        if (fontSlider) {
            function updateFontScale(value) {
                currentFontScale = value / 100;
                fontSlider.value = value;
                fontLabel.textContent = value + '%';
                regenerate();
            }

            fontSlider.addEventListener('input', function() {
                currentFontScale = this.value / 100;
                fontLabel.textContent = this.value + '%';
            });
            fontSlider.addEventListener('change', function() {
                updateFontScale(parseInt(this.value));
            });
            fontDownBtn?.addEventListener('click', function() {
                updateFontScale(Math.max(50, parseInt(fontSlider.value) - 10));
            });
            fontUpBtn?.addEventListener('click', function() {
                updateFontScale(Math.min(200, parseInt(fontSlider.value) + 10));
            });
        }

        // Label size switcher (v2 flow only)
        document.querySelectorAll('#size-switcher .size-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!labelData) return;
                currentSize = this.dataset.size;

                // Update button styles
                document.querySelectorAll('#size-switcher .size-btn').forEach(b => {
                    b.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
                    b.classList.add('border-gray-200', 'text-gray-600');
                });
                this.classList.remove('border-gray-200', 'text-gray-600');
                this.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');

                regenerate();
            });
        });

        function showResult(success, message) {
            resultDiv.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
            resultDiv.classList.add(success ? 'bg-green-100' : 'bg-red-100', success ? 'text-green-700' : 'text-red-700');
            resultDiv.textContent = message;
        }

        // Print
        printBtn.addEventListener('click', async function() {
            printBtn.disabled = true;
            printBtn.textContent = 'Sending to printer...';

            try {
                const response = await fetch('{{ route("labels.print-zpl") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ zpl: getZpl() }),
                });
                const data = await response.json();
                showResult(data.success, data.message + ' — ' + data.output);
            } catch (err) {
                showResult(false, 'Request failed: ' + err.message);
            }

            printBtn.disabled = false;
            printBtn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Print to Zebra';
        });

        // Save
        saveBtn.addEventListener('click', async function() {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const response = await fetch('{{ route("labels.save-zpl") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        zpl: getZpl(),
                        image_path: '{{ $image_path }}',
                        label_data: labelData,
                    }),
                });
                const data = await response.json();
                showResult(data.success, data.message);
            } catch (err) {
                showResult(false, 'Request failed: ' + err.message);
            }

            saveBtn.disabled = false;
            saveBtn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>Save ZPL';
        });
    </script>
</x-admin-layout>
