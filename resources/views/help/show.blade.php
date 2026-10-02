{{-- Procedures (SOPs) read from BookStack. The HTML is sanitised by App\Services\BookStack\SopHtml. --}}
<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $state === 'page' ? $page['title'] : 'How to' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <a href="{{ $back }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back
            </a>

            <x-alert type="success" :message="session('success')" />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($state === 'page')
                        <div class="sop-body">{!! $page['html'] !!}</div>
                        <div class="mt-6 flex items-center justify-between gap-4 border-t border-gray-200 pt-4">
                            <p class="text-sm text-gray-500">
                                @if ($page['updated_at'])
                                    Updated {{ \Illuminate\Support\Carbon::parse($page['updated_at'])->format('j M Y') }}
                                @endif
                            </p>
                            @if ($canManage && $page['url'])
                                <a href="{{ $page['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Edit in BookStack</a>
                            @endif
                        </div>
                    @elseif ($state === 'list')
                        <ul class="divide-y divide-gray-200">
                            @foreach ($pages as $item)
                                <li>
                                    <a href="{{ route('help.page', ['id' => $item['id'], 'back' => request()->getRequestUri()]) }}" class="flex items-center justify-between py-3 text-gray-900 hover:text-indigo-600">
                                        <span class="font-medium">{{ $item['title'] }}</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($state === 'empty')
                        <p class="text-gray-700">There is no written procedure for this screen yet.</p>
                        @if ($canManage)
                            <p class="mt-4 text-sm text-gray-600">
                                To add one, open the page in BookStack and add a tag named
                                <code class="px-1 py-0.5 bg-gray-100 rounded">{{ config('services.bookstack.screen_tag') }}</code>
                                with the value
                                <code class="px-1 py-0.5 bg-gray-100 rounded">{{ $screen }}</code>
                            </p>
                            <div class="mt-4 flex items-center gap-3">
                                <form method="POST" action="{{ route('help.refresh') }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md text-sm font-medium text-white hover:bg-gray-700">Refresh</button>
                                </form>
                                <a href="{{ $bookstackUrl }}" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Open BookStack</a>
                            </div>
                        @endif
                    @else
                        <p class="rounded-md bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800">This procedure could not be loaded just now. Try again in a minute.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
