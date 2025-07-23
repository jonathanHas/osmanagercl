@props([
    'title',                   // Card title (required)
    'value',                  // Main value to display (required)
    'subtitle' => null,       // Optional subtitle
    'icon' => 'chart-bar',    // Icon name (predefined set)
    'color' => 'blue',        // Color scheme
    'trend' => null,          // Optional trend indicator (+5%, -2%, etc)
    'trendDirection' => 'up', // up, down, neutral
    'href' => null,          // Optional link
])

@php
    // Define color schemes
    $colorSchemes = [
        'blue' => [
            'bg' => 'bg-blue-100 dark:bg-blue-900',
            'icon' => 'text-blue-600 dark:text-blue-300',
            'border' => 'border-blue-200 dark:border-blue-700',
        ],
        'green' => [
            'bg' => 'bg-green-100 dark:bg-green-900',
            'icon' => 'text-green-600 dark:text-green-300',
            'border' => 'border-green-200 dark:border-green-700',
        ],
        'red' => [
            'bg' => 'bg-red-100 dark:bg-red-900',
            'icon' => 'text-red-600 dark:text-red-300',
            'border' => 'border-red-200 dark:border-red-700',
        ],
        'yellow' => [
            'bg' => 'bg-yellow-100 dark:bg-yellow-900',
            'icon' => 'text-yellow-600 dark:text-yellow-300',
            'border' => 'border-yellow-200 dark:border-yellow-700',
        ],
        'purple' => [
            'bg' => 'bg-purple-100 dark:bg-purple-900',
            'icon' => 'text-purple-600 dark:text-purple-300',
            'border' => 'border-purple-200 dark:border-purple-700',
        ],
        'gray' => [
            'bg' => 'bg-gray-100 dark:bg-gray-700',
            'icon' => 'text-gray-600 dark:text-gray-400',
            'border' => 'border-gray-200 dark:border-gray-600',
        ],
    ];
    
    // Define icon paths
    $icons = [
        'chart-bar' => 'M9 19V6l3-3 3 3v13M9 19H3a2 2 0 01-2-2V9a2 2 0 012-2h6m6 12h6a2 2 0 002-2V9a2 2 0 00-2-2h-6',
        'cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'users' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'shopping-cart' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'truck' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 11-2 0 1 1 0 012 0z',
        'tag' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'clipboard-list' => 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'check-circle' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'x-circle' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    
    $colors = $colorSchemes[$color] ?? $colorSchemes['blue'];
    $iconPath = $icons[$icon] ?? $icons['chart-bar'];
    
    // Format the value if it's numeric
    $formattedValue = is_numeric($value) ? number_format($value) : $value;
    
    // Trend styling
    $trendClasses = [
        'up' => 'text-green-600 dark:text-green-400',
        'down' => 'text-red-600 dark:text-red-400',
        'neutral' => 'text-gray-600 dark:text-gray-400',
    ];
    $trendClass = $trendClasses[$trendDirection] ?? $trendClasses['neutral'];
@endphp

@if($href)
    <a href="{{ $href }}" class="block">
@endif

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg transition-shadow hover:shadow-md']) }}>
    <div class="p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full {{ $colors['bg'] }}">
                <svg class="w-6 h-6 {{ $colors['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"></path>
                </svg>
            </div>
            <div class="ml-4 flex-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $title }}</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $formattedValue }}</p>
                        @if($subtitle)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $subtitle }}</p>
                        @endif
                    </div>
                    @if($trend)
                        <div class="text-right">
                            <div class="flex items-center {{ $trendClass }}">
                                @if($trendDirection === 'up')
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                @elseif($trendDirection === 'down')
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                    </svg>
                                @endif
                                <span class="text-sm font-medium">{{ $trend }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($href)
    </a>
@endif