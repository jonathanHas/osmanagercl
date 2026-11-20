<x-admin-layout>
    @php
        $failed = $bill->failedChecks();
        $blocking = $bill->hasBlockingFailure();
        $input = 'rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm py-1 px-2';
        $label = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1';
        $categoryNames = [
            'energy' => 'Energy', 'standing' => 'Standing charge', 'pso_levy' => 'PSO levy',
            'electricity_tax' => 'Electricity tax', 'market' => 'Market charge', 'network' => 'Network',
            'export_credit' => 'Export credit', 'adjustment' => 'Adjustment', 'other' => 'Other',
        ];
    @endphp

    <div class="p-6" x-data="electricityBill(@js(['charges' => old('charges', $data['charges']), 'readings' => old('readings', $data['readings']), 'editing' => $errors->any() || session()->has('error')]))">
        <div class="mb-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <a href="{{ route('management.electricity.bills.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Electricity bills</a>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ $bill->provider }} {{ $bill->isCreditNote() ? 'credit' : 'bill' }} {{ $bill->bill_number }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($bill->isConfirmed())
                        <span class="text-green-700 dark:text-green-400 font-medium">✓ Confirmed</span>
                        {{ $bill->confirmedBy ? 'by '.$bill->confirmedBy->name : 'automatically (every check passed)' }}
                        {{ $bill->confirmed_at?->format('d/m/Y') }}
                    @else
                        <span class="text-amber-700 dark:text-amber-300 font-medium">⚠ Waiting for review</span> · not yet in the Electricity figures
                    @endif
                    · {{ ['parser' => 'read by the '.$bill->provider.' parser', 'ai' => 'read by AI', 'manual' => 'edited by hand'][$bill->source] ?? $bill->source }}
                    @if($bill->invoice) · <a class="underline" href="{{ route('invoices.show', $bill->invoice) }}">accounts invoice</a>@endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($hasPdf)
                    <a href="{{ route('management.electricity.bills.pdf', $bill) }}" target="_blank" rel="noopener"
                       class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Open PDF in new tab</a>
                @endif
                @if($aiAvailable && $hasPdf)
                    <form method="POST" action="{{ route('management.electricity.bills.reread', $bill) }}"
                          onsubmit="return confirm('Read this bill again with AI? The figures below are replaced and the bill goes back to review.')">
                        @csrf
                        <button class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Re-read with AI</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('management.electricity.bills.destroy', $bill) }}"
                      onsubmit="return confirm('Remove bill {{ $bill->bill_number }} from Electricity? The accounts invoice is not touched.')">
                    @csrf
                    @method('DELETE')
                    <button class="px-4 py-2 border border-red-300 dark:border-red-700 rounded-md text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">Remove</button>
                </form>
                @unless($bill->isConfirmed())
                    <form method="POST" action="{{ route('management.electricity.bills.confirm', $bill) }}"
                          @if($blocking) onsubmit="return confirm('Some checks still fail. Confirm this bill anyway?')" @endif>
                        @csrf
                        @if($blocking)<input type="hidden" name="override" value="1">@endif
                        <button class="px-5 py-2 rounded-md text-sm font-medium text-white {{ $blocking ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700' }}">
                            {{ $blocking ? 'Confirm anyway' : 'Confirm bill' }}
                        </button>
                    </form>
                @endunless
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg text-sm">
                <p class="font-medium mb-1">Not saved:</p>
                <ul class="list-disc ml-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Checks --}}
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Checks</h3>
            <ul class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-1 text-sm">
                @foreach($bill->checks ?? [] as $check)
                    <li class="flex gap-2">
                        @if($check['passed'])
                            <span class="text-green-600 dark:text-green-400" aria-hidden="true">✓</span>
                            <span class="text-gray-700 dark:text-gray-300">{{ $check['label'] }}</span>
                        @elseif($check['level'] === 'fail')
                            <span class="text-red-600 dark:text-red-400" aria-hidden="true">✗</span>
                            <span class="text-red-700 dark:text-red-300"><span class="font-medium">{{ $check['label'] }}</span>@if($check['detail']): {{ $check['detail'] }}@endif</span>
                        @else
                            <span class="text-amber-600 dark:text-amber-400" aria-hidden="true">!</span>
                            <span class="text-gray-700 dark:text-gray-300">Not so: {{ lcfirst($check['label']) }} @if($check['detail'])({{ $check['detail'] }})@endif</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @foreach($bill->warnings ?? [] as $warning)
                <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">! {{ $warning }}</p>
            @endforeach
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Amber notes do not stop a bill being confirmed: re-bills and credit notes often break the run of dates and readings.</p>
        </div>

        {{-- Side by side while reading; stacked while editing, because the edit tables need the full width --}}
        <div class="grid grid-cols-1 gap-6" :class="editing ? '' : 'xl:grid-cols-2'">
            {{-- The bill --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden self-start w-full" :class="editing ? 'max-w-4xl' : 'xl:sticky xl:top-4'">
                @if($hasPdf)
                    <iframe src="{{ route('management.electricity.bills.pdf', $bill) }}#view=FitH&navpanes=0" title="Bill PDF" class="w-full" :style="editing ? 'height: 60vh' : 'height: 82vh'"></iframe>
                @else
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">The PDF for this bill is not on disk.</p>
                @endif
            </div>

            {{-- What was read --}}
            <div class="space-y-6 min-w-0" x-show="! editing">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">What was read from the bill</h3>
                        <button type="button" @click="editing = true" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Correct figures</button>
                    </div>
                    <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                        @foreach([
                            'MPRN' => $data['mprn'],
                            'Account' => $data['account_number'],
                            'Issued' => $bill->issue_date?->format('d/m/Y'),
                            'Period' => $bill->period_start && $bill->period_end ? $bill->period_start->format('d/m/y').' – '.$bill->period_end->format('d/m/y') : null,
                            'Tariff' => $data['tariff'],
                            'Net total' => $bill->net_total !== null ? '€'.number_format($bill->net_total, 2) : null,
                            'VAT' => $bill->vat_total !== null ? '€'.number_format($bill->vat_total, 2) : null,
                            'Export credit' => $bill->export_credit_total ? '€'.number_format($bill->export_credit_total, 2) : null,
                            'Bill total' => $bill->gross_total !== null ? '€'.number_format($bill->gross_total, 2) : null,
                        ] as $term => $value)
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $term }}</dt>
                                <dd class="text-gray-900 dark:text-gray-100 tabular-nums">{{ $value ?? '–' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Meter readings</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr><th class="py-2 pr-3 font-medium">Meter</th><th class="py-2 pr-3 font-medium">From – to</th><th class="py-2 pr-3 font-medium text-right">Previous</th><th class="py-2 pr-3 font-medium text-right">Present</th><th class="py-2 font-medium text-right">kWh</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @forelse($bill->readings as $r)
                                <tr>
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $r->meter->label }}@if($r->band !== '24h') <span class="text-xs text-gray-500">{{ $r->band }}</span>@endif</td>
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $r->period_start->format('d/m/y') }} – {{ $r->period_end->format('d/m/y') }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ number_format($r->previous_reading) }} <span class="text-xs text-gray-500 dark:text-gray-400">{{ $r->previous_type }}</span></td>
                                    <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ number_format($r->present_reading) }} <span class="text-xs text-gray-500 dark:text-gray-400">{{ $r->present_type }}</span></td>
                                    <td class="py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($r->kwh) }}@if($r->is_reversal) <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">takes back an estimate</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-3 text-gray-500 dark:text-gray-400">No meter readings on this document.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Charges</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr><th class="py-2 pr-3 font-medium">Type</th><th class="py-2 pr-3 font-medium">As printed</th><th class="py-2 pr-3 font-medium text-right">Units</th><th class="py-2 pr-3 font-medium text-right">Rate</th><th class="py-2 pr-3 font-medium">Dates</th><th class="py-2 pr-3 font-medium text-right">VAT</th><th class="py-2 font-medium text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @foreach($bill->charges as $c)
                                <tr>
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $categoryNames[$c->category] ?? $c->category }}@if($c->band && $c->band !== '24h') <span class="text-xs text-gray-500">{{ $c->band }}</span>@endif</td>
                                    <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ $c->description }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $c->quantity !== null ? rtrim(rtrim(number_format($c->quantity, 3), '0'), '.') : '' }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">@if($c->rate !== null)€{{ rtrim(rtrim(number_format($c->rate, 5), '0'), '.') }}@if($c->unit)<span class="text-xs text-gray-500 dark:text-gray-400">/{{ ['kwh' => 'kWh', 'mwh' => 'MWh', 'kva' => 'kVA'][$c->unit] ?? $c->unit }}</span>@endif @endif</td>
                                    <td class="py-2 pr-3 whitespace-nowrap">@if($c->period_start || $c->period_end){{ $c->period_start?->format('d/m/y') }}@if($c->period_end && (! $c->period_start || ! $c->period_end->equalTo($c->period_start))) – {{ $c->period_end->format('d/m/y') }}@endif @endif</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $c->vat_rate !== null ? rtrim(rtrim(number_format($c->vat_rate, 1), '0'), '.').'%' : '' }}</td>
                                    <td class="py-2 text-right tabular-nums whitespace-nowrap">{{ number_format($c->net_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form x-show="editing" x-cloak method="POST" action="{{ route('management.electricity.bills.update', $bill) }}" class="space-y-6 min-w-0">
                @csrf
                @method('PUT')

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Bill details</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div><label class="{{ $label }}" for="f-provider">Provider</label><input style="width:100%" id="f-provider" name="provider" value="{{ old('provider', $data['provider']) }}" class="{{ $input }}" required></div>
                        <div><label class="{{ $label }}" for="f-mprn">MPRN</label><input style="width:100%" id="f-mprn" name="mprn" value="{{ old('mprn', $data['mprn']) }}" class="{{ $input }}" required inputmode="numeric"></div>
                        <div><label class="{{ $label }}" for="f-account">Account number</label><input style="width:100%" id="f-account" name="account_number" value="{{ old('account_number', $data['account_number']) }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="f-number">Bill number</label><input style="width:100%" id="f-number" name="bill_number" value="{{ old('bill_number', $data['bill_number']) }}" class="{{ $input }}" required></div>
                        <div>
                            <label class="{{ $label }}" for="f-type">Document</label>
                            <select style="width:100%" id="f-type" name="document_type" class="{{ $input }}">
                                <option value="invoice" @selected(old('document_type', $data['document_type']) === 'invoice')>Bill</option>
                                <option value="credit_note" @selected(old('document_type', $data['document_type']) === 'credit_note')>Credit</option>
                            </select>
                        </div>
                        <div><label class="{{ $label }}" for="f-issue">Date of issue</label><input style="width:100%" id="f-issue" type="date" name="issue_date" value="{{ old('issue_date', $data['issue_date']) }}" class="{{ $input }}" required></div>
                        <div><label class="{{ $label }}" for="f-from">Period from</label><input style="width:100%" id="f-from" type="date" name="period_start" value="{{ old('period_start', $data['period_start']) }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="f-to">Period to</label><input style="width:100%" id="f-to" type="date" name="period_end" value="{{ old('period_end', $data['period_end']) }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="f-due">Due date</label><input style="width:100%" id="f-due" type="date" name="due_date" value="{{ old('due_date', $data['due_date']) }}" class="{{ $input }}"></div>
                        <div class="col-span-2 md:col-span-3"><label class="{{ $label }}" for="f-tariff">Tariff</label><input style="width:100%" id="f-tariff" name="tariff" value="{{ old('tariff', $data['tariff']) }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="f-net">Net total €</label><input style="width:100%" id="f-net" type="number" step="0.01" name="net_total" value="{{ old('net_total', $data['net_total']) }}" class="{{ $input }}" required></div>
                        <div><label class="{{ $label }}" for="f-vat">VAT €</label><input style="width:100%" id="f-vat" type="number" step="0.01" name="vat_total" value="{{ old('vat_total', $data['vat_total']) }}" class="{{ $input }}" required></div>
                        <div><label class="{{ $label }}" for="f-export">Export credit €</label><input style="width:100%" id="f-export" type="number" step="0.01" name="export_credit_total" value="{{ old('export_credit_total', $data['export_credit_total']) }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="f-gross">Bill total €</label><input style="width:100%" id="f-gross" type="number" step="0.01" name="gross_total" value="{{ old('gross_total', $data['gross_total']) }}" class="{{ $input }}" required></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Meter readings</h3>
                        <button type="button" @click="addReading()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">+ Add row</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="pr-2 pb-2 font-medium">Meter</th><th class="pr-2 pb-2 font-medium">Band</th>
                                    <th class="pr-2 pb-2 font-medium">From</th><th class="pr-2 pb-2 font-medium">To</th>
                                    <th class="pr-2 pb-2 font-medium">Previous</th><th class="pr-2 pb-2 font-medium">Read</th>
                                    <th class="pr-2 pb-2 font-medium">Present</th><th class="pr-2 pb-2 font-medium">Read</th>
                                    <th class="pr-2 pb-2 font-medium">kWh</th><th class="pr-2 pb-2 font-medium" title="Takes back a row billed earlier">Reversal</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(r, i) in readings" :key="r._k">
                                    <tr>
                                        <td class="pr-2 pb-2"><input :name="`readings[${i}][meter_serial]`" x-model="r.meter_serial" class="{{ $input }} w-28" required aria-label="Meter number"></td>
                                        <td class="pr-2 pb-2">
                                            <select :name="`readings[${i}][band]`" x-model="r.band" class="{{ $input }} w-20" aria-label="Band">
                                                @foreach($bands as $band)<option value="{{ $band }}">{{ $band }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="pr-2 pb-2"><input type="date" :name="`readings[${i}][period_start]`" x-model="r.period_start" class="{{ $input }} w-36" required aria-label="From"></td>
                                        <td class="pr-2 pb-2"><input type="date" :name="`readings[${i}][period_end]`" x-model="r.period_end" class="{{ $input }} w-36" required aria-label="To"></td>
                                        <td class="pr-2 pb-2"><input type="number" step="any" :name="`readings[${i}][previous_reading]`" x-model="r.previous_reading" class="{{ $input }} w-24" required aria-label="Previous reading"></td>
                                        <td class="pr-2 pb-2">
                                            <select :name="`readings[${i}][previous_type]`" x-model="r.previous_type" class="{{ $input }} w-28" aria-label="Previous read type">
                                                @foreach($readTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="pr-2 pb-2"><input type="number" step="any" :name="`readings[${i}][present_reading]`" x-model="r.present_reading" class="{{ $input }} w-24" required aria-label="Present reading"></td>
                                        <td class="pr-2 pb-2">
                                            <select :name="`readings[${i}][present_type]`" x-model="r.present_type" class="{{ $input }} w-28" aria-label="Present read type">
                                                @foreach($readTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="pr-2 pb-2">
                                            <input type="number" step="any" :name="`readings[${i}][kwh]`" x-model="r.kwh" class="{{ $input }} w-24" required aria-label="kWh">
                                            <input type="hidden" :name="`readings[${i}][multiplier]`" :value="r.multiplier ?? 1">
                                        </td>
                                        <td class="pr-2 pb-2 text-center">
                                            <input type="hidden" :name="`readings[${i}][is_reversal]`" :value="r.is_reversal ? 1 : 0">
                                            <input type="checkbox" x-model="r.is_reversal" class="rounded border-gray-300 dark:border-gray-600" aria-label="Reversal">
                                        </td>
                                        <td class="pb-2"><button type="button" @click="readings.splice(i, 1)" class="text-gray-400 hover:text-red-600" aria-label="Remove row">✕</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Meters add up to <span class="font-medium tabular-nums" x-text="fmt(readingKwh())"></span> kWh; energy lines charge <span class="font-medium tabular-nums" x-text="fmt(energyKwh())"></span> kWh.</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Charges</h3>
                        <button type="button" @click="addCharge()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">+ Add line</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="pr-2 pb-2 font-medium">Type</th><th class="pr-2 pb-2 font-medium">Band</th><th class="pr-2 pb-2 font-medium">As printed</th>
                                    <th class="pr-2 pb-2 font-medium">Units</th><th class="pr-2 pb-2 font-medium">Per</th><th class="pr-2 pb-2 font-medium">Rate €</th>
                                    <th class="pr-2 pb-2 font-medium">From</th><th class="pr-2 pb-2 font-medium">To</th>
                                    <th class="pr-2 pb-2 font-medium">VAT %</th><th class="pr-2 pb-2 font-medium">Amount €</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(c, i) in charges" :key="c._k">
                                    <tr>
                                        <td class="pr-2 pb-2">
                                            <select :name="`charges[${i}][category]`" x-model="c.category" class="{{ $input }} w-36" aria-label="Type of charge">
                                                @foreach($categories as $category)<option value="{{ $category }}">{{ $categoryNames[$category] ?? $category }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="pr-2 pb-2">
                                            <select :name="`charges[${i}][band]`" x-model="c.band" class="{{ $input }} w-20" aria-label="Band">
                                                <option value="">–</option>
                                                @foreach($bands as $band)<option value="{{ $band }}">{{ $band }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="pr-2 pb-2"><input :name="`charges[${i}][description]`" x-model="c.description" class="{{ $input }} w-56" aria-label="Description as printed"></td>
                                        <td class="pr-2 pb-2"><input type="number" step="any" :name="`charges[${i}][quantity]`" x-model="c.quantity" class="{{ $input }} w-24" aria-label="Units"></td>
                                        <td class="pr-2 pb-2">
                                            <select :name="`charges[${i}][unit]`" x-model="c.unit" class="{{ $input }} w-24" aria-label="Unit">
                                                <option value="">–</option>
                                                @foreach(['kwh' => 'kWh', 'mwh' => 'MWh', 'day' => 'day', 'month' => 'month', 'kva' => 'kVA'] as $unit => $name)<option value="{{ $unit }}">{{ $name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="pr-2 pb-2"><input type="number" step="any" :name="`charges[${i}][rate]`" x-model="c.rate" class="{{ $input }} w-24" aria-label="Rate"></td>
                                        <td class="pr-2 pb-2"><input type="date" :name="`charges[${i}][period_start]`" x-model="c.period_start" class="{{ $input }} w-36" aria-label="From"></td>
                                        <td class="pr-2 pb-2"><input type="date" :name="`charges[${i}][period_end]`" x-model="c.period_end" class="{{ $input }} w-36" aria-label="To"></td>
                                        <td class="pr-2 pb-2"><input type="number" step="any" :name="`charges[${i}][vat_rate]`" x-model="c.vat_rate" class="{{ $input }} w-20" aria-label="VAT rate"></td>
                                        <td class="pr-2 pb-2"><input type="number" step="0.01" :name="`charges[${i}][net_amount]`" x-model="c.net_amount" class="{{ $input }} w-28" required aria-label="Amount"></td>
                                        <td class="pb-2"><button type="button" @click="charges.splice(i, 1)" class="text-gray-400 hover:text-red-600" aria-label="Remove line">✕</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Lines add up to <span class="font-medium tabular-nums" x-text="'€' + fmt(chargeTotal(), 2)"></span> before any export credit.</p>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">Save changes</button>
                    <button type="button" @click="editing = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Cancel</button>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Saving re-runs the checks and puts the bill back to review until it is confirmed.</span>
                </div>
            </form>
        </div>
    </div>

    <script>
        function electricityBill(initial) {
            let key = 0;
            const keyed = rows => (rows || []).map(r => ({ ...r, band: r.band ?? '', unit: r.unit ?? '', is_reversal: !!Number(r.is_reversal), _k: ++key }));
            return {
                editing: !!initial.editing,
                charges: keyed(initial.charges),
                readings: keyed(initial.readings),
                fmt: (v, d = 0) => Number(v || 0).toLocaleString('en-IE', { minimumFractionDigits: d, maximumFractionDigits: d }),
                addCharge() {
                    this.charges.push({ _k: ++key, category: 'other', band: '', description: '', quantity: '', unit: '', rate: '', period_start: '', period_end: '', vat_rate: 9, net_amount: '' });
                },
                addReading() {
                    const last = this.readings[this.readings.length - 1] || {};
                    this.readings.push({ _k: ++key, meter_serial: last.meter_serial || '', band: last.band || '24h', period_start: last.period_start || '', period_end: last.period_end || '',
                        previous_reading: '', previous_type: 'actual', present_reading: '', present_type: 'actual', multiplier: 1, kwh: '', is_reversal: false });
                },
                readingKwh() { return this.readings.reduce((s, r) => s + Number(r.kwh || 0), 0); },
                energyKwh() { return this.charges.filter(c => c.category === 'energy').reduce((s, c) => s + Number(c.quantity || 0) * (c.unit === 'mwh' ? 1000 : 1), 0); },
                chargeTotal() { return this.charges.filter(c => c.category !== 'export_credit').reduce((s, c) => s + Number(c.net_amount || 0), 0); },
            };
        }
    </script>
</x-admin-layout>
