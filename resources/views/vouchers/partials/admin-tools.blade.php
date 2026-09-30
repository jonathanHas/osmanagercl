{{--
    Admin changeover tools (vouchers cycle 4): bulk make for sale, deactivate,
    reactivate, delete and restore. Included by vouchers/list.blade.php only when
    `$adminTools` (admin + config('vouchers.admin_tools')). Everything the tools
    need in the browser lives here, so removing this partial (and its @include
    and the checkbox cells in list.blade.php) removes them.

    The row checkboxes sit in the table, outside this form; they join it through
    their `form="voucher-bulk"` attribute. The header "select all" checkbox is
    found by its `data-bulk-all` attribute.
--}}
<div x-data="voucherBulk()"
     data-deleted-view="{{ $showDeleted ? '1' : '0' }}"
     data-url-deactivate="{{ route('vouchers.bulk.deactivate') }}"
     data-url-reactivate="{{ route('vouchers.bulk.reactivate') }}"
     data-url-delete="{{ route('vouchers.bulk.delete') }}"
     data-url-restore="{{ route('vouchers.bulk.restore') }}"
     data-url-for-sale="{{ route('vouchers.bulk.for-sale') }}"
     x-on:keydown.escape.window="cancel()"
     class="mb-4">
    <form id="voucher-bulk" method="POST" action="{{ route('vouchers.bulk.deactivate') }}"
          class="bg-gray-800 rounded p-4 flex flex-wrap items-end gap-3">
        @csrf
        <input type="hidden" name="note" :value="note">

        <div class="text-sm text-gray-300 min-w-[9rem]">
            <span class="font-semibold text-gray-100" x-text="count"></span> selected
            <span class="block text-xs text-gray-500" x-show="count > 0" x-text="'balance ' + money(total)"></span>
        </div>

        <div class="flex-1 min-w-[14rem]">
            <label for="voucher-bulk-note" class="block text-xs text-gray-400 mb-1">Note (a reason is required to delete)</label>
            <textarea id="voucher-bulk-note" rows="1" maxlength="500" x-model="note"
                      class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100 text-sm"></textarea>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($showDeleted)
                <button type="button" x-on:click="ask('restore')" :disabled="count === 0"
                        class="bg-green-700 hover:bg-green-600 disabled:bg-gray-600 disabled:text-gray-400 text-white text-sm font-medium py-2 px-3 rounded">Restore selected</button>
            @else
                <button type="button" x-on:click="ask('forSale')" :disabled="count === 0"
                        class="bg-yellow-700 hover:bg-yellow-600 disabled:bg-gray-600 disabled:text-gray-400 text-white text-sm font-medium py-2 px-3 rounded">Make for sale</button>
                <button type="button" x-on:click="ask('deactivate')" :disabled="count === 0"
                        class="bg-gray-600 hover:bg-gray-500 disabled:bg-gray-600 disabled:text-gray-400 text-white text-sm font-medium py-2 px-3 rounded">Deactivate selected</button>
                <button type="button" x-on:click="ask('reactivate')" :disabled="count === 0"
                        class="bg-gray-600 hover:bg-gray-500 disabled:bg-gray-600 disabled:text-gray-400 text-white text-sm font-medium py-2 px-3 rounded">Reactivate selected</button>
                <button type="button" x-on:click="ask('delete')" :disabled="count === 0"
                        class="bg-red-700 hover:bg-red-600 disabled:bg-gray-600 disabled:text-gray-400 text-white text-sm font-medium py-2 px-3 rounded">Delete selected</button>
            @endif
        </div>
    </form>

    <!-- Confirmation dialog (no browser confirm()) -->
    <div x-show="action" x-cloak style="display:none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" x-on:click="cancel()"></div>

        <div class="relative bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 text-gray-100" x-on:click.stop>
            <h3 class="text-lg font-semibold mb-2" x-text="title()"></h3>
            <p class="text-sm text-gray-300 mb-4" x-text="body()"></p>

            <label for="voucher-bulk-dialog-note" class="block text-xs text-gray-400 mb-1"
                   x-text="action === 'delete' ? 'Reason (required, at least 3 characters)' : 'Note (optional)'"></label>
            <textarea id="voucher-bulk-dialog-note" rows="2" maxlength="500" x-model="note"
                      class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100 mb-4"></textarea>

            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="cancel()"
                        class="px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-white">Cancel</button>
                <button type="button" x-on:click="confirm()" :disabled="! canConfirm()"
                        class="px-4 py-2 rounded font-medium text-white disabled:bg-gray-600 disabled:text-gray-400"
                        :class="action === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                        x-text="confirmLabel()"></button>
            </div>
        </div>
    </div>

    <script>
        function voucherBulk() {
            return {
                count: 0,
                total: 0,
                note: '',
                action: null,
                submitting: false,

                init() {
                    document.addEventListener('change', (e) => {
                        if (e.target.matches('[data-bulk-all]')) {
                            this.boxes().forEach((b) => { b.checked = e.target.checked; });
                        }
                        if (e.target.matches('[data-bulk-all], input[name="ids[]"][form="voucher-bulk"]')) {
                            this.recount();
                        }
                    });
                    this.recount();
                },

                boxes() {
                    return Array.from(document.querySelectorAll('input[name="ids[]"][form="voucher-bulk"]'));
                },

                recount() {
                    const checked = this.boxes().filter((b) => b.checked);
                    this.count = checked.length;
                    this.total = checked.reduce((sum, b) => sum + Number(b.dataset.balance || 0), 0);

                    const all = document.querySelector('[data-bulk-all]');
                    if (all) all.checked = this.count > 0 && this.count === this.boxes().length;
                },

                ask(action) {
                    if (this.count === 0) return;
                    this.action = action;
                },

                cancel() {
                    if (! this.submitting) this.action = null;
                },

                plural() {
                    return this.count + ' ' + (this.count === 1 ? 'voucher' : 'vouchers');
                },

                title() {
                    switch (this.action) {
                        case 'deactivate': return 'Deactivate ' + this.plural() + '?';
                        case 'reactivate': return 'Reactivate ' + this.plural() + '?';
                        case 'restore': return 'Restore ' + this.plural() + '?';
                        case 'forSale': return 'Make ' + this.plural() + ' for sale?';
                        case 'delete': return 'Delete ' + this.plural() + '?';
                        default: return '';
                    }
                },

                body() {
                    switch (this.action) {
                        case 'deactivate': return 'Only active vouchers are changed; any others are skipped and listed afterwards.';
                        case 'reactivate': return 'Only deactivated vouchers are changed; any others are skipped and listed afterwards.';
                        case 'restore': return 'Each voucher comes back with the status and balance it had when it was deleted.';
                        case 'forSale':
                            return this.plural() + ' worth ' + this.money(this.total) + ' go back to unsold. '
                                + 'Their balances become their sale value, and each activates again when it is sold at the till. '
                                + 'Leave out any voucher a customer already holds.';
                        case 'delete':
                            return this.plural() + ' will be deleted. Deleted vouchers leave the list, the activity log and the totals. '
                                + 'They can be restored from the Deleted view. Active vouchers are skipped: deactivate them first.';
                        default: return '';
                    }
                },

                confirmLabel() {
                    return { deactivate: 'Deactivate', reactivate: 'Reactivate', restore: 'Restore', forSale: 'Make for sale', delete: 'Delete' }[this.action] || 'Confirm';
                },

                canConfirm() {
                    if (this.submitting || this.count === 0) return false;
                    if (this.action === 'delete') return this.note.trim().length >= 3;
                    return true;
                },

                confirm() {
                    if (! this.canConfirm()) return;

                    const urls = {
                        deactivate: this.$root.dataset.urlDeactivate,
                        reactivate: this.$root.dataset.urlReactivate,
                        delete: this.$root.dataset.urlDelete,
                        restore: this.$root.dataset.urlRestore,
                        forSale: this.$root.dataset.urlForSale,
                    };

                    this.submitting = true;
                    const form = document.getElementById('voucher-bulk');
                    form.action = urls[this.action];
                    // The hidden note input is bound with :value; make sure it is current.
                    form.querySelector('input[name="note"]').value = this.note;
                    form.submit();
                },

                money(n) {
                    return '€' + Number(n || 0).toFixed(2);
                },
            };
        }
    </script>
</div>
