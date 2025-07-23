@props([
    'actions' => [],     // Array of action configurations
    'size' => 'default', // sm, default, lg
    'spacing' => 'default', // tight, default, loose
])

@php
    // Size configurations
    $sizeClasses = [
        'sm' => 'text-xs px-2 py-1',
        'default' => 'text-sm px-3 py-1',
        'lg' => 'text-base px-4 py-2',
    ];
    
    // Spacing configurations
    $spacingClasses = [
        'tight' => 'gap-1',
        'default' => 'gap-2', 
        'loose' => 'gap-3',
    ];
    
    // Color schemes for different action types
    $colorSchemes = [
        'primary' => 'text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600',
        'secondary' => 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300',
        'success' => 'text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300',
        'danger' => 'text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300',
        'warning' => 'text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300',
        'info' => 'text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300',
        
        // Legacy color mappings for backward compatibility
        'indigo' => 'text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600',
        'blue' => 'text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300',
        'green' => 'text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300',
        'red' => 'text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300',
        'yellow' => 'text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300',
        'gray' => 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300',
    ];
    
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['default'];
    $spacingClass = $spacingClasses[$spacing] ?? $spacingClasses['default'];
@endphp

@if(!empty($actions))
    <div {{ $attributes->merge(['class' => "flex items-center {$spacingClass}"]) }}>
        @foreach($actions as $action)
            @php
                $actionType = $action['type'] ?? 'link';
                $color = $action['color'] ?? 'primary';
                $label = $action['label'] ?? 'Action';
                $colorClass = $colorSchemes[$color] ?? $colorSchemes['primary'];
                $classes = "{$sizeClass} {$colorClass} font-medium transition-colors duration-200";
                
                // Handle additional classes
                if (isset($action['class'])) {
                    $classes .= ' ' . $action['class'];
                }
                
                // Handle conditional display
                $shouldShow = true;
                if (isset($action['when'])) {
                    $shouldShow = $action['when'];
                }
            @endphp
            
            @if($shouldShow)
                @if($actionType === 'link')
                    <a href="{{ $action['href'] ?? (isset($action['route']) ? route($action['route'], $action['params'] ?? []) : '#') }}" 
                       class="{{ $classes }}"
                       @if(isset($action['target'])) target="{{ $action['target'] }}" @endif
                       @if(isset($action['title'])) title="{{ $action['title'] }}" @endif>
                        @if(isset($action['icon']))
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $action['icon'] }}"></path>
                            </svg>
                        @endif
                        {{ $label }}
                    </a>
                    
                @elseif($actionType === 'button')
                    <button type="{{ $action['buttonType'] ?? 'button' }}"
                            class="{{ $classes }}"
                            @if(isset($action['onclick'])) onclick="{{ $action['onclick'] }}" @endif
                            @if(isset($action['disabled']) && $action['disabled']) disabled @endif
                            @if(isset($action['title'])) title="{{ $action['title'] }}" @endif>
                        @if(isset($action['icon']))
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $action['icon'] }}"></path>
                            </svg>
                        @endif
                        {{ $label }}
                    </button>
                    
                @elseif($actionType === 'delete' || $actionType === 'form')
                    <form method="POST" 
                          action="{{ $action['action'] ?? (isset($action['route']) ? route($action['route'], $action['params'] ?? []) : '#') }}" 
                          class="inline"
                          @if(isset($action['confirm'])) onsubmit="return confirm('{{ $action['confirm'] }}')" @endif>
                        @csrf
                        @if($actionType === 'delete')
                            @method('DELETE')
                        @elseif(isset($action['method']))
                            @method($action['method'])
                        @endif
                        
                        <button type="submit" class="{{ $classes }}">
                            @if(isset($action['icon']))
                                <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $action['icon'] }}"></path>
                                </svg>
                            @endif
                            {{ $label }}
                        </button>
                    </form>
                    
                @elseif($actionType === 'dropdown')
                    <div class="relative inline-block text-left" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="{{ $classes }} flex items-center"
                                type="button">
                            {{ $label }}
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white dark:bg-gray-800 py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                            @if(isset($action['items']) && is_array($action['items']))
                                @foreach($action['items'] as $item)
                                    @if($item['type'] === 'divider')
                                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                    @else
                                        <a href="{{ $item['href'] ?? (isset($item['route']) ? route($item['route'], $item['params'] ?? []) : '#') }}"
                                           class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            {{ $item['label'] }}
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        @endforeach
    </div>
@endif