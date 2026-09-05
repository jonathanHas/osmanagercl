<script>
    /**
     * Allocation state and behaviour shared by the "Record payment" and
     * "Edit allocations" pages — the half of each page's Alpine component that the
     * customer-payments.allocation-table component depends on.
     *
     * Spread it into the page's own component rather than nesting, so the table's
     * bindings resolve against a single root:
     *
     *     function myPageForm(config) {
     *         return { ...customerAllocationCore(config), ...pageSpecificStuff };
     *     }
     *
     * Alpine calls only the root's init(), so the initialiser here is named
     * initAllocations() and each page calls it from its own init() — otherwise
     * the page's init() would silently shadow this one.
     */
    function customerAllocationCore(config) {
        return {
            urls: config.urls,
            customer: config.preselectCustomer ?? { id: null, name: '', email: '', phone: '' },
            amount: 0,
            openInvoices: [],
            loadingInvoices: false,
            // Record-payment can grow the amount to fit; edit-allocations can't —
            // there the banked figure is the ceiling.
            amountIsEditable: config.amountIsEditable ?? false,

            initAllocations() {
                // Rows rendered server-side (the edit page) skip the fetch entirely.
                if (config.invoiceRows) {
                    this.openInvoices = config.invoiceRows.map(inv => ({
                        ...inv,
                        allocate: inv.allocated ?? 0,
                    }));
                    return Promise.resolve();
                }
                if (this.customer.id) {
                    return this.loadOpenInvoices();
                }
                return Promise.resolve();
            },

            async loadOpenInvoices() {
                if (!this.customer.id) return;
                this.loadingInvoices = true;
                try {
                    const url = this.urls.openInvoices.replace('__ID__', this.customer.id);
                    const r = await fetch(url, { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    this.openInvoices = (j.data ?? []).map(inv => ({ ...inv, allocate: 0 }));
                } finally {
                    this.loadingInvoices = false;
                }
            },

            /** Most that can go on this row: what's left of the payment, capped by the invoice. */
            remainingFor(inv) {
                const available = this.unallocated() + (parseFloat(inv.allocate) || 0);
                return Math.round(Math.min(Math.max(available, 0), inv.outstanding) * 100) / 100;
            },

            isApplied(inv) {
                return (parseFloat(inv.allocate) || 0) > 0.005;
            },

            isFullyApplied(inv) {
                const applied = parseFloat(inv.allocate) || 0;
                return applied > 0.005 && applied >= inv.outstanding - 0.005;
            },

            /** The payment settles this invoice exactly — worth pointing out. */
            isExactMatch(inv) {
                return (this.amount || 0) > 0.005
                    && Math.abs(inv.outstanding - this.amount) < 0.005;
            },

            /** Nothing left to put here, and nothing here to take away. */
            cannotApply(inv) {
                return ! this.isApplied(inv)
                    && this.remainingFor(inv) <= 0.005
                    && ! this.amountIsEditable;
            },

            /**
             * Tick to settle the invoice, untick to clear it — so an amount that
             * matches an invoice can be applied without retyping the figure.
             * A partly-applied row fills to the maximum rather than clearing.
             */
            toggleInvoice(inv) {
                if (this.isFullyApplied(inv)) {
                    inv.allocate = 0;
                    return;
                }

                const room = this.remainingFor(inv);
                if (room > 0.005) {
                    inv.allocate = room;
                    return;
                }

                // The payment is already spoken for. Where the amount is still
                // ours to set, grow it to cover this invoice — the same thing
                // Auto-allocate does.
                if (this.amountIsEditable) {
                    inv.allocate = inv.outstanding;
                    this.amount = this.totalAllocated();
                }
            },

            totalAllocated() {
                return Math.round(
                    this.openInvoices.reduce((s, i) => s + (parseFloat(i.allocate) || 0), 0) * 100
                ) / 100;
            },
            unallocated() {
                return Math.round(((this.amount || 0) - this.totalAllocated()) * 100) / 100;
            },
            overAllocated() {
                return this.totalAllocated() > (this.amount || 0) + 0.005;
            },

            autoAllocateOldest() {
                let remaining = this.amount || 0;
                for (const inv of this.openInvoices) {
                    if (remaining <= 0.005) { inv.allocate = 0; continue; }
                    const apply = Math.min(remaining, inv.outstanding);
                    inv.allocate = Math.round(apply * 100) / 100;
                    remaining = Math.round((remaining - apply) * 100) / 100;
                }
            },

            /**
             * One-click flow: set the amount to the customer's full outstanding total
             * and apply it oldest-first. Common case for "pay everything they owe".
             * Only offered where the amount is still editable.
             */
            autoAllocate() {
                if (this.openInvoices.length === 0) return;
                const totalOutstanding = this.openInvoices
                    .reduce((s, inv) => s + (parseFloat(inv.outstanding) || 0), 0);
                this.amount = Math.round(totalOutstanding * 100) / 100;
                this.autoAllocateOldest();
            },

            splitEqually() {
                if (this.openInvoices.length === 0) return;
                const each = Math.round(((this.amount || 0) / this.openInvoices.length) * 100) / 100;
                for (const inv of this.openInvoices) {
                    inv.allocate = Math.min(each, inv.outstanding);
                }
            },

            clearAllocations() {
                for (const inv of this.openInvoices) inv.allocate = 0;
            },
        };
    }
</script>
