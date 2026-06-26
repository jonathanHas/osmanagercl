<x-admin-layout>
    @php($isAdmin = auth()->user()->hasRole('admin'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="voucherAdmin()">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Vouchers</h2>
            <div class="flex gap-2">
                <a href="{{ route('vouchers.index') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Till screen</a>
                <a href="{{ route('vouchers.generate') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">+ Generate</a>
            </div>
        </div>

        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4 bg-gray-800 p-4 rounded">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">Search code</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Voucher code…"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
            </div>
            <div class="flex items-end gap-2">
                <select name="status" class="bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
                    <option value="">All statuses</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="deactivated" @selected(request('status') === 'deactivated')>Deactivated</option>
                    <option value="exhausted" @selected(request('status') === 'exhausted')>Exhausted</option>
                </select>
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">Filter</button>
                <a href="{{ route('vouchers.list') }}" class="text-gray-400 hover:text-gray-200 px-2 py-2">Reset</a>
            </div>
        </form>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Code</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-right">Initial</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                        <th class="px-4 py-2 text-left">Created by</th>
                        <th class="px-4 py-2 text-left">Created</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($vouchers as $voucher)
                        <tr class="hover:bg-gray-700/40">
                            <td class="px-4 py-2 font-mono font-medium">{{ $voucher->code }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $badge = match ($voucher->status) {
                                        'active' => 'bg-green-800/50 text-green-300',
                                        'deactivated' => 'bg-red-800/50 text-red-300',
                                        'exhausted' => 'bg-gray-700 text-gray-400',
                                        default => 'bg-yellow-800/50 text-yellow-300',
                                    };
                                @endphp
                                <span class="text-xs px-2 py-0.5 rounded {{ $badge }}">{{ ucfirst($voucher->status) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">{{ $voucher->initial_value !== null ? '€'.number_format($voucher->initial_value, 2) : '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium">€{{ number_format($voucher->current_balance, 2) }}</td>
                            <td class="px-4 py-2 text-gray-300">{{ $voucher->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $voucher->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2 text-right space-x-3 whitespace-nowrap">
                                @if ($isAdmin && in_array($voucher->status, ['active', 'deactivated']))
                                    <button type="button"
                                            @click="openEdit({{ $voucher->id }}, @js($voucher->code), @js($voucher->status), {{ (float) $voucher->current_balance }})"
                                            class="text-yellow-400 hover:text-yellow-300">Edit</button>
                                @endif
                                <a href="{{ route('vouchers.transactions', $voucher) }}" class="text-blue-400 hover:text-blue-300">Log ({{ $voucher->transactions_count }})</a>
                                <a href="{{ route('vouchers.print', ['ids' => $voucher->id]) }}" target="_blank" class="text-gray-400 hover:text-gray-200">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No vouchers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $vouchers->links() }}</div>

        @if ($isAdmin)
            <!-- Edit modal -->
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="display:none;" @keydown.escape.window="close()">
                <div class="absolute inset-0 bg-black/60" @click="close()"></div>

                <div class="relative bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 text-gray-100" @click.stop>
                    <h3 class="text-lg font-semibold mb-1">Edit voucher</h3>
                    <p class="font-mono text-sm text-gray-400 mb-4" x-text="voucher.code"></p>

                    <div class="flex items-center justify-between bg-gray-900 rounded p-3 mb-4">
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            <p class="text-sm font-medium"
                               :class="voucher.status === 'active' ? 'text-green-400' : 'text-red-400'"
                               x-text="voucher.status.charAt(0).toUpperCase() + voucher.status.slice(1)"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Balance</p>
                            <p class="text-sm font-medium" x-text="'€' + Number(voucher.balance).toFixed(2)"></p>
                        </div>
                    </div>

                    <label class="block text-xs text-gray-400 mb-1">Reason / note (optional)</label>
                    <textarea x-model="note" rows="2" maxlength="500"
                              placeholder="Why is this being changed?"
                              class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100 mb-4"></textarea>

                    <div x-show="error" x-cloak class="mb-3 rounded bg-red-700 text-white px-3 py-2 text-sm" x-text="error"></div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="close()"
                                class="px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-white">Cancel</button>

                        <template x-if="voucher.status === 'active'">
                            <button type="button" @click="submit('deactivate')" :disabled="processing"
                                    class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 disabled:bg-gray-500 text-white font-medium">
                                <span x-show="!processing">Deactivate</span>
                                <span x-show="processing">Working…</span>
                            </button>
                        </template>

                        <template x-if="voucher.status === 'deactivated'">
                            <button type="button" @click="submit('reactivate')" :disabled="processing"
                                    class="px-4 py-2 rounded bg-green-600 hover:bg-green-700 disabled:bg-gray-500 text-white font-medium">
                                <span x-show="!processing">Reactivate</span>
                                <span x-show="processing">Working…</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <script>
                function voucherAdmin() {
                    return {
                        open: false,
                        processing: false,
                        error: '',
                        note: '',
                        voucher: { id: null, code: '', status: '', balance: 0 },
                        deactivateTpl: '{{ route('vouchers.deactivate', ['voucher' => '__ID__']) }}',
                        reactivateTpl: '{{ route('vouchers.reactivate', ['voucher' => '__ID__']) }}',

                        openEdit(id, code, status, balance) {
                            this.voucher = { id, code, status, balance };
                            this.note = '';
                            this.error = '';
                            this.processing = false;
                            this.open = true;
                        },

                        close() {
                            if (this.processing) return;
                            this.open = false;
                        },

                        async submit(action) {
                            if (this.processing) return;
                            this.processing = true;
                            this.error = '';
                            const tpl = action === 'deactivate' ? this.deactivateTpl : this.reactivateTpl;
                            const url = tpl.replace('__ID__', this.voucher.id);
                            try {
                                const res = await fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({ note: this.note }),
                                });
                                const data = await res.json();
                                if (data.success) {
                                    window.location.reload();
                                } else {
                                    this.error = data.message || 'Action failed.';
                                    this.processing = false;
                                }
                            } catch (err) {
                                this.error = 'Action failed: ' + err.message;
                                this.processing = false;
                            }
                        },
                    };
                }
            </script>
        @else
            <script>
                // Non-admins never see the Edit button; provide a no-op so x-data resolves.
                function voucherAdmin() { return {}; }
            </script>
        @endif
    </div>
</x-admin-layout>
