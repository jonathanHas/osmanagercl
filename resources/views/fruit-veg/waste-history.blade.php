<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Waste History') }}
            </h2>
            <a href="{{ route('fruit-veg.waste') }}"
               class="px-4 py-2 bg-[#c2410c] text-white rounded-md hover:bg-[#9a3412] transition text-sm">
                Log new waste
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @forelse ($dates as $dateRow)
                @php
                    $dateKey = \Carbon\Carbon::parse($dateRow->waste_date)->toDateString();
                    $lines = $rowsByDate[$dateKey] ?? collect();
                    $unitTotals = $lines->groupBy('unit')->map(fn ($g) => $g->sum('quantity'));
                @endphp
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ \Carbon\Carbon::parse($dateKey)->format('D j M Y') }}
                            </h3>
                            <p class="text-sm text-gray-500">
                                {{ $dateRow->line_count }} {{ \Illuminate\Support\Str::plural('item', $dateRow->line_count) }}
                                @if ($unitTotals->isNotEmpty())
                                    &middot;
                                    {{ $unitTotals->map(fn ($qty, $unit) => rtrim(rtrim(number_format($qty, 2), '0'), '.').' '.$unit)->implode(', ') }}
                                @endif
                                @if ($dateRow->total_value > 0)
                                    &middot;
                                    <span class="font-medium text-[#c2410c]">€{{ number_format($dateRow->total_value, 2) }} est. value lost</span>
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('fruit-veg.waste', ['date' => $dateKey]) }}"
                           class="text-sm text-indigo-600 hover:text-indigo-800">Edit this day &rarr;</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Est. value</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($lines as $line)
                                    <tr>
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{!! $line->product_name !!}</td>
                                        <td class="px-6 py-3 text-sm text-gray-700">
                                            {{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}
                                            <span class="text-gray-400">{{ $line->unit }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm">
                                            @if ($line->value !== null)
                                                <span class="font-medium text-[#c2410c]">€{{ number_format($line->value, 2) }}</span>
                                            @else
                                                <span class="text-gray-300">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <form method="POST" action="{{ route('fruit-veg.waste.destroy', $line) }}"
                                                  onsubmit="return confirm('Remove this waste entry?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    No waste logged yet.
                    <a href="{{ route('fruit-veg.waste') }}" class="text-indigo-600 hover:text-indigo-800">Log your first entry &rarr;</a>
                </div>
            @endforelse

            <div class="mt-4">
                {{ $dates->links() }}
            </div>
        </div>
    </div>
</x-admin-layout>
