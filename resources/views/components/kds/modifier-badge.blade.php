{{--
    One modifier badge for a KDS card, or the plain chip when the option has no
    badge kind. The page must also @include('kds._modifier-badge-assets') once
    for the CSS and the SVG sprite this markup references.

    ⚠ LOCKSTEP: this component and window.kdsModifierBadgeHtml() in
    resources/views/kds/_modifier-badge-assets.blade.php must emit the same
    markup — the JS one re-renders every card after an SSE or poll update.
    The shared geometry lives in App\Models\CoffeeProductMetadata::BADGE_KINDS
    and ::BADGE_DECOS.
--}}
@props(['kind' => null, 'label' => ''])

@php
    $family = \App\Models\CoffeeProductMetadata::badgeFamily($kind);
@endphp

@if ($family === null)
    <span class="item__mod">{{ $label }}</span>
@else
    <span class="mb mb--f-{{ $family }} mb--{{ $kind }}"><svg class="mb__bg" aria-hidden="true"><use href="#mb-shape-{{ $family }}"/></svg>@foreach (\App\Models\CoffeeProductMetadata::BADGE_DECOS[$family] ?? [] as $deco)<svg class="mb__deco" style="{{ $deco['style'] }}" aria-hidden="true"><use href="#{{ $deco['symbol'] }}"/></svg>@endforeach<span class="mb__label">{{ $label }}</span></span>
@endif
