@props([
    'feature' => 'invoice_parsing',
    'label' => 'AI',
    'variant' => 'dark',
])

@php
    $provider = \App\Services\AiSettingsService::get($feature, 'provider', 'mistral-ocr');
    $model = \App\Services\AiSettingsService::get($feature, 'model', '');
    $providers = \App\Services\AiSettingsService::getAvailableProviders();
    $providerLabel = $providers[$provider]['label'] ?? ucfirst($provider);
    $classes = $variant === 'light'
        ? 'bg-gray-100 text-gray-700 border border-gray-300'
        : 'bg-gray-700/60 text-gray-300 border border-gray-600';
@endphp

<span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full {{ $classes }}"
      title="{{ $label }}: {{ $providerLabel }}{{ $model ? ' · '.$model : '' }}">
    <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
    </svg>
    <span>{{ $label }}: {{ $providerLabel }}</span>
</span>
