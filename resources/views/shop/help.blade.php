{{-- Procedures (SOPs) read from BookStack. The HTML is sanitised by App\Services\BookStack\SopHtml. --}}
<x-shop-layout :title="$state === 'page' ? $page['title'] : 'How to'" :back="$back">
    <main class="shop-page shop-page--narrow">
        @if ($state === 'page')
            <article class="shop-sop">{!! $page['html'] !!}</article>
            @if ($page['updated_at'])
                <p class="shop-meta">Updated {{ \Illuminate\Support\Carbon::parse($page['updated_at'])->format('j M Y') }}</p>
            @endif
            @if ($canManage && $page['url'])
                <div class="shop-inline">
                    <a class="shop-btn shop-btn--secondary" href="{{ $page['url'] }}" target="_blank" rel="noopener">Edit in BookStack</a>
                </div>
            @endif
        @elseif ($state === 'list')
            <div class="shop-list">
                @foreach ($pages as $item)
                    <a class="shop-row" href="{{ route('help.page', ['id' => $item['id'], 'back' => request()->getRequestUri()]) }}">
                        <span class="shop-row__main"><span class="shop-row__title">{{ $item['title'] }}</span></span>
                        <x-shop.icon name="chevron-right" class="shop-row__chev" />
                    </a>
                @endforeach
            </div>
        @elseif ($state === 'empty')
            <section class="shop-empty">
                <div class="shop-empty__icon"><x-shop.icon name="help" size="xl" /></div>
                <p class="shop-empty__title">There is no written procedure for this screen yet.</p>
                @if ($canManage)
                    <p class="shop-empty__text">To add one, open the page in BookStack and add a tag named <span class="shop-code">{{ config('services.bookstack.screen_tag') }}</span> with the value <span class="shop-code">{{ $screen }}</span></p>
                    <form method="POST" action="{{ route('help.refresh') }}">
                        @csrf
                        <button class="shop-btn shop-btn--primary" type="submit">Refresh</button>
                    </form>
                    <a class="shop-btn shop-btn--secondary" href="{{ $bookstackUrl }}" target="_blank" rel="noopener">Open BookStack</a>
                @endif
            </section>
        @else
            <p class="shop-notice"><x-shop.icon name="alert" />This procedure could not be loaded just now. Try again in a minute.</p>
        @endif
    </main>
</x-shop-layout>
