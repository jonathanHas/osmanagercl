<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Udea Case / Single-Unit Pricing Test
            </h2>
            <a href="{{ route('orders.show', $order) }}"
               class="text-sm text-indigo-600 hover:text-indigo-800">← Back to order #{{ $order->id }}</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Intro / status --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 text-sm text-gray-700 dark:text-gray-300 space-y-2">
                <p>
                    Scrapes each Udea single-unit line item of
                    <strong>order #{{ $order->id }} — {{ $order->supplier->Supplier ?? 'Supplier' }}</strong>
                    and shows the parsed buy tiers next to our stored case size. The page loads immediately and
                    each row fills in as its product is queried. Use the raw HTML toggle to verify the parser.
                </p>
                @unless($isUdeaOrder)
                    <p class="text-amber-600 dark:text-amber-400">
                        ⚠ This order's supplier ({{ $order->supplier_id }}) is not a configured Udea supplier
                        ({{ implode(', ', config('suppliers.external_links.udea.supplier_ids', [])) }}).
                        Results may be empty.
                    </p>
                @endunless
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Only <strong>single-unit products (case units = 1)</strong> are scraped — the only ones where a
                    case-buy option is worth discovering. Case products are skipped for speed (tick “Show case products”
                    to list them with images only).
                </p>
                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span>To scrape: <strong>{{ $toScrapeCount }}</strong></span>
                    <span>Skipped — case product: <strong>{{ $skippedCaseProduct }}</strong></span>
                    <span>Skipped — no supplier code: <strong>{{ $skippedNoCode }}</strong></span>
                    <form method="GET" class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1">
                            <input type="checkbox" name="fresh" value="1" @checked($forceRefresh) class="rounded border-gray-300">
                            Bypass cache
                        </label>
                        <label class="inline-flex items-center gap-1">
                            <input type="checkbox" name="all" value="1" @checked($showAll) class="rounded border-gray-300">
                            Show case products
                        </label>
                        <button type="submit"
                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs">
                            Reload
                        </button>
                    </form>
                </div>

                {{-- Live progress --}}
                <div id="scrape-progress" class="pt-1 {{ $toScrapeCount ? '' : 'hidden' }}">
                    <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300">
                        <span id="scrape-progress-label">0 / {{ $toScrapeCount }}</span>
                        <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded overflow-hidden max-w-md">
                            <div id="scrape-progress-bar" class="h-full bg-indigo-500 transition-all duration-300" style="width:0%"></div>
                        </div>
                        <span id="scrape-progress-done" class="text-green-600 font-medium hidden">✓ Done</span>
                    </div>
                </div>
            </div>

            {{-- Results table --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
                        <tr>
                            <th class="px-3 py-2">Product</th>
                            <th class="px-3 py-2">Supplier code</th>
                            <th class="px-3 py-2 text-right">Our case units</th>
                            <th class="px-3 py-2 text-right">Site case qty</th>
                            <th class="px-3 py-2 text-center">Single unit?</th>
                            <th class="px-3 py-2 text-right">Single price</th>
                            <th class="px-3 py-2 text-right">Per-unit (case)</th>
                            <th class="px-3 py-2">Tiers parsed</th>
                            <th class="px-3 py-2">Raw</th>
                        </tr>
                    </thead>
                    @forelse($rows as $row)
                        @php
                            $product = $row['product'];
                            $scrape = $row['scrape'];
                            $ourCase = $row['our_case_units'] !== null ? (float) $row['our_case_units'] : null;
                        @endphp
                        <tbody
                            @if($scrape) data-scrape-row data-code="{{ $row['supplier_code'] }}" data-our-case="{{ $ourCase !== null ? (int) $ourCase : '' }}" @endif
                            x-data="{ raw: false }"
                            class="scrape-tbody divide-y divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
                        <tr class="align-top">
                            <td class="px-3 py-2">
                                <div class="flex items-start gap-2">
                                    <x-product-image :product="$product" :supplierService="$supplierService" size="lg" fit="contain" :hover="true" class="flex-shrink-0" />
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $product->NAME ?? 'Unknown' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $product->CODE ?? 'N/A' }}</div>
                                        @if(! $scrape)
                                            <span class="text-[11px] text-gray-400">Case product (not scraped)</span>
                                        @else
                                            <span class="js-status text-[11px] text-gray-400"></span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $row['supplier_code'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $ourCase !== null ? rtrim(rtrim(number_format($ourCase, 2), '0'), '.') : '—' }}</td>
                            <td class="js-caseqty px-3 py-2 text-right text-gray-400">
                                @if($scrape)
                                    <svg class="js-spinner inline w-4 h-4 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="js-single px-3 py-2 text-center text-gray-400">{{ $scrape ? '' : '—' }}</td>
                            <td class="js-singleprice px-3 py-2 text-right text-gray-400">{{ $scrape ? '' : '—' }}</td>
                            <td class="js-perunit px-3 py-2 text-right text-gray-400">{{ $scrape ? '' : '—' }}</td>
                            <td class="js-tiers px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $scrape ? '' : '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="raw = !raw"
                                            class="js-rawbtn hidden text-[11px] text-indigo-600 hover:text-indigo-800"
                                            x-text="raw ? 'Hide' : 'Show'"></button>
                                    @if($scrape)
                                        <button type="button" class="js-refreshbtn text-[13px] text-gray-400 hover:text-indigo-600 leading-none"
                                                title="Re-scrape this product now">&#8635;</button>
                                    @else
                                        <span class="text-gray-400 text-[11px]">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($scrape)
                            <tr x-show="raw" style="display:none;">
                                <td colspan="9" class="px-3 py-2 bg-gray-50 dark:bg-gray-900">
                                    <pre class="js-raw text-[10px] leading-tight whitespace-pre-wrap break-all max-h-96 overflow-auto text-gray-700 dark:text-gray-300"></pre>
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    @empty
                        <tbody>
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-gray-500">
                                No Udea single-unit line items found in this order.
                            </td>
                        </tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>
    </div>

    <style>
        .scrape-tbody.is-loading { opacity: .55; }
    </style>

    <script>
        (function () {
            const SCRAPE_URL = @json(route('tools.udea-case-test.scrape'));
            const CSRF = @json(csrf_token());
            const FRESH = @json((bool) $forceRefresh);
            const CHUNK = 4;   // codes per request (one auth reused across the chunk)
            const POOL = 2;    // concurrent requests

            const rows = Array.from(document.querySelectorAll('tbody[data-scrape-row]'));
            const total = rows.length;
            if (!total) return;

            const labelEl = document.getElementById('scrape-progress-label');
            const barEl = document.getElementById('scrape-progress-bar');
            const doneEl = document.getElementById('scrape-progress-done');
            let done = 0;

            function updateProgress() {
                labelEl.textContent = done + ' / ' + total;
                barEl.style.width = total ? Math.round(done / total * 100) + '%' : '0%';
                if (done >= total) doneEl.classList.remove('hidden');
            }

            const SPINNER = '<svg class="js-spinner inline w-4 h-4 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';

            function setCell(tb, sel, html, cls) {
                const el = tb.querySelector(sel);
                if (!el) return;
                el.innerHTML = html;
                if (cls) el.className = cls;
            }

            function resetRow(tb) {
                setCell(tb, '.js-caseqty', SPINNER, 'js-caseqty px-3 py-2 text-right text-gray-400');
                setCell(tb, '.js-single', '', 'js-single px-3 py-2 text-center text-gray-400');
                setCell(tb, '.js-singleprice', '', 'js-singleprice px-3 py-2 text-right text-gray-400');
                setCell(tb, '.js-perunit', '', 'js-perunit px-3 py-2 text-right text-gray-400');
                setCell(tb, '.js-tiers', '', 'js-tiers px-3 py-2 text-xs');
                const st = tb.querySelector('.js-status');
                if (st) st.textContent = '';
            }

            async function scrapeBatch(codes, fresh) {
                try {
                    const resp = await fetch(SCRAPE_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ codes: codes, fresh: !!fresh }),
                    });
                    const json = await resp.json();
                    return json.results || {};
                } catch (e) {
                    return {};
                }
            }

            function fillRow(tb, r) {
                const status = tb.querySelector('.js-status');
                if (r && r.error) {
                    if (status) { status.textContent = r.error; status.className = 'js-status text-[11px] text-red-500'; }
                    setCell(tb, '.js-caseqty', '<span class="text-gray-400">—</span>', 'js-caseqty px-3 py-2 text-right text-gray-400');
                    return;
                }
                const data = r && r.data;
                if (!data) {
                    if (status) { status.textContent = 'No data returned'; status.className = 'js-status text-[11px] text-amber-500'; }
                    setCell(tb, '.js-caseqty', '<span class="text-gray-400">—</span>', 'js-caseqty px-3 py-2 text-right text-gray-400');
                    return;
                }
                if (status) {
                    status.textContent = r.from_cache ? 'cached' : 'just scraped';
                    status.className = 'js-status text-[11px] ' + (r.from_cache ? 'text-gray-400' : 'text-green-600');
                }

                const ourCase = tb.dataset.ourCase !== '' ? parseInt(tb.dataset.ourCase, 10) : null;
                const caseQty = data.case_qty;
                if (caseQty != null) {
                    const match = ourCase != null && parseInt(caseQty, 10) === ourCase;
                    const note = ourCase != null ? '<span class="block text-[10px]">' + (match ? 'match' : 'differs') + '</span>' : '';
                    setCell(tb, '.js-caseqty', caseQty + note, 'js-caseqty px-3 py-2 text-right ' + (match ? 'text-green-600' : 'text-red-600'));
                } else {
                    setCell(tb, '.js-caseqty', '<span class="text-gray-400">—</span>', 'js-caseqty px-3 py-2 text-right text-gray-400');
                }

                setCell(tb, '.js-single',
                    data.single_unit_available
                        ? '<span class="inline-flex px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[11px] font-medium">Yes</span>'
                        : '<span class="text-gray-400 text-[11px]">no</span>',
                    'js-single px-3 py-2 text-center');

                setCell(tb, '.js-singleprice', data.single_unit_price ? '€' + data.single_unit_price : '—', 'js-singleprice px-3 py-2 text-right');
                setCell(tb, '.js-perunit', data.per_unit_case_price ? '€' + data.per_unit_case_price : '—', 'js-perunit px-3 py-2 text-right');

                const tiers = data.purchase_tiers || [];
                if (tiers.length) {
                    const items = tiers.map(t => '<li>x ' + t.qty + ': <span class="font-mono">line ' + (t.line_price || '?') + ' / unit ' + (t.unit_price || '?') + '</span></li>').join('');
                    setCell(tb, '.js-tiers', '<ul class="space-y-0.5">' + items + '</ul>', 'js-tiers px-3 py-2 text-xs text-gray-600 dark:text-gray-300');
                } else {
                    setCell(tb, '.js-tiers', '<span class="text-gray-400">—</span>', 'js-tiers px-3 py-2 text-xs');
                }

                if (r.card_html) {
                    const pre = tb.querySelector('.js-raw');
                    if (pre) pre.textContent = r.card_html;
                    const btn = tb.querySelector('.js-rawbtn');
                    if (btn) btn.classList.remove('hidden');
                }
            }

            const chunks = [];
            for (let i = 0; i < rows.length; i += CHUNK) chunks.push(rows.slice(i, i + CHUNK));
            let nextChunk = 0;
            updateProgress();

            async function worker() {
                while (nextChunk < chunks.length) {
                    const chunk = chunks[nextChunk++];
                    chunk.forEach(tb => tb.classList.add('is-loading'));
                    const codes = chunk.map(tb => tb.dataset.code);
                    const results = await scrapeBatch(codes, FRESH);
                    chunk.forEach(tb => {
                        const r = results[tb.dataset.code] || { error: 'Request failed' };
                        fillRow(tb, r);
                        tb.classList.remove('is-loading');
                        done++;
                    });
                    updateProgress();
                }
            }

            // Per-row manual refresh: re-scrape just that product (bypassing the durable cache).
            document.querySelectorAll('.js-refreshbtn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const tb = btn.closest('tbody[data-scrape-row]');
                    if (!tb || tb.classList.contains('is-loading')) return;
                    tb.classList.add('is-loading');
                    resetRow(tb);
                    const results = await scrapeBatch([tb.dataset.code], true);
                    fillRow(tb, results[tb.dataset.code] || { error: 'Request failed' });
                    tb.classList.remove('is-loading');
                });
            });

            Promise.all(Array.from({ length: Math.min(POOL, chunks.length) }, () => worker()));
        })();
    </script>
</x-app-layout>
