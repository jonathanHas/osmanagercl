@props([
    'title' => null,
    'description' => null,
    'actions' => null,
])

@php
    $sizeClasses = [
        'sm' => 'text-xs',
        'default' => 'text-sm',
        'lg' => 'text-base',
    ];
    
    $paddingClasses = [
        'sm' => 'px-4 py-2',
        'default' => 'px-6 py-3',
        'lg' => 'px-8 py-4',
    ];
    
    $textSize = $sizeClasses[$size] ?? $sizeClasses['default'];
    $cellPadding = $paddingClasses[$size] ?? $paddingClasses['default'];
    
    $tableClasses = "min-w-full divide-y divide-gray-200 dark:divide-gray-700";
    if ($striped) {
        $tableClasses .= " divide-y";
    }
@endphp

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg']) }}>
    @if($title || $description || $actions)
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    @if($title)
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                    @endif
                    @if($description)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
                    @endif
                </div>
                @if($actions)
                    <div class="flex space-x-3">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($hasData())
        <div class="overflow-x-auto">
            <table class="{{ $tableClasses }}">
                @if(count($headers) > 0)
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            @if($selectable)
                                <th class="{{ $cellPadding }} w-8">
                                    <input type="checkbox" 
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                           onchange="toggleAllRows(this)">
                                </th>
                            @endif
                            
                            @foreach($headers as $header)
                                <th class="{{ $cellPadding }} text-left {{ $textSize }} font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider {{ $header['class'] }}"
                                    @if($header['width']) style="width: {{ $header['width'] }}" @endif>
                                    
                                    @if($sortable && $header['sortable'])
                                        <button class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-200">
                                            <span>{{ $header['label'] }}</span>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                            </svg>
                                        </button>
                                    @else
                                        {{ $header['label'] }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                @endif

                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @if($slot->isNotEmpty() && isset($row))
                        <!-- Custom row slot provided -->
                        @foreach($rows as $item)
                            <tr class="{{ $hoverable ? 'hover:bg-gray-50 dark:hover:bg-gray-700' : '' }} {{ $striped && $loop->even ? 'bg-gray-50 dark:bg-gray-750' : '' }}">
                                @if($selectable)
                                    <td class="{{ $cellPadding }}">
                                        <input type="checkbox" 
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 row-checkbox"
                                               value="{{ $item->id ?? $loop->index }}">
                                    </td>
                                @endif
                                {{ $row }}
                            </tr>
                        @endforeach
                    @else
                        <!-- Default row rendering -->
                        @foreach($rows as $item)
                            <tr class="{{ $hoverable ? 'hover:bg-gray-50 dark:hover:bg-gray-700' : '' }} {{ $striped && $loop->even ? 'bg-gray-50 dark:bg-gray-750' : '' }}">
                                @if($selectable)
                                    <td class="{{ $cellPadding }}">
                                        <input type="checkbox" 
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 row-checkbox"
                                               value="{{ $item->id ?? $loop->index }}">
                                    </td>
                                @endif
                                
                                @foreach($headers as $header)
                                    <td class="{{ $cellPadding }} {{ $textSize }} text-gray-900 dark:text-gray-100">
                                        @if($header['key'] && is_object($item))
                                            {{ data_get($item, $header['key'], '-') }}
                                        @elseif($header['key'] && is_array($item))
                                            {{ $item[$header['key']] ?? '-' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        @if($isPaginated())
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700">
                {{ $pagination->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="text-center py-12">
            @php
                $emptyIcons = [
                    'table' => 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
                    'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
                    'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                ];
                $iconPath = $emptyIcons[$emptyIcon] ?? $emptyIcons['table'];
            @endphp
            
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $emptyMessage }}</h3>
            
            @isset($emptyAction)
                <div class="mt-6">
                    {{ $emptyAction }}
                </div>
            @endisset
        </div>
    @endif
</div>

@if($selectable)
    @push('scripts')
    <script>
        function toggleAllRows(masterCheckbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = masterCheckbox.checked;
            });
        }
    </script>
    @endpush
@endif