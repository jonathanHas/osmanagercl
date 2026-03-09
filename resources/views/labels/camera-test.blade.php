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
                    <div class="text-center space-y-6">
                        <p class="text-gray-600">Snap a photo of a product label using your camera.</p>

                        {{-- Take Photo: label wraps the real file input so the tap goes directly to the input --}}
                        <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-white active:bg-indigo-700 cursor-pointer">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-width="2"/></svg>
                            Take Photo
                            <input type="file" id="camera-input" name="label_image" accept="image/*" capture="environment" class="hidden">
                        </label>

                        {{-- Or pick from gallery --}}
                        <div>
                            <span class="text-gray-400 text-sm">or</span>
                        </div>

                        <label class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 active:bg-gray-200 cursor-pointer">
                            Choose from Gallery
                            <input type="file" id="gallery-input" accept="image/*" class="hidden">
                        </label>

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
        const cameraInput = document.getElementById('camera-input');
        const galleryInput = document.getElementById('gallery-input');
        const preview = document.getElementById('image-preview');
        const previewContainer = document.getElementById('preview-container');
        const filenameDisplay = document.getElementById('filename-display');
        const btnUpload = document.getElementById('btn-upload');

        function showPreview(file, input) {
            if (!file) return;

            // If picked via gallery, copy to the camera input (which is the form's named input)
            if (input === galleryInput) {
                const dt = new DataTransfer();
                dt.items.add(file);
                cameraInput.files = dt.files;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                preview.src = event.target.result;
                previewContainer.classList.remove('hidden');
                filenameDisplay.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
                btnUpload.disabled = false;
            };
            reader.readAsDataURL(file);
        }

        cameraInput.addEventListener('change', function(e) {
            showPreview(e.target.files[0], cameraInput);
        });

        galleryInput.addEventListener('change', function(e) {
            showPreview(e.target.files[0], galleryInput);
        });
    </script>
</x-admin-layout>
