<x-shop-layout title="Vouchers" :back="route('shop.home')">
    <main class="shop-page" x-data="shopVouchers()"
          data-lookup-url="{{ route('vouchers.lookup') }}"
          data-deduct-url="{{ route('vouchers.deduct') }}"
          data-activate-url="{{ $canActivate ? route('vouchers.activate') : '' }}"
          @scan="onScan($event.detail.code)">
        <div class="shop-split">
            <div class="shop-stack">
                <x-shop.scan-input placeholder="Scan voucher" hint="Ready — scan a voucher" />

                <section class="shop-empty" x-show="mode === 'idle'">
                    <div class="shop-empty__icon"><x-shop.icon name="gift" size="xl" /></div>
                    <p class="shop-empty__title">Scan a voucher</p>
                    <p class="shop-empty__text">Its balance and history will show here.</p>
                </section>

                <section class="shop-card" x-show="voucher" x-cloak>
                    <div class="shop-between">
                        <span class="shop-label">Balance</span>
                        <span class="shop-pill" :class="'shop-pill--' + statusPill.tone" x-text="statusPill.text"></span>
                    </div>
                    <span class="shop-bignum" :class="{ 'shop-bignum--bad': mode === 'deactivated' }">
                        <span class="shop-bignum__cur">€</span><span x-text="balance.toFixed(2)"></span>
                    </span>
                    <p class="shop-meta" x-text="issuedText"></p>
                </section>

                {{-- Why this voucher cannot be redeemed, in the words the office
                     till screen uses, so staff hear the same thing either way. --}}
                <section class="shop-card shop-card--flat" x-show="needsManager" x-cloak>
                    <x-shop.icon name="alert" size="sm" />
                    <p class="shop-meta">Voucher not active. Please ask a manager.</p>
                </section>
                <section class="shop-card shop-card--flat" x-show="mode === 'deactivated'" x-cloak>
                    <x-shop.icon name="alert" size="sm" />
                    <p class="shop-meta">This voucher has been deactivated.</p>
                </section>
                <section class="shop-card shop-card--flat" x-show="mode === 'exhausted'" x-cloak>
                    <x-shop.icon name="alert" size="sm" />
                    <p class="shop-meta">Nothing left on this voucher.</p>
                </section>
                @if ($canActivate)
                    <section class="shop-card shop-card--flat" x-show="activating" x-cloak>
                        <x-shop.icon name="alert" size="sm" />
                        <p class="shop-meta">Not yet active. Enter the starting balance and activate.</p>
                    </section>
                @endif

                <section class="shop-stack shop-stack--tight" x-show="history.length" x-cloak>
                    <h2 class="shop-label">History</h2>
                    <div class="shop-list">
                        <template x-for="t in history" :key="t.at + t.type">
                            <div class="shop-row">
                                <div class="shop-row__main">
                                    <span class="shop-row__title" x-text="t.label"></span>
                                    <span class="shop-row__meta" x-text="when(t.at) + ' · ' + t.user"></span>
                                </div>
                                <div class="shop-row__aside">
                                    <span class="shop-row__qty" x-text="t.amount ? signed(t.amount) : ''"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>

            <div class="shop-stack" x-show="mode === 'active' || activating" x-cloak>
                <section class="shop-card">
                    <div class="shop-numpad">
                        <div class="shop-numpad__display">
                            <span class="shop-label" x-text="displayLabel"></span>
                            <span x-text="typed || '0'"></span>
                        </div>
                        @foreach (range(1, 9) as $digit)
                            <button class="shop-key" type="button" @click="key('{{ $digit }}')">{{ $digit }}</button>
                        @endforeach
                        <button class="shop-key shop-key--fn" type="button" aria-label="Decimal point" @click="key('.')">.</button>
                        <button class="shop-key" type="button" @click="key('0')">0</button>
                        <button class="shop-key shop-key--fn" type="button" aria-label="Delete" @click="key('backspace')"><x-shop.icon name="backspace" size="lg" /></button>
                    </div>
                    <p class="shop-meta" x-show="mode === 'active'">Remaining after: <strong x-text="format(remaining)"></strong></p>
                </section>

                <button class="shop-btn shop-btn--ghost" type="button" x-show="mode === 'active'" @click="useFull()">Use full balance</button>
            </div>
        </div>

        <div class="shop-actions" x-show="mode === 'active' || activating" x-cloak>
            <button class="shop-btn shop-btn--primary shop-btn--lg" type="button"
                    x-show="mode === 'active'"
                    :disabled="! canDeduct || busy" @click="deduct()" x-text="deductLabel"></button>
            @if ($canActivate)
                <button class="shop-btn shop-btn--primary shop-btn--lg" type="button"
                        x-show="activating"
                        :disabled="! canActivate || busy" @click="activate()" x-text="activateLabel"></button>
            @endif
        </div>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>
    </main>
</x-shop-layout>
