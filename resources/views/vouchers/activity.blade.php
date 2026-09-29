<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
         x-data="voucherActivity()"
         data-feed-url="{{ route('vouchers.activity.feed') }}"
         data-poll-seconds="{{ $pollSeconds }}">
        <script type="application/json" id="voucher-activity-initial">@json($initial)</script>

        <!-- Title row -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-100">Voucher activity</h2>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded"
                      :class="offline ? 'bg-red-800/50 text-red-300' : (paused ? 'bg-gray-700 text-gray-300' : 'bg-green-800/50 text-green-300')">
                    <span class="h-2 w-2 rounded-full"
                          :class="offline ? 'bg-red-400' : (paused ? 'bg-gray-400' : 'bg-green-400 animate-pulse')"></span>
                    <span x-text="offline ? 'Offline' : (paused ? 'Paused' : 'Live')"></span>
                </span>
            </div>
            <div class="flex gap-2">
                <button type="button" x-on:click="togglePause()"
                        class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded"
                        x-text="paused ? 'Resume' : 'Pause'"></button>
                <a href="{{ route('vouchers.exceptions') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Till exceptions</a>
                <a href="{{ route('vouchers.list') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">All vouchers</a>
            </div>
        </div>

        <!-- Health banner -->
        <div class="mb-1">
            <template x-if="health.state === 'ok'">
                <div class="rounded px-4 py-3 bg-green-800/40 border border-green-700 text-green-200 text-sm">
                    <span x-text="'Till checked ' + ago(health.last_check_at)"></span>
                </div>
            </template>
            <template x-if="health.state !== 'ok'">
                <div class="rounded px-4 py-3 text-sm border"
                     :class="health.state === 'error' ? 'bg-red-900/50 border-red-700 text-red-200' : 'bg-yellow-900/40 border-yellow-700 text-yellow-200'">
                    <ul class="space-y-1">
                        <template x-for="(m, i) in (health.messages || [])" :key="i">
                            <li x-text="m"></li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
        <p class="text-xs text-gray-500 mb-4">
            <span x-text="'Last till check ' + (health.last_check_at ? ago(health.last_check_at) + ' (' + (health.last_check_source || '?') + ')' : 'never')"></span>
            ·
            <span x-text="'Scheduler last ran ' + (health.scheduler_last_at ? ago(health.scheduler_last_at) : 'never')"></span>
        </p>

        <!-- Tiles -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-gray-800 rounded p-4">
                <p class="text-xs text-gray-400">Redeemed today</p>
                <p class="text-2xl font-semibold text-gray-100" x-text="money(totals.redeemed_today)"></p>
                <p class="text-xs text-gray-500" x-text="'till ' + money(totals.redeemed_today_till) + ' · manual ' + money(totals.redeemed_today_manual)"></p>
            </div>
            <div class="bg-gray-800 rounded p-4">
                <p class="text-xs text-gray-400">Issued today</p>
                <p class="text-2xl font-semibold text-gray-100" x-text="money(totals.issued_today)"></p>
            </div>
            <div class="bg-gray-800 rounded p-4">
                <p class="text-xs text-gray-400">Outstanding balance</p>
                <p class="text-2xl font-semibold text-green-400" x-text="money(totals.outstanding_balance)"></p>
                <p class="text-xs text-gray-500" x-text="(totals.active_vouchers || 0) + ' active vouchers'"></p>
            </div>
            <a href="{{ route('vouchers.exceptions') }}" class="block bg-gray-800 hover:bg-gray-700 rounded p-4">
                <p class="text-xs text-gray-400">Unreviewed exceptions</p>
                <p class="text-2xl font-semibold"
                   :class="totals.unreviewed_exceptions > 0 ? 'text-red-400' : 'text-gray-100'"
                   x-text="totals.unreviewed_exceptions || 0"></p>
                <p class="text-xs text-gray-500">Open the exceptions page</p>
            </a>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3 mb-4 bg-gray-800 p-4 rounded">
            <div class="flex rounded overflow-hidden border border-gray-700">
                <template x-for="p in periods" :key="p.days">
                    <button type="button" x-on:click="setDays(p.days)"
                            class="px-3 py-2 text-sm"
                            :class="days === p.days ? 'bg-blue-600 text-white' : 'bg-gray-900 text-gray-300 hover:bg-gray-700'"
                            x-text="p.label"></button>
                </template>
            </div>
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-xs text-gray-400 mb-1" for="voucher-activity-q">Search code</label>
                <input id="voucher-activity-q" type="text" x-model="q" maxlength="64"
                       x-on:input.debounce.300ms="fetchNow()"
                       placeholder="Voucher code…"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
            </div>
        </div>

        <!-- Log -->
        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">When</th>
                        <th class="px-4 py-2 text-left">Event</th>
                        <th class="px-4 py-2 text-left">Voucher</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Balance after</th>
                        <th class="px-4 py-2 text-left">By</th>
                        <th class="px-4 py-2 text-left">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    <template x-for="e in events" :key="e.key">
                        <tr class="align-top transition-colors duration-700"
                            :class="isFresh(e.key) ? 'bg-blue-900/40' : 'hover:bg-gray-700/40'">
                            <td class="px-4 py-2 text-xs whitespace-nowrap">
                                <span class="text-gray-300" x-text="when(e.at)"></span>
                                <template x-if="e.sold_at">
                                    <span class="block text-gray-500" x-text="'till ' + hhmm(e.sold_at)"></span>
                                </template>
                            </td>
                            <td class="px-4 py-2">
                                <span class="text-gray-100" x-text="e.label"></span>
                                <span class="ml-1 text-xs px-2 py-0.5 rounded whitespace-nowrap"
                                      :class="pill(e).cls" x-text="pill(e).text"></span>
                                <template x-if="e.shortfall">
                                    <span class="block text-xs text-red-300" x-text="'short ' + money(e.shortfall)"></span>
                                </template>
                            </td>
                            <td class="px-4 py-2 font-mono">
                                <template x-if="e.voucher_url">
                                    <a :href="e.voucher_url" class="text-blue-400 hover:text-blue-300" x-text="e.code"></a>
                                </template>
                                <template x-if="! e.voucher_url">
                                    <span x-text="e.code || '—'"></span>
                                </template>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"
                                :class="e.amount > 0 ? 'text-green-400' : (e.amount < 0 ? 'text-blue-300' : 'text-gray-500')"
                                x-text="signed(e.amount)"></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"
                                x-text="e.balance_after === null ? '—' : money(e.balance_after)"></td>
                            <td class="px-4 py-2 text-gray-300 whitespace-nowrap" x-text="e.who"></td>
                            <td class="px-4 py-2 text-gray-400 text-xs max-w-xs" x-text="e.note || ''"></td>
                        </tr>
                    </template>
                    <tr x-show="events.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No voucher activity in this period.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function voucherActivity() {
            return {
                feedUrl: '',
                pollSeconds: 5,
                days: 7,
                q: '',
                periods: [
                    { days: 1, label: 'Today' },
                    { days: 7, label: '7 days' },
                    { days: 30, label: '30 days' },
                ],
                health: {},
                totals: {},
                events: [],
                seen: null,
                fresh: {},
                offset: 0,
                tick: Date.now(),
                paused: false,
                offline: false,
                inFlight: false,
                pending: false,

                init() {
                    this.feedUrl = this.$root.dataset.feedUrl;
                    this.pollSeconds = Number(this.$root.dataset.pollSeconds) || 5;

                    try {
                        const initial = JSON.parse(document.getElementById('voucher-activity-initial').textContent);
                        this.apply(initial, true);
                    } catch (e) {
                        console.error('Voucher activity: bad initial payload', e);
                    }

                    setInterval(() => this.poll(), this.pollSeconds * 1000);
                    // "Ago" texts and highlights tick every second.
                    setInterval(() => { this.tick = Date.now(); }, 1000);

                    document.addEventListener('visibilitychange', () => {
                        if (! document.hidden && ! this.paused) {
                            this.fetchNow();
                        }
                    });
                },

                poll() {
                    if (this.paused || document.hidden) return;
                    this.fetchNow();
                },

                async fetchNow() {
                    // A filter change while a fetch is out is re-run once it returns.
                    if (this.inFlight) {
                        this.pending = true;
                        return;
                    }
                    this.inFlight = true;

                    try {
                        const params = new URLSearchParams({ days: this.days });
                        if (this.q.trim() !== '') params.set('q', this.q.trim());

                        const response = await fetch(this.feedUrl + '?' + params.toString(), {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (! response.ok) throw new Error('HTTP ' + response.status);

                        this.apply(await response.json(), false);
                        this.offline = false;
                    } catch (e) {
                        this.offline = true;
                    } finally {
                        this.inFlight = false;
                        if (this.pending) {
                            this.pending = false;
                            this.fetchNow();
                        }
                    }
                },

                apply(data, first) {
                    this.offset = Date.parse(data.server_now) - Date.now();
                    this.health = data.health || {};
                    this.totals = data.totals || {};

                    const keys = new Set((data.events || []).map(e => e.key));
                    if (! first && this.seen) {
                        const now = Date.now();
                        for (const key of keys) {
                            if (! this.seen.has(key)) this.fresh[key] = now;
                        }
                    }
                    this.seen = keys;
                    this.events = data.events || [];
                },

                setDays(days) {
                    this.days = days;
                    // A new period is not "new activity": do not highlight its rows.
                    this.seen = null;
                    this.fetchNow();
                },

                togglePause() {
                    this.paused = ! this.paused;
                    if (! this.paused) this.fetchNow();
                },

                isFresh(key) {
                    return this.fresh[key] !== undefined && this.tick - this.fresh[key] < 10000;
                },

                serverNow() {
                    return this.tick + this.offset;
                },

                ago(iso) {
                    if (! iso) return 'never';
                    const s = Math.max(0, Math.round((this.serverNow() - Date.parse(iso)) / 1000));
                    if (s < 60) return s + ' s ago';
                    if (s < 3600) return Math.floor(s / 60) + ' min ago';
                    if (s < 86400) return Math.floor(s / 3600) + ' h ago';
                    return Math.floor(s / 86400) + ' days ago';
                },

                when(iso) {
                    if (! iso) return '';
                    return new Date(iso).toLocaleString('en-IE', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' });
                },

                hhmm(iso) {
                    if (! iso) return '';
                    return new Date(iso).toLocaleTimeString('en-IE', { hour: '2-digit', minute: '2-digit' });
                },

                money(n) {
                    return '€' + Number(n || 0).toFixed(2);
                },

                signed(n) {
                    const v = Number(n || 0);
                    if (v === 0) return '—';
                    return (v > 0 ? '+' : '−') + '€' + Math.abs(v).toFixed(2);
                },

                pill(e) {
                    if (e.status === 'partial') return { cls: 'bg-red-800/50 text-red-300', text: 'partial' + (e.reviewed ? ' · reviewed' : '') };
                    switch (e.kind) {
                        case 'redeem_till': return { cls: 'bg-purple-800/50 text-purple-300', text: 'till' };
                        case 'redeem_manual': return { cls: 'bg-blue-800/50 text-blue-300', text: 'manual' };
                        case 'issue': return { cls: 'bg-green-800/50 text-green-300', text: 'issue' };
                        case 'exception': return { cls: 'bg-yellow-800/50 text-yellow-300', text: (e.status || 'exception').replace('_', ' ') + (e.reviewed ? ' · reviewed' : '') };
                        default: return { cls: 'bg-gray-700 text-gray-300', text: 'status' };
                    }
                },
            };
        }
    </script>
</x-admin-layout>
