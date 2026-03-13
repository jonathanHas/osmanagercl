@php
    $current = $current ?? '';
@endphp
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="{{ route('labels.index') }}"
       class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $current === 'hub' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Label Area
    </a>
    <a href="{{ route('labels.zebra') }}"
       class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $current === 'zebra' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Zebra Labels
    </a>
    <a href="{{ route('labels.translate') }}"
       class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $current === 'translate' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Translate Label
    </a>
    <a href="{{ route('labels.shelf-labels') }}"
       class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $current === 'shelf' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Shelf Labels
    </a>
</div>
