<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Barcode Scanner Test</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-md mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Hidden temp element for html5-qrcode file scanning --}}
            <div id="barcode-scanner-temp" class="hidden"></div>

            {{-- Scan Barcode (capture photo) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="text-center space-y-4">
                    <p class="text-gray-600 text-sm">Take a photo of a product barcode to look it up.</p>

                    {{-- Camera capture button --}}
                    <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-white active:bg-indigo-700 cursor-pointer">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <circle cx="12" cy="13" r="3" stroke-width="2"/>
                        </svg>
                        Scan Barcode
                        <input type="file" accept="image/*" capture="environment" class="hidden" id="camera-input">
                    </label>

                    {{-- Gallery fallback --}}
                    <label class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 active:bg-gray-200 cursor-pointer">
                        Choose from Gallery
                        <input type="file" accept="image/*" class="hidden" id="gallery-input">
                    </label>

                    {{-- Preview --}}
                    <div id="preview-container" class="hidden">
                        <img id="image-preview" class="mx-auto max-h-48 rounded shadow" alt="Preview">
                    </div>

                    {{-- Status --}}
                    <p id="scannerStatus" class="text-sm text-gray-500"></p>
                </div>
            </div>

            {{-- Manual Input --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Manual Barcode Input</h3>
                <form onsubmit="event.preventDefault(); lookupBarcode(document.getElementById('manualBarcode').value);" class="flex gap-3">
                    <input type="text" id="manualBarcode" placeholder="Type or paste barcode..."
                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                        Look Up
                    </button>
                </form>
            </div>

            {{-- Result Display --}}
            <div id="resultCard" class="hidden bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Result</h3>
                <div id="resultContent"></div>
            </div>

            {{-- Scan History --}}
            <div id="historyCard" class="hidden bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Scan History</h3>
                <ul id="historyList" class="space-y-2 text-sm"></ul>
            </div>
        </div>
    </div>

    @vite(['resources/js/barcode-scanner.js'])

    <script>
        const cameraInput = document.getElementById('camera-input');
        const galleryInput = document.getElementById('gallery-input');
        const preview = document.getElementById('image-preview');
        const previewContainer = document.getElementById('preview-container');
        const status = document.getElementById('scannerStatus');

        async function handlePhoto(file) {
            if (!file) return;

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                previewContainer.classList.remove('hidden');
            };
            reader.readAsDataURL(file);

            // Decode barcode from image
            status.textContent = 'Scanning image for barcode...';
            try {
                const result = await window.BarcodeScanner.scanFile(file);
                status.textContent = 'Found: ' + result.text + ' (' + result.format + ')';

                // Beep
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    osc.frequency.value = 1000;
                    osc.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.1);
                } catch (e) {}

                lookupBarcode(result.text);
            } catch (err) {
                const msg = (err && err.message) ? err.message : JSON.stringify(err);
                console.error('Barcode scan error:', err);
                status.textContent = 'No barcode found in image. Try again with a clearer photo.';
            }
        }

        cameraInput.addEventListener('change', (e) => handlePhoto(e.target.files[0]));
        galleryInput.addEventListener('change', (e) => handlePhoto(e.target.files[0]));

        async function lookupBarcode(barcode) {
            if (!barcode || !barcode.trim()) return;
            barcode = barcode.trim();

            const resultCard = document.getElementById('resultCard');
            const resultContent = document.getElementById('resultContent');
            resultCard.classList.remove('hidden');
            resultContent.innerHTML = '<p class="text-gray-500">Looking up ' + escapeHtml(barcode) + '...</p>';

            try {
                const response = await fetch('{{ route("labels.lookup-barcode") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ barcode: barcode }),
                });

                const data = await response.json();

                if (data.success) {
                    resultContent.innerHTML = `
                        <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                            <p class="text-green-800 font-semibold text-lg">${escapeHtml(data.product.name)}</p>
                            <div class="mt-2 space-y-1 text-sm text-green-700">
                                <p><span class="font-medium">Code:</span> ${escapeHtml(data.product.code)}</p>
                                <p><span class="font-medium">Price:</span> ${escapeHtml(data.product.formatted_price)}</p>
                            </div>
                        </div>
                    `;
                } else {
                    resultContent.innerHTML = `
                        <div class="border border-red-200 bg-red-50 rounded-lg p-4">
                            <p class="text-red-700">${escapeHtml(data.message)}</p>
                        </div>
                    `;
                }

                addToHistory(barcode, data);
            } catch (err) {
                resultContent.innerHTML = `
                    <div class="border border-red-200 bg-red-50 rounded-lg p-4">
                        <p class="text-red-700">Error: ${escapeHtml(err.message)}</p>
                    </div>
                `;
            }
        }

        function addToHistory(barcode, data) {
            const historyCard = document.getElementById('historyCard');
            const historyList = document.getElementById('historyList');
            historyCard.classList.remove('hidden');

            const li = document.createElement('li');
            li.className = 'flex justify-between items-center p-2 rounded ' +
                (data.success ? 'bg-green-50' : 'bg-red-50');
            li.innerHTML = `
                <span class="font-mono">${escapeHtml(barcode)}</span>
                <span class="${data.success ? 'text-green-700' : 'text-red-600'} text-sm">
                    ${data.success ? escapeHtml(data.product.name) : 'Not found'}
                </span>
            `;
            historyList.prepend(li);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</x-app-layout>
