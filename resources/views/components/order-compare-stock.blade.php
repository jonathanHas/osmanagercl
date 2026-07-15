{{-- Stock level snapshot taken when the order was generated. --}}
{{-- Tinted to support the trim decision: plenty in stock is safer to drop than none. --}}
@props(['stock' => null])

@php
    $known = $stock !== null;
    $value = (float) $stock;

    $class = ! $known
        ? 'text-gray-400'
        : ($value <= 0
            ? 'text-red-600 font-semibold'
            : ($value < 5 ? 'text-amber-600' : 'text-gray-700'));

    $display = $known
        ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')
        : '—';
@endphp

<span class="text-sm {{ $class }}"
      @if(! $known) title="No stock snapshot recorded for this item" @endif>
    {{ $display === '' ? '0' : $display }}
</span>
