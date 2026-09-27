<x-shop-layout title="Enter PIN" :back="route('shop.switch')" guest-safe>
    <main class="shop-page shop-page--center"
          x-data="shopPinPad()"
          data-length="{{ $person->pin_length }}"
          data-error="{{ session('pinError') ?? '' }}"
          x-on:keydown.window="onKey($event)">
        <div class="shop-pin" :class="{ 'is-error': message }">
            <div class="shop-pin__who">
                <span class="shop-avatar shop-avatar--xl shop-avatar--accent">{{ $initials }}</span>
                <h2 class="shop-title">Hi {{ $firstName }}</h2>
                <p class="shop-meta">Enter your {{ $person->pin_length }}-digit PIN</p>
            </div>

            <div class="shop-pin__dots" :aria-label="digits.length + ' digits entered'">
                @for ($i = 0; $i < $person->pin_length; $i++)
                    <span class="shop-pin__dot" :class="{ 'is-filled': digits.length > {{ $i }} }"></span>
                @endfor
            </div>

            <p class="shop-pin__msg" x-text="message"></p>

            <div class="shop-pin__pad">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $key)
                    <button class="shop-pin__key" type="button" x-on:click="press('{{ $key }}')">{{ $key }}</button>
                @endforeach
                <a class="shop-pin__key shop-pin__key--fn" href="{{ route('shop.switch') }}">Not you?</a>
                <button class="shop-pin__key" type="button" x-on:click="press('0')">0</button>
                <button class="shop-pin__key shop-pin__key--fn" type="button" aria-label="Delete" x-on:click="backspace()"><x-shop.icon name="backspace" size="lg" /></button>
            </div>
        </div>

        <form method="POST" action="{{ route('shop.switch.authenticate', $person) }}" x-ref="form" hidden>
            @csrf
            <input type="hidden" name="pin" x-ref="pin">
        </form>
    </main>
</x-shop-layout>
