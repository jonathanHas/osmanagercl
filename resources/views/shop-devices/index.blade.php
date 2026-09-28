<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Shop Devices') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-alert type="success" :message="session('success')" />
            <x-alert type="error" :message="session('error')" />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-6">
                        A device is trusted from the Shop menu on the device itself, by a manager signing in
                        with their password. Revoking it here stops PIN sign-in on that device from its next
                        request &mdash; anyone currently signed in on it stays signed in until the screen locks.
                    </p>

                    @if($devices->isEmpty())
                        <p class="text-sm text-gray-500 italic">No devices have been trusted yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trusted by</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last user</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last used</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($devices as $device)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $device->name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $device->registeredBy?->name ?? '—' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $device->lastUser?->name ?? '—' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $device->last_used_at?->format('j M Y H:i') ?? 'Never' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($device->isActive())
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                                          title="Revoked {{ $device->revoked_at?->format('j M Y H:i') }}">
                                                        Revoked {{ $device->revoked_at?->format('j M Y') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                @if($device->isActive())
                                                    {{-- No confirm dialog: revoking is cheap to undo by trusting the device again. --}}
                                                    <form method="POST" action="{{ route('shop-devices.revoke', $device) }}">
                                                        @csrf
                                                        <button type="submit" class="text-red-600 hover:text-red-900">Revoke</button>
                                                    </form>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold text-lg text-gray-800 mb-4">Recent switch activity</h3>

                    @if($logs->isEmpty())
                        <p class="text-sm text-gray-500 italic">Nothing recorded yet.</p>
                    @else
                        @php
                            $eventLabels = [
                                'trust' => ['Trusted', 'bg-blue-100 text-blue-800'],
                                'switch' => ['Switched', 'bg-green-100 text-green-800'],
                                'switch_failed' => ['Wrong PIN', 'bg-yellow-100 text-yellow-800'],
                                'switch_blocked' => ['Locked out', 'bg-red-100 text-red-800'],
                                'lock' => ['Locked', 'bg-gray-100 text-gray-800'],
                                'revoke' => ['Revoked', 'bg-red-100 text-red-800'],
                            ];
                        @endphp
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">When</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Person</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($logs as $log)
                                        @php([$label, $tone] = $eventLabels[$log->event] ?? [$log->event, 'bg-gray-100 text-gray-800'])
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->created_at?->format('j M Y H:i') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tone }}">{{ $label }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->user?->name ?? '—' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->device?->name ?? '—' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->ip ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
