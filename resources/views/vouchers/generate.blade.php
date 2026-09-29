<x-admin-layout>
    <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Generate Vouchers</h2>
            <a href="{{ route('vouchers.list') }}" class="text-blue-400 hover:text-blue-300 text-sm">All vouchers</a>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded bg-red-700 text-white px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('vouchers.generate.store') }}" class="bg-gray-800 p-6 rounded">
            @csrf
            <label class="block text-sm text-gray-300 mb-2">How many vouchers to generate?</label>
            <input type="number" name="count" value="{{ old('count', 10) }}" min="1" max="200"
                   class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100 text-lg mb-4">
            <label class="block text-sm text-gray-300 mb-2" for="voucher-amount">Value of each voucher (€)</label>
            <input type="number" id="voucher-amount" name="amount" value="{{ old('amount') }}"
                   min="0.01" max="{{ config('vouchers.max_face_value') }}" step="0.01" required inputmode="decimal"
                   placeholder="20.00"
                   class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100 text-lg mb-2">
            <p class="text-xs text-gray-500 mb-4">
                Each voucher gets a unique random barcode and starts unsold, carrying this value.
                Selling it at the till (scan the label as an item; the till charges the value) activates it with this value.
                Labels look the same whatever their value, so keep batches of different values apart.
            </p>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded">
                Generate &amp; Print
            </button>
        </form>
    </div>
</x-admin-layout>
