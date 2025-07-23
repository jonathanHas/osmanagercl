@props([
    'type' => 'info',           // success, error, warning, info
    'message' => null,          // Single message string
    'messages' => [],           // Array of messages
    'dismissible' => false,     // Show close button
    'icon' => true,            // Show type-specific icon
])

@php
    // Determine if we have any content to display
    $hasContent = $message || (is_array($messages) && count($messages) > 0);
    
    // If no content, don't render anything
    if (!$hasContent) return;
    
    // Define type-specific styling
    $typeClasses = [
        'success' => 'bg-green-100 border-green-400 text-green-700 dark:bg-green-900 dark:border-green-600 dark:text-green-300',
        'error' => 'bg-red-100 border-red-400 text-red-700 dark:bg-red-900 dark:border-red-600 dark:text-red-300',
        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700 dark:bg-yellow-900 dark:border-yellow-600 dark:text-yellow-300',
        'info' => 'bg-blue-100 border-blue-400 text-blue-700 dark:bg-blue-900 dark:border-blue-600 dark:text-blue-300',
    ];
    
    $iconClasses = [
        'success' => 'text-green-500 dark:text-green-400',
        'error' => 'text-red-500 dark:text-red-400',
        'warning' => 'text-yellow-500 dark:text-yellow-400',
        'info' => 'text-blue-500 dark:text-blue-400',
    ];
    
    $icons = [
        'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z',
        'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    
    $classes = $typeClasses[$type] ?? $typeClasses['info'];
    $iconClass = $iconClasses[$type] ?? $iconClasses['info'];
    $iconPath = $icons[$type] ?? $icons['info'];
@endphp

<div {{ $attributes->merge(['class' => "border px-4 py-3 rounded mb-6 $classes"]) }} 
     role="alert"
     @if($dismissible) x-data="{ show: true }" x-show="show" @endif>
    
    <div class="flex items-start">
        @if($icon)
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 {{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"></path>
                </svg>
            </div>
        @endif
        
        <div class="{{ $icon ? 'ml-3' : '' }} flex-1">
            @if($message)
                <span class="block sm:inline">{{ $message }}</span>
            @endif
            
            @if(is_array($messages) && count($messages) > 0)
                @if(count($messages) === 1)
                    <span class="block sm:inline">{{ $messages[0] }}</span>
                @else
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($messages as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
        
        @if($dismissible)
            <div class="flex-shrink-0 ml-4">
                <button @click="show = false" class="inline-flex {{ $iconClass }} hover:opacity-75">
                    <span class="sr-only">Dismiss</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif
    </div>
</div>