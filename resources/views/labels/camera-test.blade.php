<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Camera Test - Snap Product Label
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-md mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Debug Panel --}}
            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-300 rounded text-left text-xs font-mono">
                <h3 class="font-bold text-sm mb-2 text-yellow-800">Debug Info</h3>
                <div id="debug-log" class="space-y-1 text-yellow-900"></div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form id="camera-form" action="{{ route('labels.camera-upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="text-center space-y-6">
                        <p class="text-gray-600">Snap a photo of a product label using your camera.</p>

                        {{-- Test 1: capture="environment" (rear camera) --}}
                        <div class="border-2 border-blue-200 rounded-lg p-4 bg-blue-50">
                            <p class="text-xs text-blue-600 mb-2 font-mono">Test 1: input[capture="environment"]</p>
                            <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-indigo-600 rounded-md font-semibold text-white active:bg-indigo-700 cursor-pointer">
                                Rear Camera
                                <input type="file" name="label_image" accept="image/*" capture="environment" class="hidden" data-test="1-rear">
                            </label>
                        </div>

                        {{-- Test 2: capture="user" (front camera) --}}
                        <div class="border-2 border-green-200 rounded-lg p-4 bg-green-50">
                            <p class="text-xs text-green-600 mb-2 font-mono">Test 2: input[capture="user"]</p>
                            <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-green-600 rounded-md font-semibold text-white active:bg-green-700 cursor-pointer">
                                Front Camera
                                <input type="file" name="label_image" accept="image/*" capture="user" class="hidden" data-test="2-front">
                            </label>
                        </div>

                        {{-- Test 3: capture (no value) --}}
                        <div class="border-2 border-purple-200 rounded-lg p-4 bg-purple-50">
                            <p class="text-xs text-purple-600 mb-2 font-mono">Test 3: input[capture] (no value)</p>
                            <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-purple-600 rounded-md font-semibold text-white active:bg-purple-700 cursor-pointer">
                                Camera (default)
                                <input type="file" name="label_image" accept="image/*" capture class="hidden" data-test="3-default">
                            </label>
                        </div>

                        {{-- Test 4: accept="image/*" only, no capture --}}
                        <div class="border-2 border-orange-200 rounded-lg p-4 bg-orange-50">
                            <p class="text-xs text-orange-600 mb-2 font-mono">Test 4: accept="image/*" (no capture)</p>
                            <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-orange-600 rounded-md font-semibold text-white active:bg-orange-700 cursor-pointer">
                                Image Picker (no capture)
                                <input type="file" name="label_image" accept="image/*" class="hidden" data-test="4-nocapture">
                            </label>
                        </div>

                        {{-- Test 5: Visible native input (not hidden) --}}
                        <div class="border-2 border-red-200 rounded-lg p-4 bg-red-50">
                            <p class="text-xs text-red-600 mb-2 font-mono">Test 5: Visible native input[capture="environment"]</p>
                            <input type="file" name="label_image" accept="image/*" capture="environment" class="block w-full text-sm" data-test="5-visible">
                        </div>

                        {{-- Preview of captured/selected image --}}
                        <div id="preview-container" class="hidden">
                            <img id="image-preview" class="mx-auto max-h-64 rounded shadow" alt="Preview">
                            <p class="mt-2 text-sm text-green-600" id="filename-display"></p>
                        </div>

                        <button type="submit" id="btn-upload" disabled
                                class="w-full inline-flex justify-center items-center px-6 py-3 bg-gray-800 border border-transparent rounded-md font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Upload & Process
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const debugLog = document.getElementById('debug-log');
        const preview = document.getElementById('image-preview');
        const previewContainer = document.getElementById('preview-container');
        const filenameDisplay = document.getElementById('filename-display');
        const btnUpload = document.getElementById('btn-upload');

        function log(msg) {
            const line = document.createElement('div');
            const time = new Date().toLocaleTimeString();
            line.textContent = '[' + time + '] ' + msg;
            debugLog.appendChild(line);
            debugLog.scrollTop = debugLog.scrollHeight;
        }

        // Environment info
        log('userAgent: ' + navigator.userAgent);
        log('platform: ' + navigator.platform);
        log('protocol: ' + window.location.protocol);
        log('secure context: ' + window.isSecureContext);
        log('mediaDevices available: ' + !!(navigator.mediaDevices));
        log('getUserMedia available: ' + !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia));

        // Check for touch support (mobile indicator)
        log('touch support: ' + ('ontouchstart' in window));
        log('max touch points: ' + navigator.maxTouchPoints);

        // Enumerate cameras if possible
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices().then(function(devices) {
                const cameras = devices.filter(d => d.kind === 'videoinput');
                log('video devices found: ' + cameras.length);
                cameras.forEach(function(cam, i) {
                    log('  cam[' + i + ']: ' + (cam.label || 'no label') + ' (id: ' + cam.deviceId.substring(0, 8) + '...)');
                });
            }).catch(function(err) {
                log('enumerateDevices error: ' + err.message);
            });
        } else {
            log('enumerateDevices: not available');
        }

        // Listen on all file inputs
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            const testId = input.getAttribute('data-test') || 'unknown';

            input.addEventListener('click', function() {
                log('CLICK on test ' + testId + ' | capture="' + (input.getAttribute('capture') || 'none') + '" | accept="' + (input.getAttribute('accept') || 'none') + '"');
            });

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    log('FILE from test ' + testId + ': ' + file.name + ' | type: ' + file.type + ' | size: ' + (file.size/1024).toFixed(0) + 'KB');

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        preview.src = event.target.result;
                        previewContainer.classList.remove('hidden');
                        filenameDisplay.textContent = 'From test ' + testId + ': ' + file.name + ' (' + (file.size/1024).toFixed(0) + ' KB)';
                        btnUpload.disabled = false;
                    };
                    reader.readAsDataURL(file);

                    // Copy file to the first input (the form submission one)
                    if (testId !== '1-rear') {
                        const mainInput = document.querySelector('input[data-test="1-rear"]');
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        mainInput.files = dt.files;
                        log('Copied file to main input for form submission');
                    }
                } else {
                    log('CANCEL on test ' + testId + ' (no file selected)');
                }
            });
        });
    </script>
</x-admin-layout>
