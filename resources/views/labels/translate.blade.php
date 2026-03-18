<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Label Translation</h2>
            @if (request()->input('from') === 'zebra')
                <a href="{{ route('labels.zebra', ['view' => 'translations']) }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition">Back to Translated Labels</a>
            @else
                <a href="{{ route('labels.translate.history') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition">History</a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
            @include('labels._nav', ['current' => 'translate'])
        </div>
        <div class="max-w-lg mx-auto sm:px-6 lg:px-8" x-data="labelTranslator()" x-cloak>

            {{-- Step Indicator --}}
            <div class="flex items-center justify-center mb-6 gap-1">
                <template x-for="(s, i) in steps" :key="i">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold transition-all duration-300"
                             :class="step > i+1 ? 'bg-green-500 text-white' : (step === i+1 ? 'bg-indigo-600 text-white ring-2 ring-indigo-300' : 'bg-gray-200 text-gray-500')">
                            <template x-if="step > i+1"><span>&#10003;</span></template>
                            <template x-if="step <= i+1"><span x-text="i+1"></span></template>
                        </div>
                        <span class="ml-1 text-xs hidden sm:inline" :class="step === i+1 ? 'text-indigo-700 font-semibold' : 'text-gray-400'" x-text="s"></span>
                        <template x-if="i < steps.length - 1">
                            <div class="w-6 sm:w-10 h-0.5 mx-1" :class="step > i+1 ? 'bg-green-400' : 'bg-gray-200'"></div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- ==================== STEP 1: SCAN BARCODE ==================== --}}
            <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <p class="text-gray-600 text-sm text-center">Scan or enter the product barcode to get started.</p>

                    {{-- Live Scanner --}}
                    <button @click="toggleScanner()" type="button"
                        class="w-full inline-flex justify-center items-center px-6 py-4 border border-transparent rounded-md font-semibold text-white transition"
                        :class="scannerRunning ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <circle cx="12" cy="13" r="3" stroke-width="2"/>
                        </svg>
                        <span x-text="scannerRunning ? 'Stop Scanner' : 'Start Scanner'"></span>
                    </button>

                    <div id="scanner-container" x-show="scannerVisible" class="rounded-lg overflow-hidden">
                        <div id="scanner" class="w-full"></div>
                    </div>

                    <p class="text-sm text-center" :class="scannerStatus.includes('error') || scannerStatus.includes('Error') ? 'text-red-500' : 'text-gray-500'" x-text="scannerStatus"></p>

                    {{-- Manual Input --}}
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                        <div class="relative flex justify-center text-xs"><span class="px-2 bg-white text-gray-400">or enter manually</span></div>
                    </div>

                    <form @submit.prevent="onManualBarcode()" class="flex gap-2">
                        <input type="text" x-model="manualBarcode" placeholder="Type barcode..."
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            inputmode="numeric">
                        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800 text-sm font-medium transition">
                            Look Up
                        </button>
                    </form>

                    <div x-show="lookupError" class="p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-sm" x-text="lookupError"></div>
                    <div x-show="lookupLoading" class="text-center py-2">
                        <svg class="animate-spin h-5 w-5 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </div>
                </div>
            </div>

            {{-- ==================== STEP 2: PRODUCT DECISION ==================== --}}
            <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-4">
                    {{-- Product Info Card --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <template x-if="product">
                            <div>
                                <p class="text-lg font-semibold text-gray-900" x-text="product.name"></p>
                                <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    <div><span class="text-gray-500">Barcode</span><p class="font-mono" x-text="barcode"></p></div>
                                    <div><span class="text-gray-500">Price</span><p class="font-semibold" x-text="product.formatted_price"></p></div>
                                    <div><span class="text-gray-500">Category</span><p x-text="product.category"></p></div>
                                    <div x-show="product.supplier"><span class="text-gray-500">Supplier</span><p x-text="product.supplier"></p></div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!product">
                            <div class="text-center">
                                <p class="text-amber-700 font-medium">Product not found in system</p>
                                <p class="text-sm text-gray-500 mt-1">Barcode: <span class="font-mono" x-text="barcode"></span></p>
                                <p class="text-xs text-gray-400 mt-1">You can still translate the label without linking to a product.</p>
                            </div>
                        </template>
                    </div>

                    {{-- Existing Translation --}}
                    <template x-if="existingTranslation">
                        <div class="bg-blue-50 border border-blue-200 sm:rounded-lg p-5 space-y-3">
                            <p class="text-blue-800 font-medium text-sm">
                                <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                Existing translation found <span class="font-normal text-blue-600" x-text="'(' + existingTranslation.created_at + ')'"></span>
                            </p>
                            <div class="flex flex-col gap-2">
                                <button @click="loadExistingTranslation()" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                                    View Existing Translation
                                </button>
                                <button @click="goToPrint()" class="w-full px-4 py-2.5 bg-green-600 text-white rounded-md text-sm font-semibold hover:bg-green-700 transition">
                                    Print Labels
                                </button>
                                <button @click="step = 3" class="w-full px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition">
                                    Create New Translation
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- No Translation --}}
                    <template x-if="!existingTranslation">
                        <div class="flex flex-col gap-2">
                            <button @click="step = 3" class="w-full px-4 py-3 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition">
                                Translate Label
                            </button>
                            <button @click="resetAll()" class="w-full px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition">
                                Scan Different Product
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ==================== STEP 3: CAPTURE PHOTOS ==================== --}}
            <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-4">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        <p class="text-gray-600 text-sm text-center">Take photos of the product label. Multiple photos can capture different sides.</p>

                        {{-- Camera Button --}}
                        <label class="w-full inline-flex justify-center items-center px-6 py-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-white active:bg-indigo-700 cursor-pointer">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-width="2"/></svg>
                            Take Photo
                            <input type="file" accept="image/*" capture="environment" class="hidden" @change="addPhoto($event)">
                        </label>

                        <div class="relative">
                            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                            <div class="relative flex justify-center text-xs"><span class="px-2 bg-white text-gray-400">or</span></div>
                        </div>

                        <label class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 active:bg-gray-200 cursor-pointer">
                            Choose from Gallery
                            <input type="file" accept="image/*" multiple class="hidden" @change="addPhotos($event)">
                        </label>

                        {{-- Photo Thumbnails --}}
                        <div x-show="photos.length > 0" class="space-y-2">
                            <p class="text-xs font-medium text-gray-500" x-text="photos.length + ' photo' + (photos.length !== 1 ? 's' : '') + ' captured'"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(photo, idx) in photos" :key="idx">
                                    <div class="relative group">
                                        <img :src="photo.preview" class="w-20 h-20 object-cover rounded-lg border shadow-sm">
                                        <button @click="photos.splice(idx, 1)" type="button"
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center shadow hover:bg-red-600">
                                            &times;
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Label Size Selector --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 mb-2">Label Size</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($labelSizes as $key => $size)
                                <button type="button"
                                    @click="labelSize = '{{ $key }}'"
                                    class="flex-1 min-w-0 px-3 py-2 rounded-md border-2 text-sm font-medium transition text-center"
                                    :class="labelSize === '{{ $key }}' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                    {{ $size['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-2">
                        <button @click="step = product ? 2 : 1" class="px-4 py-3 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition">
                            Back
                        </button>
                        <button @click="uploadPhotos()" :disabled="photos.length === 0"
                            class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Upload & Translate
                        </button>
                    </div>
                </div>
            </div>

            {{-- ==================== STEP 4: REVIEW & EDIT ==================== --}}
            <div x-show="step === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Processing Overlay --}}
                <template x-if="processing">
                    <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center space-y-4">
                        <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="text-indigo-700 font-semibold" x-text="processingStatus"></p>
                        <p class="text-xs text-gray-400">This may take up to 30 seconds</p>
                    </div>
                </template>

                {{-- Processing Error --}}
                <template x-if="processingError && !processing">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-red-700 font-medium">Translation Failed</p>
                            <p class="text-red-600 text-sm mt-1" x-text="processingError"></p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="step = 3; processingError = null" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition">Try Again</button>
                            <button @click="resetAll()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition">Start Over</button>
                        </div>
                    </div>
                </template>

                {{-- Save Success Panel --}}
                <template x-if="saved && !processing">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                        <div class="text-center space-y-2">
                            <div class="mx-auto w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-green-700 font-semibold text-lg">Translation saved!</p>
                            <p class="text-gray-600 text-sm" x-text="labelData?.product_name || ''"></p>
                            <p x-show="barcode" class="text-gray-400 text-xs font-mono" x-text="barcode"></p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <button @click="resetAll()" class="w-full px-4 py-3 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition">
                                Scan New Product
                            </button>
                            <a href="{{ route('labels.translate.history') }}" class="w-full px-4 py-2.5 bg-green-600 text-white rounded-md text-sm font-semibold hover:bg-green-700 transition text-center">
                                View in History
                            </a>
                            <button @click="saved = false" class="w-full px-4 py-2 text-gray-500 text-sm hover:text-gray-700 transition">
                                &larr; Continue Editing
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Review Content --}}
                <template x-if="labelData && !processing && !processingError && !saved">
                    <div class="space-y-4">
                        {{-- Product / Barcode reference --}}
                        <div x-show="barcode || product" class="flex items-center gap-2 px-4 text-xs text-gray-400">
                            <span x-show="product" x-text="product?.name"></span>
                            <span x-show="product && barcode">&middot;</span>
                            <span x-show="barcode" class="font-mono" x-text="barcode"></span>
                        </div>

                        {{-- Original Photos (clickable, open in new window) --}}
                        <div x-show="originalPhotos.length > 0" class="bg-white shadow-sm sm:rounded-lg p-4">
                            <p class="text-xs font-medium text-gray-500 mb-2">Original Photos <span class="text-gray-400">(tap to enlarge)</span></p>
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                <template x-for="(photo, idx) in originalPhotos" :key="idx">
                                    <a href="#" @click.prevent="window.open(photo.startsWith('data:') ? photo : '/storage/' + photo, 'photo_' + idx, 'width=800,height=600,scrollbars=yes,resizable=yes')" class="flex-shrink-0 cursor-pointer">
                                        <img :src="photo.startsWith('data:') ? photo : '/storage/' + photo" class="h-24 rounded-lg border shadow-sm hover:ring-2 hover:ring-indigo-400 transition">
                                    </a>
                                </template>
                            </div>
                        </div>

                        {{-- Label Preview --}}
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <p class="text-xs font-medium text-gray-500 mb-2">Label Preview</p>
                            <div class="text-center min-h-[100px] flex items-center justify-center bg-gray-50 rounded">
                                <img x-show="previewSrc" :src="previewSrc" class="max-h-48 rounded border" alt="Label preview">
                                <p x-show="!previewSrc && !previewError" class="text-gray-400 text-sm">Generating preview...</p>
                                <p x-show="previewError" class="text-red-500 text-sm" x-text="previewError"></p>
                            </div>
                        </div>

                        {{-- ZPL Code (collapsible) --}}
                        <div class="bg-gray-50 border border-gray-200 sm:rounded-lg p-4" x-data="{ showZpl: false }">
                            <button @click="showZpl = !showZpl" class="flex items-center justify-between w-full text-left">
                                <p class="text-xs font-medium text-gray-600">ZPL Code</p>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="showZpl && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="showZpl" x-collapse>
                                <pre class="mt-2 text-xs text-gray-700 bg-white border border-gray-200 rounded p-3 overflow-x-auto max-h-64 overflow-y-auto whitespace-pre-wrap break-all" x-text="zplContent"></pre>
                            </div>
                        </div>

                        {{-- Label Size + Font Scale --}}
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                            <div>
                                <p class="text-xs font-medium text-gray-500 mb-2">Label Size</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($labelSizes as $key => $size)
                                        <button type="button"
                                            @click="labelSize = '{{ $key }}'; regenerate()"
                                            class="flex-1 min-w-0 px-3 py-1.5 rounded-md border-2 text-xs font-medium transition text-center"
                                            :class="labelSize === '{{ $key }}' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
                                            {{ $size['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 mb-2">Text Size</p>
                                <div class="flex items-center gap-2">
                                    <button @click="adjustFontScale(-10)" class="px-2 py-0.5 bg-gray-200 rounded text-xs font-bold text-gray-700 hover:bg-gray-300">A-</button>
                                    <input type="range" min="50" max="200" step="10" x-model.number="fontScale"
                                        @change="regenerate()" class="flex-1 accent-indigo-600 h-1.5">
                                    <button @click="adjustFontScale(10)" class="px-2 py-0.5 bg-gray-200 rounded text-xs font-bold text-gray-700 hover:bg-gray-300">A+</button>
                                    <span class="text-xs text-gray-500 w-12 text-right" x-text="fontScale + '%'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Original Text (for verification) --}}
                        <div x-show="labelData.original_text" class="bg-amber-50 border border-amber-200 sm:rounded-lg p-4" x-data="{ showOriginal: false }">
                            <button @click="showOriginal = !showOriginal" class="flex items-center justify-between w-full text-left">
                                <p class="text-xs font-medium text-amber-700">Original Label Text (for checking translation)</p>
                                <svg class="w-4 h-4 text-amber-500 transition-transform" :class="showOriginal && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="showOriginal" x-collapse>
                                <p class="mt-2 text-sm text-amber-900 whitespace-pre-wrap leading-relaxed" x-text="labelData.original_text"></p>
                            </div>
                        </div>

                        {{-- Editable Label Data --}}
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <p class="text-xs font-medium text-gray-500 mb-3">Label Data</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Product Name</label>
                                    <input type="text" x-model="labelData.product_name" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Ingredients</label>
                                    <textarea x-model="labelData.ingredients" rows="4" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Nutrition (inline)</label>
                                    <textarea x-model="labelData.nutrition_inline" rows="2" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Storage</label>
                                    <input type="text" x-model="labelData.storage" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
                                        <input type="text" x-model="labelData.address" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Origin</label>
                                        <input type="text" x-model="labelData.origin" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                </div>
                                <button @click="regenerate()" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition">
                                    Regenerate Label
                                </button>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-col gap-2">
                            <button @click="saveAndPrint()" :disabled="saving"
                                class="w-full px-4 py-3 bg-green-600 text-white rounded-md text-sm font-semibold hover:bg-green-700 transition disabled:opacity-50">
                                <span x-show="!saving">Save & Print</span>
                                <span x-show="saving">Saving...</span>
                            </button>
                            <button @click="saveTranslation()" :disabled="saving"
                                class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50">
                                <span x-show="!saving">Save Translation</span>
                                <span x-show="saving">Saving...</span>
                            </button>
                            <button @click="resetAll()" class="w-full px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition">
                                Start Over
                            </button>
                        </div>

                        {{-- Status Message --}}
                        <div x-show="saveMessage" class="p-3 rounded-md text-sm"
                             :class="saveSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'"
                             x-text="saveMessage"
                             x-transition></div>
                    </div>
                </template>
            </div>

            {{-- ==================== STEP 5: PRINT ==================== --}}
            <div x-show="step === 5" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-4">
                    {{-- Product Summary --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <p class="font-semibold text-gray-900" x-text="labelData?.product_name || 'Label'"></p>
                        <p x-show="barcode" class="text-sm text-gray-500 font-mono" x-text="barcode"></p>
                    </div>

                    {{-- Label Preview --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-center bg-gray-50 rounded p-2">
                            <img x-show="previewSrc" :src="previewSrc" class="max-h-40 mx-auto rounded border" alt="Label">
                        </div>
                    </div>

                    {{-- Print Controls --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        {{-- Label Size Reminder --}}
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-800">
                            <span class="font-medium">Label loaded:</span>
                            <span x-text="labelSizeConfig[labelSize]?.label || labelSize"></span>
                        </div>

                        {{-- Number of copies --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Number of copies</label>
                            <div class="flex items-center gap-3">
                                <button @click="copies = Math.max(1, copies - 1)" class="w-10 h-10 flex items-center justify-center bg-gray-200 rounded-md text-lg font-bold text-gray-700 hover:bg-gray-300">&minus;</button>
                                <input type="number" x-model.number="copies" min="1" max="100"
                                    class="w-20 text-center rounded-md border-gray-300 text-lg font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                                <button @click="copies = Math.min(100, copies + 1)" class="w-10 h-10 flex items-center justify-center bg-gray-200 rounded-md text-lg font-bold text-gray-700 hover:bg-gray-300">+</button>
                            </div>
                        </div>

                        {{-- Print Button --}}
                        <button @click="printLabels()" :disabled="printing"
                            class="w-full px-6 py-4 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 transition disabled:opacity-50 flex items-center justify-center">
                            <svg x-show="!printing" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span x-text="printing ? 'Printing...' : 'Print ' + copies + ' Label' + (copies !== 1 ? 's' : '')"></span>
                        </button>

                        {{-- Print Result --}}
                        <div x-show="printMessage" class="p-3 rounded-md text-sm"
                             :class="printSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'"
                             x-text="printMessage" x-transition></div>
                    </div>

                    {{-- Next Actions --}}
                    <div class="flex flex-col gap-2">
                        <button @click="resetAll()" class="w-full px-4 py-3 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 transition">
                            Scan Another Product
                        </button>
                        <button @click="step = 4" class="w-full px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition">
                            Back to Review
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @vite(['resources/js/barcode-scanner.js', 'resources/js/zpl-preview.js'])

    <script>
        // Must be a regular script (not module) so Alpine.data() is registered
        // synchronously before Alpine initializes and evaluates x-data attributes.

        const csrfToken = '{{ csrf_token() }}';
        const labelSizeConfig = @json($labelSizes);

        // Label dimensions for WASM renderer (px → mm at 12 dpmm)
        const labelDims = {};
        for (const [key, size] of Object.entries(labelSizeConfig)) {
            labelDims[key] = { w: size.widthMm, h: size.heightMm };
        }

        // ZPL renderer — lazy-loaded from window.ZplPreview (set by the vite module)
        let _zplRenderer = null;
        async function getZplRenderer() {
            if (_zplRenderer) return _zplRenderer;
            // 9MB WASM bundle — allow up to 15s on slow/VPN connections
            for (let i = 0; i < 150 && !window.ZplPreview; i++) {
                await new Promise(r => setTimeout(r, 100));
            }
            _zplRenderer = window.ZplPreview;
            if (!_zplRenderer) throw new Error('ZPL renderer not available — try refreshing the page');
            return _zplRenderer;
        }

        async function renderPreview(zpl, size) {
            const renderer = await getZplRenderer();
            const dims = labelDims[size] || labelDims.large;
            const base64 = await renderer.renderToBase64(zpl, dims.w, dims.h);
            return 'data:image/png;base64,' + base64;
        }

        // Edit mode: pre-load existing translation
        const editTranslation = @json($editTranslation);

        document.addEventListener('alpine:init', () => {
            Alpine.data('labelTranslator', () => ({
                steps: ['Scan', 'Product', 'Photos', 'Review', 'Print'],
                step: editTranslation ? 4 : 1,

                // Scanner
                scannerRunning: false,
                scannerVisible: false,
                scannerStatus: 'Ready to scan',
                manualBarcode: '',
                barcode: editTranslation?.product_code || '',
                product: null,
                lookupError: '',
                lookupLoading: false,

                // Translation
                existingTranslation: null,
                translationId: editTranslation?.id || null,

                // Photos
                photos: [],
                originalPhotos: editTranslation?.original_photos || [],

                // Processing
                processing: false,
                processingStatus: '',
                processingError: null,

                // Label data
                labelData: editTranslation?.label_data || null,
                zplContent: editTranslation?.zpl_content || '',
                labelSize: editTranslation?.label_size || 'large',
                fontScale: editTranslation ? Math.round(editTranslation.font_scale * 100) : 100,
                previewSrc: null,
                previewError: null,
                labelSizeConfig: labelSizeConfig,

                // Save
                saving: false,
                saveMessage: '',
                saveSuccess: false,
                saved: false,

                // Print
                copies: 1,
                printing: false,
                printMessage: '',
                printSuccess: false,

                init() {
                    // If editing, render preview
                    if (editTranslation && this.zplContent) {
                        this.renderCurrentPreview();
                    }
                },

                // === SCANNER ===
                async toggleScanner() {
                    if (this.scannerRunning) {
                        await window.BarcodeScanner.stopScanner();
                        this.scannerRunning = false;
                        this.scannerVisible = false;
                        this.scannerStatus = 'Scanner stopped';
                    } else {
                        // Show the container FIRST so html5-qrcode can measure it
                        this.scannerVisible = true;
                        this.scannerStatus = 'Starting camera...';

                        // Wait one tick for Alpine to render the container
                        await this.$nextTick();

                        try {
                            await window.BarcodeScanner.startScanner('scanner', (text, result) => this.onBarcodeDetected(text, result));
                            this.scannerRunning = true;
                            this.scannerStatus = 'Scanning... point camera at barcode';
                        } catch (err) {
                            this.scannerVisible = false;
                            this.scannerStatus = 'Camera error: ' + (err?.message || JSON.stringify(err));
                        }
                    }
                },

                onBarcodeDetected(text, result) {
                    // Beep
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        osc.frequency.value = 1000;
                        osc.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.1);
                    } catch (e) {}

                    this.scannerStatus = 'Detected: ' + text;
                    this.stopScannerIfRunning();
                    this.lookupProduct(text);
                },

                async stopScannerIfRunning() {
                    if (this.scannerRunning) {
                        await window.BarcodeScanner.stopScanner();
                        this.scannerRunning = false;
                        this.scannerVisible = false;
                    }
                },

                onManualBarcode() {
                    if (this.manualBarcode.trim()) {
                        this.stopScannerIfRunning();
                        this.lookupProduct(this.manualBarcode.trim());
                    }
                },

                async lookupProduct(code) {
                    this.barcode = code;
                    this.lookupError = '';
                    this.lookupLoading = true;
                    this.product = null;
                    this.existingTranslation = null;

                    try {
                        // Lookup product in POS
                        const prodRes = await fetch('{{ route("labels.lookup-barcode") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ barcode: code }),
                        });
                        const prodData = await prodRes.json();

                        if (prodData.success) {
                            this.product = prodData.product;
                            this.copies = prodData.product.case_units || 1;
                        }

                        // Check for existing translation
                        const transRes = await fetch('{{ route("labels.translate.check") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ barcode: code }),
                        });
                        const transData = await transRes.json();

                        if (transData.exists) {
                            this.existingTranslation = transData.translation;
                        }

                        this.lookupLoading = false;
                        this.step = 2;

                        // Auto-advance if no existing translation and product not found
                        if (!this.existingTranslation && !this.product) {
                            // Stay on step 2 so user sees "not found" message
                        }

                    } catch (err) {
                        this.lookupLoading = false;
                        this.lookupError = 'Lookup failed: ' + err.message;
                    }
                },

                // === EXISTING TRANSLATION ===
                async loadExistingTranslation() {
                    const t = this.existingTranslation;
                    this.labelData = { ...t.label_data };
                    this.zplContent = t.zpl_content;
                    this.labelSize = t.label_size;
                    this.fontScale = Math.round(t.font_scale * 100);
                    this.originalPhotos = t.original_photos || [];
                    this.translationId = t.id;
                    this.step = 4;
                    await this.renderCurrentPreview();
                },

                goToPrint() {
                    const t = this.existingTranslation;
                    this.labelData = { ...t.label_data };
                    this.zplContent = t.zpl_content;
                    this.labelSize = t.label_size;
                    this.fontScale = Math.round(t.font_scale * 100);
                    this.translationId = t.id;
                    this.step = 5;
                    this.renderCurrentPreview();
                },

                // === PHOTOS ===
                addPhoto(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.addFileToPhotos(file);
                    event.target.value = '';
                },

                addPhotos(event) {
                    for (const file of event.target.files) {
                        this.addFileToPhotos(file);
                    }
                    event.target.value = '';
                },

                addFileToPhotos(file) {
                    const maxDim = 1600;
                    const quality = 0.85;

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = new Image();
                        img.onload = () => {
                            // Skip resize if already small enough
                            if (img.width <= maxDim && img.height <= maxDim) {
                                this.photos.push({ file, preview: e.target.result });
                                return;
                            }

                            const scale = maxDim / Math.max(img.width, img.height);
                            const canvas = document.createElement('canvas');
                            canvas.width = Math.round(img.width * scale);
                            canvas.height = Math.round(img.height * scale);
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                            canvas.toBlob((blob) => {
                                const resizedFile = new File([blob], file.name, { type: 'image/jpeg' });
                                const preview = canvas.toDataURL('image/jpeg', quality);
                                this.photos.push({ file: resizedFile, preview });
                            }, 'image/jpeg', quality);
                        };
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },

                // === UPLOAD & TRANSLATE ===
                async uploadPhotos() {
                    this.step = 4;
                    this.processing = true;
                    this.processingError = null;
                    this.processingStatus = 'Uploading photos...';

                    const formData = new FormData();
                    this.photos.forEach((p) => {
                        formData.append('label_images[]', p.file);
                    });
                    formData.append('label_size', this.labelSize);
                    if (this.barcode) formData.append('product_code', this.barcode);
                    if (this.product) formData.append('product_id', this.product.id);

                    try {
                        this.processingStatus = 'Waiting for AI translation...';
                        const res = await fetch('{{ route("labels.translate.upload") }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: formData,
                        });
                        const data = await res.json();

                        if (!data.success) {
                            this.processingError = data.message || 'Translation failed.';
                            this.processing = false;
                            return;
                        }

                        this.processingStatus = 'Generating label preview...';
                        this.labelData = data.label_data;
                        this.zplContent = data.zpl_content;
                        this.fontScale = Math.round(data.font_scale * 100);
                        this.labelSize = data.label_size;
                        this.originalPhotos = this.photos.map(p => p.preview);
                        this.storedPhotoPaths = data.original_photos;

                        await this.renderCurrentPreview();
                        this.processing = false;

                    } catch (err) {
                        this.processingError = 'Request failed: ' + err.message;
                        this.processing = false;
                    }
                },

                // === PREVIEW ===
                async renderCurrentPreview() {
                    this.previewError = null;
                    try {
                        this.previewSrc = await renderPreview(this.zplContent, this.labelSize);
                    } catch (err) {
                        this.previewError = 'Preview failed: ' + err.message;
                    }
                },

                // === REGENERATE ===
                async regenerate() {
                    if (!this.labelData) return;
                    try {
                        const res = await fetch('{{ route("labels.regenerate-zpl") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                label_data: this.labelData,
                                label_size: this.labelSize,
                                font_scale: this.fontScale / 100,
                            }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.zplContent = data.zpl;
                            if (data.font_scale !== undefined) {
                                this.fontScale = Math.round(data.font_scale * 100);
                            }
                            await this.renderCurrentPreview();
                        }
                    } catch (err) {
                        console.error('Regeneration failed:', err);
                    }
                },

                adjustFontScale(delta) {
                    this.fontScale = Math.max(50, Math.min(200, this.fontScale + delta));
                    this.regenerate();
                },

                // === SAVE ===
                async saveTranslation() {
                    this.saving = true;
                    this.saveMessage = '';

                    try {
                        const res = await fetch('{{ route("labels.translate.save") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                label_data: this.labelData,
                                label_size: this.labelSize,
                                font_scale: this.fontScale / 100,
                                zpl_content: this.zplContent,
                                original_photos: this.storedPhotoPaths || this.existingTranslation?.original_photos || [],
                                product_id: this.product?.id || null,
                                product_code: this.barcode || null,
                                translation_id: this.translationId,
                            }),
                        });
                        const data = await res.json();
                        this.saveSuccess = data.success;
                        if (data.translation_id) this.translationId = data.translation_id;
                        if (data.success) {
                            this.saved = true;
                            this.saveMessage = '';
                        } else {
                            this.saveMessage = data.message || 'Save failed';
                            setTimeout(() => { this.saveMessage = ''; }, 4000);
                        }
                    } catch (err) {
                        this.saveMessage = 'Save failed: ' + err.message;
                        this.saveSuccess = false;
                        setTimeout(() => { this.saveMessage = ''; }, 4000);
                    }
                    this.saving = false;
                },

                async saveAndPrint() {
                    await this.saveTranslation();
                    if (this.saveSuccess) {
                        this.saved = false;
                        this.step = 5;
                    }
                },

                // === PRINT ===
                async printLabels() {
                    this.printing = true;
                    this.printMessage = '';

                    // Build ZPL for N copies by repeating the ZPL
                    let zpl = '';
                    for (let i = 0; i < this.copies; i++) {
                        zpl += this.zplContent;
                    }

                    try {
                        const res = await fetch('{{ route("labels.print-zpl") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ zpl }),
                        });
                        const data = await res.json();
                        this.printMessage = data.success ? 'Print job sent!' : ('Print failed: ' + (data.output || data.message));
                        this.printSuccess = data.success;
                    } catch (err) {
                        this.printMessage = 'Print failed: ' + err.message;
                        this.printSuccess = false;
                    }
                    this.printing = false;
                },

                // === RESET ===
                resetAll() {
                    this.stopScannerIfRunning();
                    this.step = 1;
                    this.barcode = '';
                    this.manualBarcode = '';
                    this.product = null;
                    this.existingTranslation = null;
                    this.translationId = null;
                    this.photos = [];
                    this.originalPhotos = [];
                    this.storedPhotoPaths = null;
                    this.processing = false;
                    this.processingStatus = '';
                    this.processingError = null;
                    this.labelData = null;
                    this.zplContent = '';
                    this.fontScale = 100;
                    this.previewSrc = null;
                    this.previewError = null;
                    this.saving = false;
                    this.saveMessage = '';
                    this.saved = false;
                    this.printing = false;
                    this.printMessage = '';
                    this.copies = 1;
                    this.lookupError = '';
                    this.lookupLoading = false;
                    this.scannerStatus = 'Ready to scan';
                },
            }));
        });
    </script>
</x-admin-layout>
