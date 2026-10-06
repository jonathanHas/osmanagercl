/**
 * Shop mode supplier order review (design screen 20).
 *
 * Ported from the design's script block. The page root carries one URL:
 *   - shop.orders.items  GET  the order header and one row per item
 * and each row carries its own PATCH url (shop.orders.item), which sets the
 * item's cases through OrderService so the learning log and totals stay right.
 *
 * Stock is the generation-time snapshot the suggestion was computed from, not
 * live stock. For single-unit products "cases" are units throughout, as in
 * OrderService::updateOrderItemCases().
 *
 * Saves are optimistic and debounced per item: the last tap within SAVE_MS
 * wins, a failure puts the row back to what the server last confirmed.
 *
 * A Udea order holds well over a thousand items, and building every row at
 * once took 18 s on a desktop. Each group renders at most PAGE rows and a
 * "Show 50 more" button; any change to the filter, the switch, the sort or the
 * search starts the windows again (watchers set up in init()).
 */
import mix from './mix.js';
import productImages from './product-images.js';
import productPeek from './product-peek.js';

const TOAST_MS = 3000;
const SAVE_MS = 400;
const PAGE = 50;

const PRIO = {
    review: ['bad', 'Review'],
    standard: ['warn', 'Standard'],
    safe: ['ok', 'Safe'],
    added: ['sage', 'Added'],
};

// Until the order arrives (its header carries the real list, chilled groups first).
const DEFAULT_GROUPS = [{ key: 'case', title: 'Case products', codes: [] }, { key: 'unit', title: 'Single units', codes: [] }];

const FILTERS = [['all', 'All'], ['review', 'Review'], ['standard', 'Standard'], ['safe', 'Safe'], ['added', 'Added']];

