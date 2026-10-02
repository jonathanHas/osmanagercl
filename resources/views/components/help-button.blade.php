{{-- Office sidebar row: the BookStack procedure for the current screen. Renders
     nothing when BookStack is not configured or the screen has no procedure
     (managers see it on every screen, to find the screen's tag value). --}}
@php($help = app(\App\Services\BookStack\ScreenHelp::class)->current())
@if ($help)
    <div class="flex-shrink-0 border-t border-gray-800 px-2 py-2">
        <a href="{{ route('help.show', ['screen' => $help['screen'], 'back' => request()->getRequestUri()]) }}"
           class="group flex w-full items-center px-2 py-2 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-700 hover:text-white">
            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            How to: this page
        </a>
    </div>
@endif
