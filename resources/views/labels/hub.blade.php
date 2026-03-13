<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Label Area</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('labels._nav', ['current' => 'hub'])

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                {{-- Zebra Labels --}}
                <a href="{{ route('labels.zebra') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-blue-300 hover:shadow-md transition group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 group-hover:bg-blue-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Zebra Labels</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Print & manage Zebra printer labels</p>
                            <p class="text-xs text-blue-600 mt-1">{{ $zebraLabelCount }} active {{ Str::plural('label', $zebraLabelCount) }}</p>
                        </div>
                    </div>
                </a>

                {{-- Translate Label --}}
                <a href="{{ route('labels.translate') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-md transition group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Translate Label</h3>
                            <p class="text-xs text-gray-500 mt-0.5">AI-powered label translation with Gemini</p>
                            <p class="text-xs text-indigo-600 mt-1">{{ $translationCount }} saved {{ Str::plural('translation', $translationCount) }}</p>
                        </div>
                    </div>
                </a>

                {{-- Shelf Labels --}}
                <a href="{{ route('labels.shelf-labels') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-green-300 hover:shadow-md transition group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center text-green-600 group-hover:bg-green-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Shelf Labels</h3>
                            <p class="text-xs text-gray-500 mt-0.5">A4 shelf label print queue</p>
                            @if ($needsLabelsCount > 0)
                                <p class="text-xs text-amber-600 mt-1">{{ $needsLabelsCount }} {{ Str::plural('product', $needsLabelsCount) }} needing labels</p>
                            @else
                                <p class="text-xs text-green-600 mt-1">All up to date</p>
                            @endif
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>
</x-admin-layout>