// Row thumbnails (productImages) and the larger picture beside them (productPeek)
// are the shared Shop parts; the order item's `id` is the image key.
export default () => mix(productImages(), productPeek(), {
    order: { editable: false },
    items: [],
    filter: 'all',
    showUnordered: false,
    sort: 'sales',
    q: '',
    loading: true,
    // How many rows each group renders, by group key, filled on demand (PAGE
    // until "Show more" raises it); see shown() and more().
    windows: {},
    toast: null,
    toastTimer: null,

    init() {
        for (const key of ['filter', 'showUnordered', 'sort', 'q']) {
            this.$watch(key, () => this.resetWindows());
        }

        this.watchPeekScroll();
        this.load();
    },

    destroy() {
        this.unwatchPeekScroll();
    },

    get itemsUrl() {
        return this.$root.dataset.itemsUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    async load() {
        this.loading = true;

        try {
            const response = await fetch(this.itemsUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (! response.ok) {
                throw new Error(response.status);
            }
            const data = await response.json();
            this.order = data.order;
            this.items = data.items.map((it) => ({ ...it, busy: false, savedCases: it.final_cases, timer: null, hot: null, pinned: false }));
        } catch (e) {
            this.showToast('bad', 'Could not load the order');
        } finally {
            this.loading = false;
        }
    },

    // --- formatting ------------------------------------------------------

    fmt(n) {
        return (Math.round((n ?? 0) * 10) / 10).toString();
    },

    eur(n) {
        return '€' + (n ?? 0).toFixed(2);
    },

    // --- per-item maths --------------------------------------------------

    units(it) {
        return it.final_cases * it.case_units;
    },

    after(it) {
        return it.stock + this.units(it);
    },

    cover(it) {
        return it.avg_weekly > 0 ? this.after(it) / it.avg_weekly : null;
    },

    coverState(it) {
        const cover = this.cover(it);

        if (cover === null) {
            // A product that never sells is not "plenty of stock".
            return ['muted', 'No recent sales'];
        }
        if (cover < 1) {
            return ['bad', 'Runs out within a week'];
        }
        if (cover < it.target_weeks * 0.85) {
            return ['warn', `Lasts ${this.fmt(cover)} wk · below target`];
        }

        return ['ok', cover > 20 ? 'Plenty of stock' : `Lasts ${this.fmt(cover)} wk`];
    },

    prio(it) {
        return PRIO[it.priority] ?? PRIO.standard;
    },

    isChanged(it) {
        return it.final_cases !== it.suggested_cases;
    },

    isUnordered(it) {
        return it.suggested_cases === 0 && it.final_cases === 0;
    },

    packLabel(it) {
        return it.group === 'case' ? `${it.case_units} per case` : 'single unit';
    },

    caseWord(it) {
        if (it.group === 'case') {
            return it.final_cases === 1 ? 'case' : 'cases';
        }

        return it.final_cases === 1 ? 'unit' : 'units';
    },

    unitsLabel(it) {
        return it.group === 'case' ? `${this.fmt(this.units(it))} units` : `${this.eur(it.unit_cost)} each`;
    },

    cost(it) {
        return this.units(it) * it.unit_cost;
    },

    // --- chart -----------------------------------------------------------

    top(it) {
        return Math.max(it.peak_weekly, this.after(it), 1);
    },

    barHeight(it, v) {
        return Math.max(4, v / this.top(it) * 100);
    },

    avgBottom(it) {
        return it.avg_weekly / this.top(it) * 100;
    },

    projection(it) {
        const after = this.after(it);

        return [0, 1, 2, 3].map((w) => Math.max(0, after - it.avg_weekly * w));
    },

    projTitle(v, w) {
        return `${w ? 'Week ' + w : 'Delivery'}: about ${this.fmt(v)} in stock`;
    },

    /**
     * The chart's bars as markup, one x-html per side instead of an x-for with
     * three bindings per bar: fourteen bars a row was most of a row's cost, and a
     * Udea order has over a thousand rows. Only numbers and fixed class names go
     * into the string. Same elements and classes as the design's chart.
     */
    sparkPast(it) {
        const peak = it.peak_weekly;
        const bars = it.weekly_sales.map((v, i) => {
            const cls = v === peak && v > 0 ? 'shop-spark__bar is-peak' : 'shop-spark__bar';

            return `<span class="${cls}" data-w="${i}" style="height:${this.barHeight(it, v)}%"></span>`;
        });

        return bars.join('') + `<span class="shop-spark__avg" style="bottom:${this.avgBottom(it)}%"></span>`;
    },

    sparkFuture(it) {
        return this.projection(it).map((v, w) => {
            const cls = v < it.avg_weekly ? 'shop-spark__bar is-proj is-low' : 'shop-spark__bar is-proj';

            return `<span class="${cls}" data-p="${w}" style="height:${this.barHeight(it, v)}%" title="${this.projTitle(v, w)}"></span>`;
        }).join('');
    },

    // --- week readout ----------------------------------------------------
    //
    // The bars are x-html output, so there are no per-bar handlers: the plot
    // delegates pointer events and the readout reads which bar it was. On
    // touch pointerleave fires as the finger lifts, so a tap pins the readout
    // until the next tap. The plot element is passed in from the view and never
    // stored on the item (Alpine would proxy it).

    /**
     * { kind: 'w' | 'p', i, x, align } for a bar element, else null. `x` is the
     * bar's centre in plot pixels. The pill is wider than a phone-width plot's
     * outer thirds, so near either end it is anchored by its edge rather than
     * centred (`align` start / end / mid), which keeps it inside the row.
     */
    tipFor(bar) {
        if (! bar) {
            return null;
        }

        const x = bar.offsetLeft + bar.offsetWidth / 2 + bar.parentElement.offsetLeft;
        const width = bar.parentElement.parentElement.clientWidth || 1;
        const align = x < width / 3 ? 'start' : (x > width * 2 / 3 ? 'end' : 'mid');

        if (bar.dataset.w !== undefined) {
            return { kind: 'w', i: Number(bar.dataset.w), x, align };
        }
        if (bar.dataset.p !== undefined) {
            return { kind: 'p', i: Number(bar.dataset.p), x, align };
        }

        return null;
    },

    tipText(it) {
        const hot = it.hot;

        if (! hot) {
            return '';
        }
        if (hot.kind === 'p') {
            return this.projTitle(this.projection(it)[hot.i] ?? 0, hot.i);
        }

        const label = it.weekly_labels?.[hot.i] ?? '';
        const sold = `${this.fmt(it.weekly_sales[hot.i])} sold`;

        return label ? `Week of ${label} · ${sold}` : `Week ${hot.i + 1} · ${sold}`;
    },

    hover(it, e) {
        if (it.pinned) {
            return;
        }

        const bar = this.barAt(e);

        // pointermove fires constantly; only touch state when the bar changes.
        if (bar === null ? it.hot !== null : ! bar.classList.contains('is-hot')) {
            this.setHot(it, e.currentTarget, bar);
        }
    },

    /**
     * The bar under the pointer's column, not only the bar element itself: a
     * week with no sales is a 3 px stub, which nobody could point at or tap.
     */
    barAt(e) {
        const direct = e.target.closest('.shop-spark__bar');

        if (direct) {
            return direct;
        }

        for (const bar of e.currentTarget.querySelectorAll('.shop-spark__bar')) {
            const r = bar.getBoundingClientRect();

            if (e.clientX >= r.left - 2 && e.clientX <= r.right + 2) {
                return bar;
            }
        }

        return null;
    },

    unhover(it, plot) {
        if (! it.pinned) {
            this.setHot(it, plot, null);
        }
    },

    pinBar(it, e) {
        const bar = this.barAt(e);
        const same = it.pinned && bar && bar.classList.contains('is-hot');

        this.setHot(it, e.currentTarget, same ? null : bar);
        it.pinned = !! it.hot;
    },

    unpinBar(it, plot) {
        if (it.hot || it.pinned) {
            this.setHot(it, plot, null);
            it.pinned = false;
        }
    },

    /** Set the readout and mark its bar; a later x-html re-render simply drops the mark. */
    setHot(it, plot, bar) {
        it.hot = this.tipFor(bar);

        plot.querySelectorAll('.is-hot').forEach((el) => el.classList.remove('is-hot'));
        if (it.hot) {
            bar.classList.add('is-hot');
        }
    },

    chartLabel(it) {
        return `Weekly sales, last ${it.weekly_sales.length} weeks: ${it.weekly_sales.join(', ')}. `
            + `Projected stock after delivery: ${this.projection(it).map((v) => this.fmt(v)).join(', ')}`;
    },

    // --- lists -----------------------------------------------------------

    get visible() {
        return this.items.filter((it) => this.showUnordered || ! this.isUnordered(it));
    },

    get filters() {
        const visible = this.visible;

        return FILTERS.map(([key, label]) => ({
            key,
            label,
            count: key === 'all' ? visible.length : visible.filter((it) => it.priority === key).length,
        }));
    },

    get groups() {
        const q = this.q.trim().toLowerCase();

        let list = this.visible
            .filter((it) => this.filter === 'all' || it.priority === this.filter)
            .filter((it) => ! q || it.name.toLowerCase().includes(q) || String(it.code).toLowerCase().includes(q));

        const by = {
            sales: (a, b) => b.sold - a.sold,
            cover: (a, b) => {
                const ca = this.cover(a);
                const cb = this.cover(b);
                if (ca === null || cb === null) {
                    return (ca === null) - (cb === null);
                }

                return ca - cb;
            },
            name: (a, b) => a.name.localeCompare(b.name),
        }[this.sort] ?? (() => 0);

        list = [...list].sort(by);

        // The group list comes with the order (chilled groups first, then case
        // and unit); one pass buckets the sorted rows, keeping their order.
        const defs = this.order.groups ?? DEFAULT_GROUPS;
        const buckets = Object.fromEntries(defs.map((g) => [g.key, []]));

        for (const it of list) {
            (buckets[this.groupKey(it)] ?? buckets[it.group])?.push(it);
        }

        return defs
            .map((g) => ({ key: g.key, title: g.title, items: buckets[g.key] }))
            .filter((g) => g.items.length);
    },

    /**
     * The chilled group whose POS categories include the row's, else the row's
     * own case/unit group. A chilled row keeps its case or unit stepper wording.
     */
    groupKey(it) {
        const chilled = (this.order.groups ?? []).find((g) => g.codes.length && g.codes.includes(it.category));

        return chilled ? chilled.key : it.group;
    },

    /** The rows a group renders now; the group itself keeps the full list. */
    shown(g) {
        return g.items.slice(0, this.windows[g.key] ?? PAGE);
    },

    remaining(g) {
        return Math.max(0, g.items.length - (this.windows[g.key] ?? PAGE));
    },

    moreLabel(g) {
        return `Show ${Math.min(PAGE, this.remaining(g))} more · ${this.remaining(g)} left`;
    },

    more(g) {
        // Reassigned so Alpine sees a key it has not seen before.
        this.windows = { ...this.windows, [g.key]: (this.windows[g.key] ?? PAGE) + PAGE };
    },

    resetWindows() {
        this.windows = {};
    },

    get totalValue() {
        return this.items.reduce((sum, it) => sum + this.cost(it), 0);
    },

    get orderedCount() {
        return this.items.filter((it) => it.final_cases > 0).length;
    },

    get allCount() {
        return this.items.length;
    },

    // --- actions ---------------------------------------------------------

    inc(it) {
        this.setCases(it, it.final_cases + 1);
    },

    dec(it) {
        this.setCases(it, it.final_cases - 1);
    },

    reset(it) {
        this.setCases(it, it.suggested_cases);
    },

    setCases(it, n) {
        if (! this.order.editable) {
            return;
        }

        it.final_cases = Math.max(0, n);

        clearTimeout(it.timer);
        it.timer = setTimeout(() => {
            it.timer = null;
            this.save(it, it.final_cases);
        }, SAVE_MS);
    },

    async save(it, cases) {
        it.busy = true;

        try {
            const response = await fetch(it.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ cases }),
            });

            if (response.status === 409) {
                this.order.editable = false;
                this.revert(it);
                this.showToast('bad', 'This order is no longer editable');

                return;
            }
            if (! response.ok) {
                throw new Error(response.status);
            }

            const data = await response.json();
            it.savedCases = data.item.final_cases;
            it.total_cost = data.item.total_cost;
            // A newer tap may be waiting; only take the server's value if it is
            // still the value on screen.
            if (it.timer === null && it.final_cases === cases) {
                it.final_cases = data.item.final_cases;
            }
            this.order = data.order;
        } catch (e) {
            this.revert(it);
            this.showToast('bad', `Could not save ${it.name}`);
        } finally {
            it.busy = false;
        }
    },

    revert(it) {
        clearTimeout(it.timer);
        it.timer = null;
        it.final_cases = it.savedCases;
    },

    showToast(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },
});
