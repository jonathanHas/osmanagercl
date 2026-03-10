<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Label Translation Review
        </h2>
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
                <a href="{{ route('labels.camera-test') }}"
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

        function getZpl() {
            return (zplEditor && zplEditor.value) || zplDisplay.textContent;
        }

        // Render preview using local WASM renderer
        async function renderPreview(zpl) {
            previewLoading.classList.remove('hidden');
            previewError.classList.add('hidden');
            previewImg.classList.add('hidden');

            try {
                const { renderToImg } = await import('{{ Vite::asset("resources/js/zpl-preview.js") }}');
                await renderToImg(zpl, previewImg);
                previewImg.classList.remove('hidden');
                previewLoading.classList.add('hidden');
            } catch (err) {
                previewLoading.classList.add('hidden');
                previewError.textContent = 'Preview failed: ' + err.message;
                previewError.classList.remove('hidden');
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

        // Save (uses current editor content)
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
