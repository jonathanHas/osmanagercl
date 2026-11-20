<x-admin-layout>
    @php
        $eur = fn ($v, $d = 0) => ($v < 0 ? '-' : '').'€'.number_format(abs($v), $d);
        $num = fn ($v, $d = 0) => number_format($v, $d);
        $change = function ($now, $before) {
            if (! $before || $now === null) {
                return null;
            }
            $pct = ($now - $before) / abs($before) * 100;

            return ($pct >= 0 ? '+' : '').number_format($pct, 0).'%';
        };
    @endphp

    <div class="p-6">
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Electricity</h1>
                @if($point)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        MPRN {{ $point->mprn }}@if($point->dg_group) · {{ $point->dg_group }}@endif @if($point->mic_kva) · MIC {{ $point->mic_kva }} kVA @endif
                        · {{ $billCount }} confirmed {{ Str::plural('bill', $billCount) }}
                    </p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if($points->count() > 1)
                    <form method="GET">
                        <select name="point" onchange="this.form.submit()" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                            @foreach($points as $p)
                                <option value="{{ $p->id }}" @selected($point && $p->id === $point->id)>{{ $p->name ?: 'MPRN '.$p->mprn }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ route('management.electricity.bills.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium text-sm">Bills</a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 rounded-lg">{{ session('success') }}</div>
        @endif

        @if($needsReview)
            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 rounded-lg">
                {{ $needsReview }} {{ Str::plural('bill', $needsReview) }} waiting for review {{ $needsReview === 1 ? 'is' : 'are' }} not in these figures.
                <a class="underline font-medium" href="{{ route('management.electricity.bills.index', ['status' => 'needs_review']) }}">Review now</a>
            </div>
        @endif

        @if(! $point || empty($months))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                <p class="text-gray-700 dark:text-gray-300 mb-4">No confirmed electricity bills yet.</p>
                <a href="{{ route('management.electricity.bills.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">Load bills</a>
            </div>
        @else
            @php($cur = $summary['current'])
            @php($prev = $summary['previous'])

            {{-- Headline figures: last 12 months against the 12 before --}}
            <div class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                {{ $cur['from'] }} to {{ $cur['to'] }}@if($prev['months']), compared with {{ $prev['from'] }} to {{ $prev['to'] }}@endif
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                @foreach([
                    ['Electricity used', $num($cur['kwh']).' kWh', $change($cur['kwh'], $prev['kwh']), 'from the meter readings'],
                    ['Cost before VAT', $eur($cur['net']), $change($cur['net'], $prev['net']), $cur['other'] ? 'includes '.$eur($cur['other']).' one-off charges' : 'all charges on the bills'],
                    ['All-in cost per kWh', $cur['cents_per_kwh'] !== null ? $num($cur['cents_per_kwh'], 1).'c' : '–', $change($cur['cents_per_kwh'], $prev['cents_per_kwh']), 'every charge except one-offs, before VAT'],
                    ['Unit rate paid', $cur['unit_rate_cents'] !== null ? $num($cur['unit_rate_cents'], 2).'c' : '–', $change($cur['unit_rate_cents'], $prev['unit_rate_cents']), 'energy line only · fixed charges '.($cur['fixed_per_day'] !== null ? $eur($cur['fixed_per_day'], 2).' a day' : '–')],
                    ['Solar export credit', $eur(abs($cur['export_credit'])), $change(abs($cur['export_credit']), abs($prev['export_credit'])), $num($cur['export_kwh']).' kWh exported'],
                ] as [$title, $value, $delta, $note])
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $title }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($delta)<span class="font-medium text-gray-700 dark:text-gray-300">{{ $delta }}</span> on the year before · @endif{{ $note }}
                        </div>
                    </div>
                @endforeach
            </div>

            @if($uncovered)
                <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 rounded-lg text-sm">
                    No bill is loaded for {{ implode(', ', $uncovered) }}. Usage for those months comes from the meter readings either side; their cost is missing and they are left out of the cost per kWh.
                </div>
            @endif
            @if($excludedCredits)
                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm">
                    {{ count($excludedCredits) }} credit {{ Str::plural('adjustment', count($excludedCredits)) }}
                    ({{ $eur(array_sum(array_column($excludedCredits, 'net_total')), 2) }}) left out of the costs: each cancels a bill that is not loaded, so neither side is counted.
                    @foreach($excludedCredits as $credit)
                        <a class="underline" href="{{ route('management.electricity.bills.show', $credit['id']) }}">{{ $credit['bill_number'] }}</a>@if(! $loop->last), @endif
                    @endforeach
                </div>
            @endif

            {{-- Range --}}
            <div class="mb-4 flex items-center gap-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Show</span>
                @foreach(['12' => '12 months', '24' => '24 months', 'all' => 'Everything'] as $value => $label)
                    <a href="{{ route('management.electricity.index', ['months' => $value, 'point' => $point->id]) }}"
                       class="px-3 py-1 rounded-md border {{ $range === (string) $value ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">{{ $label }}</a>
                @endforeach
            </div>

            {{-- Charts --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Electricity used each month</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">kWh by meter, from actual readings spread evenly between reads</p>
                    <div style="height: 300px;"><canvas id="usageChart"></canvas></div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cost each month</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">€ before VAT, by type of charge</p>
                    <div style="height: 300px;"><canvas id="costChart"></canvas></div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">All-in cost per kWh</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">cent per kWh before VAT, one-off charges left out</p>
                    <div style="height: 240px;"><canvas id="rateChart"></canvas></div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Solar exported to the grid</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">kWh credited on the bills</p>
                    <div style="height: 240px;"><canvas id="exportChart"></canvas></div>
                </div>
            </div>

            {{-- Monthly table --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 overflow-x-auto">
                <div class="px-6 pt-6 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Month by month</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Each meter's share of the cost follows its share of the kWh that month.</p>
                    </div>
                    <a href="{{ route('management.electricity.meter-split', ['point' => $point->id]) }}"
                       class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 whitespace-nowrap">Download meter split (CSV)</a>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-6 py-2 font-medium">Month</th>
                            @foreach($meters as $label)
                                <th class="px-3 py-2 font-medium text-right">{{ $label }} kWh</th>
                                <th class="px-3 py-2 font-medium text-right">{{ $label }} cost</th>
                            @endforeach
                            <th class="px-3 py-2 font-medium text-right">Total kWh</th>
                            <th class="px-3 py-2 font-medium text-right">Cost before VAT</th>
                            <th class="px-3 py-2 font-medium text-right">VAT</th>
                            <th class="px-3 py-2 font-medium text-right">c/kWh</th>
                            <th class="px-3 py-2 font-medium text-right">Exported kWh</th>
                            <th class="px-6 py-2 font-medium text-right">Export credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach(array_reverse($months, true) as $ym => $m)
                            <tr>
                                <td class="px-6 py-2 whitespace-nowrap">
                                    {{ $m['label'] }}
                                    @if($m['estimated'])<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300" title="No actual reading at one end of this stretch; the bill's estimate is used">estimated</span>@endif
                                    @if(! $m['covered'])<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200">no bill</span>@endif
                                </td>
                                @foreach($meters as $id => $label)
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $num($m['meters'][$id]['kwh'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $m['covered'] ? $eur($m['meters'][$id]['net'] ?? 0, 2) : '–' }}</td>
                                @endforeach
                                <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $num($m['kwh']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $m['covered'] ? $eur($m['net'], 2) : '–' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $m['covered'] ? $eur($m['vat'], 2) : '–' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $m['cents_per_kwh'] !== null ? $num($m['cents_per_kwh'], 1) : '–' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $m['export_kwh'] ? $num($m['export_kwh']) : '–' }}</td>
                                <td class="px-6 py-2 text-right tabular-nums">{{ $m['export_credit'] ? $eur(abs($m['export_credit']), 2) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Meters --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Meters</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Give each meter a name; it is used in the charts, the table and the download.</p>
                <div class="space-y-3">
                    @foreach($point->meters()->orderBy('serial')->get() as $meter)
                        <form method="POST" action="{{ route('management.electricity.meters.update', $meter) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            @method('PATCH')
                            <span class="w-32 font-mono text-sm text-gray-700 dark:text-gray-300">{{ $meter->serial }}</span>
                            <input type="text" name="name" value="{{ $meter->name }}" maxlength="60" placeholder="e.g. Shop" aria-label="Name for meter {{ $meter->serial }}"
                                   class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm w-64">
                            <button class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Save</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($point && ! empty($months))
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const data = @json($chart);
            const dark = document.documentElement.classList.contains('dark');
            // Fixed series order; colours belong to the meter or charge type, not its position on the day.
            const palette = dark
                ? ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#0ca30c', '#9085e9']
                : ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6'];
            const costColour = { energy: 0, market: 1, standing: 2, pso_levy: 3, electricity_tax: 4, network: 5, other: 6 };
            const surface = dark ? '#1f2937' : '#ffffff';
            const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)';
            const ink = dark ? '#9ca3af' : '#6b7280';
            const fmt = (v, d = 0) => Number(v).toLocaleString('en-IE', { minimumFractionDigits: d, maximumFractionDigits: d });

            Chart.defaults.color = ink;
            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

            const base = (yFormat, tooltipLabel, stacked, legend) => ({
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: legend, position: 'bottom', labels: { boxWidth: 12, boxHeight: 12 } },
                    tooltip: { callbacks: { label: tooltipLabel } },
                },
                scales: {
                    x: { stacked, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                    y: { stacked, grid: { color: grid }, border: { display: false }, ticks: { callback: yFormat } },
                },
            });
            const bar = (label, values, colour) => ({
                label, data: values, backgroundColor: colour,
                borderColor: surface, borderWidth: { top: 1, right: 1, bottom: 0, left: 1 }, borderRadius: 3, maxBarThickness: 28,
            });

            new Chart(document.getElementById('usageChart'), {
                type: 'bar',
                data: { labels: data.labels, datasets: data.meters.map((m, i) => bar(m.label, m.values, palette[i % palette.length])) },
                options: (() => {
                    const o = base(v => fmt(v), c => `${c.dataset.label}: ${fmt(c.parsed.y)} kWh`, true, data.meters.length > 1);
                    o.plugins.tooltip.callbacks.footer = items => {
                        const total = items.reduce((s, i) => s + i.parsed.y, 0);
                        return `Total: ${fmt(total)} kWh` + (data.estimated[items[0].dataIndex] ? '\nRests on an estimated reading' : '');
                    };
                    return o;
                })(),
            });

            new Chart(document.getElementById('costChart'), {
                type: 'bar',
                data: { labels: data.labels, datasets: data.costs.map(c => bar(c.label, c.values, palette[costColour[c.key]])) },
                options: (() => {
                    const o = base(v => '€' + fmt(v), c => `${c.dataset.label}: €${fmt(c.parsed.y, 2)}`, true, true);
                    o.plugins.tooltip.callbacks.footer = items => 'Total: €' + fmt(items.reduce((s, i) => s + i.parsed.y, 0), 2);
                    return o;
                })(),
            });

            new Chart(document.getElementById('rateChart'), {
                type: 'line',
                data: { labels: data.labels, datasets: [{
                    label: 'All-in cost per kWh', data: data.centsPerKwh, borderColor: palette[0], backgroundColor: palette[0],
                    borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: 0.2, spanGaps: false,
                }] },
                options: base(v => fmt(v) + 'c', c => `${fmt(c.parsed.y, 1)}c per kWh`, false, false),
            });

            new Chart(document.getElementById('exportChart'), {
                type: 'bar',
                data: { labels: data.labels, datasets: [bar('Exported', data.exportKwh, palette[2])] },
                options: base(v => fmt(v), c => `${fmt(c.parsed.y)} kWh exported`, false, false),
            });
        });
    </script>
    @endif
</x-admin-layout>
