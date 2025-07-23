@props([
    'name',                    // Field name (required)
    'label' => null,          // Display label
    'type' => 'text',         // Input type
    'value' => null,          // Field value
    'placeholder' => null,    // Input placeholder
    'required' => false,      // Is field required
    'disabled' => false,      // Is field disabled
    'readonly' => false,      // Is field readonly
    'rows' => null,           // For textarea
    'options' => [],          // For select fields
    'help' => null,           // Help text
    'class' => '',            // Additional classes
    'containerClass' => '',   // Container classes
])

@php
    $fieldId = $name;
    $inputClasses = 'block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm';
    
    if ($class) {
        $inputClasses .= ' ' . $class;
    }
    
    if ($errors->has($name)) {
        $inputClasses .= ' border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500';
    }
    
    $containerClasses = 'mb-4';
    if ($containerClass) {
        $containerClasses .= ' ' . $containerClass;
    }
@endphp

<div class="{{ $containerClasses }}">
    @if($label)
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    @if($type === 'textarea')
        <textarea 
            id="{{ $fieldId }}"
            name="{{ $name }}"
            class="{{ $inputClasses }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
            @if($rows) rows="{{ $rows }}" @endif
            {{ $attributes->except(['name', 'label', 'type', 'value', 'placeholder', 'required', 'disabled', 'readonly', 'rows', 'options', 'help', 'class', 'containerClass']) }}
        >{{ old($name, $value) }}</textarea>
        
    @elseif($type === 'select')
        <select 
            id="{{ $fieldId }}"
            name="{{ $name }}"
            class="{{ $inputClasses }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['name', 'label', 'type', 'value', 'placeholder', 'required', 'disabled', 'readonly', 'rows', 'options', 'help', 'class', 'containerClass']) }}
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" {{ old($name, $value) == $optionValue ? 'selected' : '' }}>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
        
    @elseif($type === 'checkbox')
        <div class="flex items-center">
            <input 
                type="checkbox"
                id="{{ $fieldId }}"
                name="{{ $name }}"
                value="1"
                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                @if(old($name, $value)) checked @endif
                @if($required) required @endif
                @if($disabled) disabled @endif
                {{ $attributes->except(['name', 'label', 'type', 'value', 'placeholder', 'required', 'disabled', 'readonly', 'rows', 'options', 'help', 'class', 'containerClass']) }}
            >
            @if($label)
                <label for="{{ $fieldId }}" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $label }}
                    @if($required)
                        <span class="text-red-500">*</span>
                    @endif
                </label>
            @endif
        </div>
        
    @elseif($type === 'radio')
        <div class="space-y-2">
            @foreach($options as $optionValue => $optionLabel)
                <div class="flex items-center">
                    <input 
                        type="radio"
                        id="{{ $fieldId }}_{{ $optionValue }}"
                        name="{{ $name }}"
                        value="{{ $optionValue }}"
                        class="border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                        @if(old($name, $value) == $optionValue) checked @endif
                        @if($required) required @endif
                        @if($disabled) disabled @endif
                        {{ $attributes->except(['name', 'label', 'type', 'value', 'placeholder', 'required', 'disabled', 'readonly', 'rows', 'options', 'help', 'class', 'containerClass']) }}
                    >
                    <label for="{{ $fieldId }}_{{ $optionValue }}" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ $optionLabel }}
                    </label>
                </div>
            @endforeach
        </div>
        
    @else
        <input 
            type="{{ $type }}"
            id="{{ $fieldId }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            class="{{ $inputClasses }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
            {{ $attributes->except(['name', 'label', 'type', 'value', 'placeholder', 'required', 'disabled', 'readonly', 'rows', 'options', 'help', 'class', 'containerClass']) }}
        >
    @endif
    
    @if($help)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif
    
    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>