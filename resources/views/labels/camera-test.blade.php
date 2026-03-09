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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form id="camera-form" action="{{ route('labels.camera-upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" id="file-input" name="label_image" accept="image/*" capture="environment" class="hidden">

                    <div class="text-center space-y-4">
                        <p class="text-gray-600">Snap a photo of a product label using your camera.</p>

                        {{-- Live camera viewfinder --}}
                        <div id="camera-container" class="hidden relative">
                            <video id="camera-stream" autoplay playsinline class="w-full rounded shadow bg-black"></video>
                            <button type="button" id="btn-capture"
                                    class="mt-3 inline-flex items-center px-6 py-3 bg-red-600 border border-transparent rounded-full font-semibold text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                                Take Photo
                            </button>
                        </div>

                        {{-- Preview of captured/selected image --}}
                        <div id="preview-container" class="hidden">
                            <img id="image-preview" class="mx-auto max-h-64 rounded shadow" alt="Preview">
                            <button type="button" id="btn-retake"
                                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-800 underline">
                                Retake
                            </button>
                        </div>

                        {{-- Hidden canvas for capturing frame --}}
                        <canvas id="capture-canvas" class="hidden"></canvas>

                        {{-- Action buttons --}}
                        <div class="space-y-3">
                            <button type="button" id="btn-open-camera"
                                    class="w-full inline-flex justify-center items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-width="2"/></svg>
                                Open Camera
                            </button>

                            <div class="text-gray-400 text-sm">or</div>

                            <button type="button" id="btn-choose-file"
                                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                                Choose from Files
                            </button>
                        </div>

                        <button type="submit" id="btn-upload" disabled
                                class="w-full inline-flex justify-center items-center px-6 py-3 bg-gray-800 border border-transparent rounded-md font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Upload & Process
                        </button>

                        <p id="camera-error" class="hidden text-red-600 text-sm"></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const video = document.getElementById('camera-stream');
        const canvas = document.getElementById('capture-canvas');
        const preview = document.getElementById('image-preview');
        const fileInput = document.getElementById('file-input');
        const btnOpenCamera = document.getElementById('btn-open-camera');
        const btnCapture = document.getElementById('btn-capture');
        const btnRetake = document.getElementById('btn-retake');
        const btnChooseFile = document.getElementById('btn-choose-file');
        const btnUpload = document.getElementById('btn-upload');
        const cameraContainer = document.getElementById('camera-container');
        const previewContainer = document.getElementById('preview-container');
        const cameraError = document.getElementById('camera-error');

        let stream = null;
        const hasGetUserMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        // If getUserMedia not available (no HTTPS), hide "Open Camera" and show
        // the file input directly — on mobile, capture="environment" still opens the camera
        if (!hasGetUserMedia) {
            btnOpenCamera.textContent = 'Take Photo';
            btnOpenCamera.addEventListener('click', function() {
                fileInput.setAttribute('capture', 'environment');
                fileInput.click();
            });
        } else {
            // Open live camera viewfinder
            btnOpenCamera.addEventListener('click', async function() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }
                    });
                    video.srcObject = stream;
                    cameraContainer.classList.remove('hidden');
                    btnOpenCamera.classList.add('hidden');
                    btnChooseFile.classList.add('hidden');
                    previewContainer.classList.add('hidden');
                    cameraError.classList.add('hidden');
                } catch (err) {
                    // Fallback: open file input with capture instead
                    fileInput.setAttribute('capture', 'environment');
                    fileInput.click();
                }
            });
        }

        // Capture photo from stream
        btnCapture.addEventListener('click', function() {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob(function(blob) {
                const file = new File([blob], 'camera-capture.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;

                preview.src = canvas.toDataURL('image/jpeg');
                previewContainer.classList.remove('hidden');
                cameraContainer.classList.add('hidden');
                btnUpload.disabled = false;

                stopCamera();
            }, 'image/jpeg', 0.9);
        });

        // Retake
        btnRetake.addEventListener('click', function() {
            previewContainer.classList.add('hidden');
            btnUpload.disabled = true;
            fileInput.value = '';
            btnOpenCamera.classList.remove('hidden');
            btnChooseFile.classList.remove('hidden');
        });

        // Choose file fallback (no capture — picks from gallery/files)
        btnChooseFile.addEventListener('click', function() {
            fileInput.removeAttribute('capture');
            fileInput.click();
        });

        // File input change (from Take Photo or Choose File)
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.src = event.target.result;
                    previewContainer.classList.remove('hidden');
                    cameraContainer.classList.add('hidden');
                    btnOpenCamera.classList.add('hidden');
                    btnChooseFile.classList.add('hidden');
                    btnUpload.disabled = false;
                };
                reader.readAsDataURL(file);
            }
        });

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        }

        window.addEventListener('beforeunload', stopCamera);
    </script>
</x-admin-layout>
