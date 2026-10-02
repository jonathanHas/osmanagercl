{{-- "How to do this": the BookStack procedure for the current screen. Renders
     nothing when BookStack is not configured or the screen has no procedure
     (managers see it on every screen, to find the screen's tag value). --}}
@php($help = app(\App\Services\BookStack\ScreenHelp::class)->current())
@if ($help)
    <a class="shop-iconbtn" href="{{ route('help.show', ['screen' => $help['screen'], 'back' => request()->getRequestUri()]) }}" aria-label="How to do this" title="How to do this"><x-shop.icon name="help" /></a>
@endif
