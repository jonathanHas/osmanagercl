{{--
    Payment → invoice allocation table.

    Shared by "Record payment" (customer-payments/create) and "Edit allocations"
    (customer-payments/allocations). The markup is presentation only — the
    enclosing Alpine root supplies the state and behaviour, which is what
    customerAllocationCore() in _allocation-script.blade.php provides.

    Contract — the enclosing x-data must expose:
      properties  customer, openInvoices, amount, loadingInvoices
                  each openInvoices entry: {id, invoice_number, issue_date,
                  total, outstanding, allocate}
      methods     totalAllocated(), unallocated(), overAllocated(),
                  autoAllocate(), autoAllocateOldest(), splitEqually(),
                  clearAllocations(), toggleInvoice(), isApplied(),
                  isFullyApplied(), isExactMatch(), cannotApply()

    The input names allocations[${idx}][customer_invoice_id] / [amount] are the
    contract with CustomerPaymentAllocationRequest — don't change their shape.
--}}
@props([
    'heading' => 'Apply to invoices',
    // "Auto-allocate" rewrites the payment amount, so it only makes sense while
    // the amount is still editable — i.e. on the create form.
    'showAutoAllocate' => true,
    // The edit page always has a customer; the create form may not yet.
    'showCustomerPrompt' => true,
    'emptyMessage' => null,
])

<div class="bg-gray-800 p-4 rounded">
    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
        <h3 class="text-gray-200 font-semibold">{{ $heading }}</h3>
        <div class="flex flex-wrap gap-2 text-sm">
            @if ($showAutoAllocate)
                <button type="button" @click="autoAllocate()"
                        :disabled="!customer.id || openInvoices.length === 0"
                        :class="(!customer.id || openInvoices.length === 0) ? 'opacity-50 cursor-not-allowed' : ''"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded inline-flex items-center gap-1"
                        title="Fill the amount with the customer's outstanding total and apply oldest first">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Auto-allocate
                </button>
            @endif
            <button type="button" @click="autoAllocateOldest()"
                    :disabled="openInvoices.length === 0"
                    :class="openInvoices.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                    class="bg-gray-700 hover:bg-gray-600 text-gray-100 px-3 py-1.5 rounded">
                Apply oldest first
            </button>
            <button type="button" @click="splitEqually()"
                    :disabled="openInvoices.length === 0"
                    :class="openInvoices.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                    class="bg-gray-700 hover:bg-gray-600 text-gray-100 px-3 py-1.5 rounded">
                Split equally
            </button>
            <button type="button" @click="clearAllocations()"
                    class="text-gray-400 hover:text-gray-200 px-2">Clear</button>
        </div>
    </div>

    @if ($showCustomerPrompt)
        <template x-if="!customer.id">
            <div class="text-gray-500 text-sm">Pick a customer to load their open invoices.</div>
        </template>
    @endif

    <template x-if="customer.id && loadingInvoices">
        <div class="text-gray-500 text-sm">Loading invoices…</div>
    </template>

    <template x-if="customer.id && !loadingInvoices && openInvoices.length === 0">
        <div class="text-gray-500 text-sm">
            @if ($emptyMessage)
                {{ $emptyMessage }}
            @else
                This customer has no open invoices. The full €<span x-text="(amount || 0).toFixed(2)"></span> will be recorded as on-account credit.
            @endif
        </div>
    </template>

    <template x-if="customer.id && !loadingInvoices && openInvoices.length > 0">
        <div>
            <table class="min-w-full text-sm">
                <thead class="text-gray-400 text-xs uppercase">
                    <tr>
                        <th class="px-2 py-1 w-8"><span class="sr-only">Apply in full</span></th>
                        <th class="px-2 py-1 text-left">Invoice</th>
                        <th class="px-2 py-1 text-left">Date</th>
                        <th class="px-2 py-1 text-right">Total</th>
                        <th class="px-2 py-1 text-right">Outstanding</th>
                        <th class="px-2 py-1 text-right w-32">Apply</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(inv, idx) in openInvoices" :key="inv.id">
                        <tr class="border-t border-gray-700"
                            :class="isExactMatch(inv) && !isApplied(inv) ? 'bg-green-900/20' : ''">
                            <td class="px-2 py-1">
                                {{-- Tick to settle the invoice in full, untick to clear it. --}}
                                <input type="checkbox"
                                       :checked="isFullyApplied(inv)"
                                       :disabled="cannotApply(inv)"
                                       x-effect="$el.indeterminate = isApplied(inv) && !isFullyApplied(inv)"
                                       @change="toggleInvoice(inv)"
                                       :title="cannotApply(inv)
                                           ? 'The payment is fully allocated — clear another row first'
                                           : (isFullyApplied(inv) ? 'Clear this invoice' : 'Apply as much as possible to this invoice')"
                                       :class="cannotApply(inv) ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'"
                                       class="w-4 h-4 rounded bg-gray-900 border-gray-600 text-green-600 focus:ring-green-500">
                            </td>
                            <td class="px-2 py-1 font-mono">
                                <input type="hidden" :name="`allocations[${idx}][customer_invoice_id]`" :value="inv.id">
                                <span x-text="inv.invoice_number || '(draft)'"></span>
                                <template x-if="isExactMatch(inv)">
                                    <span class="ml-1 text-[10px] uppercase tracking-wide bg-green-800 text-green-100 px-1.5 py-0.5 rounded font-sans"
                                          title="This invoice's outstanding amount matches the payment exactly">exact match</span>
                                </template>
                            </td>
                            <td class="px-2 py-1 text-gray-300" x-text="inv.issue_date"></td>
                            <td class="px-2 py-1 text-right font-mono">€<span x-text="inv.total.toFixed(2)"></span></td>
                            <td class="px-2 py-1 text-right font-mono text-yellow-400">€<span x-text="inv.outstanding.toFixed(2)"></span></td>
                            <td class="px-2 py-1 text-right">
                                <input type="number" step="0.01" min="0" inputmode="decimal"
                                       :name="`allocations[${idx}][amount]`"
                                       x-model.number="inv.allocate"
                                       :max="inv.outstanding"
                                       class="w-28 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100 text-right font-mono">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="mt-3 flex justify-between items-center text-sm border-t border-gray-700 pt-3">
                <div class="text-gray-300">
                    <span class="text-gray-500">Allocated:</span>
                    <span class="font-mono">€<span x-text="totalAllocated().toFixed(2)"></span></span>
                    <span class="text-gray-500"> / €<span x-text="(amount || 0).toFixed(2)"></span></span>
                </div>
                <div :class="overAllocated() ? 'text-red-400' : (unallocated() > 0.005 ? 'text-blue-300' : 'text-green-400')">
                    <template x-if="overAllocated()">
                        <span>Over-allocated by €<span x-text="(totalAllocated() - amount).toFixed(2)"></span></span>
                    </template>
                    <template x-if="!overAllocated() && unallocated() > 0.005">
                        <span>€<span x-text="unallocated().toFixed(2)"></span> on-account credit</span>
                    </template>
                    <template x-if="!overAllocated() && unallocated() <= 0.005 && totalAllocated() > 0">
                        <span>Fully allocated</span>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
