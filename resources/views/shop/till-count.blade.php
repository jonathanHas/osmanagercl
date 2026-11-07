<x-shop-layout title="Till count" :back="route('shop.home')" :subtitle="$till?->name">
    <main class="shop-page" x-data="shopTillCount()"
          data-state="{{ json_encode($clientState) }}"
          data-identify-url="{{ route('till-count.identify') }}"
          data-forget-url="{{ route('till-count.forget') }}"
          data-store-url="{{ route('till-count.store') }}"
          x-on:keydown.window="onKey($event)">
        @include('shop.partials.till-count', ['navRoute' => 'shop.till-count', 'doneUrl' => route('shop.home')])
    </main>
</x-shop-layout>
