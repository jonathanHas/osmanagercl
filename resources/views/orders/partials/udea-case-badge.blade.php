{{--
    Compact Udea "buy by the case" badge for a single-unit product row.

    Inputs:
      $udeaCard  – App\Models\UdeaProductCard|null (durable cache row for this supplier code)
      $udeaCode  – supplier code (string) for background warming
      $needsWarm – true when this single-unit product has no cached row yet

    NOTE: the client-side warmer at the foot of review-table.blade.php builds the SAME badge
    markup for products fetched after page load — keep the two in sync if you change styling.
--}}
@php
    $udeaCard = $udeaCard ?? null;
    $udeaCode = $udeaCode ?? null;
    $needsWarm = $needsWarm ?? false;

    $badgeText = null;
    if ($udeaCard && $udeaCard->case_qty && $udeaCard->case_qty > 1) {
        $single = $udeaCard->single_unit_price !== null ? (float) str_replace(',', '.', $udeaCard->single_unit_price) : null;
        $perU = $udeaCard->per_unit_case_price !== null ? (float) str_replace(',', '.', $udeaCard->per_unit_case_price) : null;
        $saving = ($single && $perU && $single > $perU) ? (int) round(($single - $perU) / $single * 100) : null;

        $badgeText = '📦 Case ×'.$udeaCard->case_qty;
        if ($udeaCard->per_unit_case_price) {
            $badgeText .= ' · €'.$udeaCard->per_unit_case_price.'/u';
        }
        if ($saving) {
            $badgeText .= ' (save '.$saving.'%)';
        }
    }
@endphp
@if($badgeText)
    <span class="udea-case-badge mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-medium"
          title="Udea offers this by the case (single is our current setup)">{{ $badgeText }}</span>
@elseif($needsWarm && $udeaCode)
    <span class="udea-case-slot" data-udea-warm data-udea-code="{{ $udeaCode }}"></span>
@endif
