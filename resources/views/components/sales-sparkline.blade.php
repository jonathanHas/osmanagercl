{{-- Weekly sales shape from an order item's generation-time snapshot. --}}
{{-- Tinted to support the trim decision: a declining or flat seller is safer to drop. --}}
{{-- Each sparkline is scaled to its own peak, so heights are NOT comparable between --}}
{{-- rows -- the avg caption carries the magnitude the shape alone cannot. --}}
@props(['weeks' => [], 'avg' => null])

@php
    $units = array_map(static fn ($week) => round((float) ($week['units'] ?? 0), 2), $weeks);
    $labels = array_map(static fn ($week) => $week['label'] ?? '', $weeks);

    $hasSales = array_sum($units) > 0;
    $avgDisplay = $avg > 0
        ? rtrim(rtrim(number_format((float) $avg, 2, '.', ''), '0'), '.')
        : '0';
@endphp

@if(empty($units))
    <span class="text-sm text-gray-400" title="No sales snapshot recorded for this item">&mdash;</span>
@else
    <div class="flex flex-col gap-0.5">
        <div class="relative h-8 w-28">
            <canvas class="sales-sparkline h-full w-full"
                    data-points="{{ json_encode($units) }}"
                    data-labels="{{ json_encode($labels) }}"
                    data-has-sales="{{ $hasSales ? 1 : 0 }}"></canvas>
        </div>
        <span class="text-[11px] {{ $hasSales ? 'text-gray-500' : 'text-gray-400' }}">
            {{ $avgDisplay }}/wk avg
        </span>
    </div>
@endif

@once
@push('scripts')
{{-- Note: sales-chart-modal pushes its own Chart.js tag and @once is per-call-site, --}}
{{-- so a page using both components would load the library twice. --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('canvas.sales-sparkline').forEach(function (el) {
        const points = JSON.parse(el.dataset.points || '[]');
        const labels = JSON.parse(el.dataset.labels || '[]');
        const hasSales = el.dataset.hasSales === '1';

        // Flat-zero rows still render, as a grey baseline: "no sales" is itself the answer.
        const color = hasSales ? 'rgb(79, 70, 229)' : 'rgb(156, 163, 175)';

        new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: points,
                    borderColor: color,
                    backgroundColor: hasSales ? 'rgba(79, 70, 229, 0.12)' : 'rgba(156, 163, 175, 0.1)',
                    borderWidth: 1.5,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: color,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 11 },
                        bodyFont: { size: 11 },
                        padding: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y.toFixed(1) + ' units';
                            },
                        },
                    },
                },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true },
                },
            },
        });
    });
});
</script>
@endpush
@endonce
