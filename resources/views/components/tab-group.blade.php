@props([
    'tabs' => [],           // Array of tab configurations
    'activeTab' => 0,       // Default active tab index
    'variant' => 'default', // Styling variant (default, pills, minimal)
    'position' => 'top',    // Tab position (top, bottom)
    'containerClass' => '', // Additional container classes
])

@php
    // Ensure tabs is an array and has valid structure
    $tabs = is_array($tabs) ? $tabs : [];
    $activeTab = max(0, min($activeTab, count($tabs) - 1)); // Clamp to valid range
    
    // Generate unique ID for this tab group
    $tabGroupId = 'tab-group-' . uniqid();
    
    // Validate tab structure and provide defaults
    foreach ($tabs as $index => $tab) {
        if (!isset($tab['id'])) {
            $tabs[$index]['id'] = 'tab-' . $index;
        }
        if (!isset($tab['label'])) {
            $tabs[$index]['label'] = 'Tab ' . ($index + 1);
        }
    }
@endphp

<div x-data="{ activeTab: {{ $activeTab }} }" class="w-full {{ $containerClass }}">
    @if($position === 'top')
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs" role="tablist">
                @foreach($tabs as $index => $tab)
                    <button @click="activeTab = {{ $index }}"
                            :class="activeTab === {{ $index }} ? 
                                'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'"
                            class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === {{ $index }}"
                            aria-controls="{{ $tabGroupId }}-panel-{{ $index }}"
                            id="{{ $tabGroupId }}-tab-{{ $index }}">
                        {{ $tab['label'] }}
                        @if(isset($tab['badge']) && $tab['badge'])
                            <span :class="activeTab === {{ $index }} ? 
                                'bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300' : 
                                'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-300'"
                                class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium transition-colors duration-200">
                                {{ $tab['badge'] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>
    @endif

    <!-- Tab Content -->
    <div class="mt-4">
        @foreach($tabs as $index => $tab)
            <div x-show="activeTab === {{ $index }}"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform translate-y-1"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform translate-y-1"
                 role="tabpanel"
                 aria-labelledby="{{ $tabGroupId }}-tab-{{ $index }}"
                 id="{{ $tabGroupId }}-panel-{{ $index }}"
                 tabindex="0">
                @php
                    $slotName = $tab['id'];
                    $hasSlot = false;
                    $slotContent = null;
                    
                    // Check if slot exists and get its content
                    if (isset($$slotName)) {
                        $hasSlot = true;
                        $slotContent = $$slotName;
                    }
                @endphp
                
                @if($hasSlot && $slotContent)
                    {{ $slotContent }}
                @else
                    <div class="p-4 text-gray-500 dark:text-gray-400 text-center">
                        <p>No content provided for "{{ $tab['label'] }}" tab.</p>
                        <p class="text-sm mt-1">Use slot name: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">{{ $tab['id'] }}</code></p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if($position === 'bottom')
        <!-- Tab Navigation at Bottom -->
        <div class="border-t border-gray-200 dark:border-gray-700 mt-4">
            <nav class="-mt-px flex space-x-8 pt-4" aria-label="Tabs" role="tablist">
                @foreach($tabs as $index => $tab)
                    <button @click="activeTab = {{ $index }}"
                            :class="activeTab === {{ $index }} ? 
                                'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'"
                            class="whitespace-nowrap py-2 px-1 border-t-2 font-medium text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === {{ $index }}"
                            aria-controls="{{ $tabGroupId }}-panel-{{ $index }}"
                            id="{{ $tabGroupId }}-tab-{{ $index }}">
                        {{ $tab['label'] }}
                        @if(isset($tab['badge']) && $tab['badge'])
                            <span :class="activeTab === {{ $index }} ? 
                                'bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300' : 
                                'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-300'"
                                class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium transition-colors duration-200">
                                {{ $tab['badge'] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>
    @endif
</div>

{{-- 
Usage Examples:

Basic tabs:
<x-tab-group :tabs="[
    ['id' => 'overview', 'label' => 'Overview'],
    ['id' => 'details', 'label' => 'Details']
]">
    <x-slot name="overview">
        <p>Overview content here</p>
    </x-slot>
    <x-slot name="details">
        <p>Details content here</p>
    </x-slot>
</x-tab-group>

Tabs with badges:
<x-tab-group :tabs="[
    ['id' => 'items', 'label' => 'Items', 'badge' => $itemCount],
    ['id' => 'issues', 'label' => 'Issues', 'badge' => $issueCount]
]">
    <x-slot name="items">
        <!-- Items content -->
    </x-slot>
    <x-slot name="issues">
        <!-- Issues content -->
    </x-slot>
</x-tab-group>

Bottom position tabs:
<x-tab-group :tabs="$tabs" position="bottom">
    <!-- Content slots -->
</x-tab-group>
--}}