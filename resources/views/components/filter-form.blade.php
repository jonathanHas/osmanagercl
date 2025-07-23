@props([
    'action',                    // Form action URL (required)
    'method' => 'GET',          // Form method
    'searchName' => 'search',   // Search input name
    'searchValue' => '',        // Search input value
    'searchPlaceholder' => 'Search...', // Search placeholder
    'filters' => [],            // Array of filter configurations
    'submitLabel' => 'Filter',  // Submit button label
    'showSubmit' => false,      // Show submit button
    'autoSubmit' => false,      // Auto-submit on change
])

@php
    // Filter configurations should be arrays with:
    // [
    //   'name' => 'filter_name',
    //   'label' => 'Filter Label', 
    //   'type' => 'checkbox|select|text',
    //   'value' => 'current_value',
    //   'options' => [] // for select filters
    // ]
@endphp

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
        <form method="{{ $method }}" action="{{ $action }}" class="space-y-4" @if($autoSubmit) onchange="this.submit()" @endif>
            <!-- Search Input Row -->
            @if($searchName)
                <div class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" 
                               name="{{ $searchName }}" 
                               value="{{ $searchValue }}" 
                               placeholder="{{ $searchPlaceholder }}"
                               class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                    </div>
                    @if($showSubmit)
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                            {{ $submitLabel }}
                        </button>
                    @endif
                </div>
            @endif
            
            <!-- Filters Row -->
            @if(!empty($filters))
                <div class="flex flex-wrap gap-4">
                    @foreach($filters as $filter)
                        @if($filter['type'] === 'checkbox')
                            <label class="inline-flex items-center">
                                <input type="checkbox" 
                                       name="{{ $filter['name'] }}" 
                                       value="{{ $filter['value'] ?? '1' }}" 
                                       @if($filter['checked'] ?? false) checked @endif
                                       class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ $filter['label'] }}</span>
                            </label>
                        @elseif($filter['type'] === 'select')
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600 dark:text-gray-400">{{ $filter['label'] }}:</label>
                                <select name="{{ $filter['name'] }}" 
                                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    @if(isset($filter['placeholder']))
                                        <option value="">{{ $filter['placeholder'] }}</option>
                                    @endif
                                    @foreach($filter['options'] ?? [] as $optValue => $optLabel)
                                        <option value="{{ $optValue }}" @if(($filter['selected'] ?? '') == $optValue) selected @endif>
                                            {{ $optLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif($filter['type'] === 'text')
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600 dark:text-gray-400">{{ $filter['label'] }}:</label>
                                <input type="text" 
                                       name="{{ $filter['name'] }}" 
                                       value="{{ $filter['value'] ?? '' }}" 
                                       placeholder="{{ $filter['placeholder'] ?? '' }}"
                                       class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
            
            <!-- Custom slot for additional form elements -->
            {{ $slot ?? '' }}
            
            <!-- Submit button row if not shown inline -->
            @if($showSubmit && !$searchName)
                <div class="flex justify-end">
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                        {{ $submitLabel }}
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>