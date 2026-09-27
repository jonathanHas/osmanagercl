<x-shop-layout title="Trust this device" :back="route('shop.home')">
    <main class="shop-page shop-page--narrow">
        <h2 class="shop-title">Trust this device</h2>

        @if ($device)
            <section class="shop-card">
                <p class="shop-meta">This device is already trusted as <strong>{{ $device->name }}</strong>. Trusting it again replaces the old token, and the old one stops working.</p>
            </section>
        @endif

        <section class="shop-card">
            <p class="shop-meta">Staff will be able to sign in on this device with their PIN instead of a password. Only do this on a device that stays in the shop.</p>
        </section>

        <form method="POST" action="{{ route('shop.devices.trust.store') }}" class="shop-stack">
            @csrf
            <div class="shop-field">
                <label class="shop-field__label" for="device-name">What is this device called?</label>
                <input class="shop-input" id="device-name" name="name" type="text" value="{{ old('name', $device->name ?? 'Counter tablet') }}" maxlength="60" required>
                @error('name')
                    <p class="shop-meta">{{ $message }}</p>
                @enderror
            </div>
            <div class="shop-inline">
                <a class="shop-btn shop-btn--secondary" href="{{ route('shop.home') }}">Cancel</a>
                <button class="shop-btn shop-btn--primary" type="submit"><x-shop.icon name="lock" />Trust this device</button>
            </div>
        </form>
    </main>
</x-shop-layout>
