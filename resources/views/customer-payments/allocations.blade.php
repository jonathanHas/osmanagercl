<x-admin-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
         x-data="customerPaymentAllocationForm({{ \Illuminate\Support\Js::from([
             'urls' => [],
             'preselectCustomer' => [
                 'id' => $payment->customer->id,
                 'name' => $payment->customer->name,
             ],
             // Rendered server-side: `outstanding` is headroom ignoring this
             // payment's own rows, so the invoices it already pays stay editable.
             'invoiceRows' => $invoiceRows,
             'amount' => (float) $payment->amount,
             // The banked amount is fixed — ticking can never grow it.
             'amountIsEditable' => false,
         ]) }})">

        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Edit allocations</h2>
                <p class="text-gray-400 text-sm mt-1">
                    Payment #{{ $payment->id }} ·
                    <a href="{{ route('customers.show', $payment->customer) }}"
                       class="text-blue-400 hover:text-blue-300">{{ $payment->customer->name }}</a>
                </p>
            </div>
            <a href="{{ route('customer-payments.show', $payment) }}" class="text-gray-400 hover:text-gray-200">← Back to payment</a>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-800 text-white p-3 rounded text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- The payment itself is fixed once banked — only the matching changes here. --}}
        <div class="bg-gray-800 p-4 rounded mb-4">
            <div class="flex flex-wrap gap-x-8 gap-y-2 items-baseline">
                <div>
                    <div class="text-gray-400 text-xs uppercase">Amount</div>
                    <div class="text-2xl font-bold text-gray-100 font-mono">€{{ number_format($payment->amount, 2) }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-xs uppercase">Date</div>
                    <div class="text-gray-200">{{ $payment->payment_date->format('d M Y') }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-xs uppercase">Method</div>
                    <div class="text-gray-200">
                        {{ $payment->methodLabel() }}@if ($payment->till_name) · {{ $payment->till_name }}@endif
                    </div>
                </div>
                @if ($payment->reference)
                    <div>
                        <div class="text-gray-400 text-xs uppercase">Reference</div>
                        <div class="text-gray-200">{{ $payment->reference }}</div>
                    </div>
                @endif
            </div>
            <p class="text-gray-500 text-xs mt-3">
                The payment's amount, date and method can't be changed here — only which invoices it pays.
                @if ($payment->last_matched_at)
                    Last re-matched {{ $payment->last_matched_at->format('Y-m-d H:i') }}@if ($payment->lastMatcher) by {{ $payment->lastMatcher->name }}@endif.
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('customer-payments.allocations.update', $payment) }}" class="space-y-4">
            @csrf @method('PUT')

            <x-customer-payments.allocation-table
                heading="Apply to invoices"
                :show-auto-allocate="false"
                :show-customer-prompt="false"
                empty-message="This customer has no invoices left to allocate against. The full payment stays as on-account credit." />

            <div class="flex justify-between items-center gap-2">
                {{-- Submits the sibling auto-match form; forms can't be nested. --}}
                <button type="submit" form="auto-match-{{ $payment->id }}"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-100 px-4 py-2 rounded text-sm">
                    Match oldest first &amp; save
                </button>
                <div class="flex gap-2">
                    <a href="{{ route('customer-payments.show', $payment) }}"
                       class="px-4 py-2 text-gray-300 hover:text-white">Cancel</a>
                    <button type="submit"
                            :disabled="overAllocated()"
                            :class="overAllocated() ? 'opacity-50 cursor-not-allowed' : ''"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        Save allocations
                    </button>
                </div>
            </div>
        </form>

        <form method="POST" id="auto-match-{{ $payment->id }}"
              action="{{ route('customer-payments.allocations.auto', $payment) }}" class="hidden">
            @csrf
        </form>
    </div>

    @include('customer-payments._allocation-script')

    <script>
        function customerPaymentAllocationForm(config) {
            return {
                ...customerAllocationCore(config),

                // Fixed — the payment amount is the ceiling, not an input.
                amount: config.amount,

                init() {
                    this.initAllocations();
                },
            };
        }
    </script>
</x-admin-layout>
