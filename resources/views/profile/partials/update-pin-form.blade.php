@php($pinUser = auth()->user())
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Shop PIN') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('4–6 digits for signing in on the shop tablet. It only works on a device a manager has trusted, and a PIN session can only reach Shop mode.') }}
        </p>

        <p class="mt-1 text-sm {{ $pinUser->hasPin() ? 'text-green-700' : 'text-gray-600' }}">
            @if ($pinUser->hasPin())
                {{ __('PIN set') }} {{ $pinUser->pin_set_at?->format('j M Y') }}.
            @else
                {{ __('No PIN set.') }}
            @endif
        </p>
    </header>

    <form method="post" action="{{ route('profile.pin.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-form-group
                name="current_password"
                label="Current Password"
                type="password"
                id="update_pin_current_password"
                autocomplete="current-password"
                containerClass="" />
            @if($errors->updatePin->has('current_password'))
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->updatePin->first('current_password') }}</p>
            @endif
        </div>

        <div>
            <x-form-group
                name="pin"
                label="New PIN"
                type="password"
                id="update_pin_pin"
                autocomplete="off"
                containerClass="" />
            @if($errors->updatePin->has('pin'))
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->updatePin->first('pin') }}</p>
            @endif
        </div>

        <div>
            <x-form-group
                name="pin_confirmation"
                label="Confirm New PIN"
                type="password"
                id="update_pin_pin_confirmation"
                autocomplete="off"
                containerClass="" />
            @if($errors->updatePin->has('pin_confirmation'))
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->updatePin->first('pin_confirmation') }}</p>
            @endif
        </div>

        @if ($pinUser->hasPin())
            <label class="flex items-center">
                <input type="checkbox" name="clear_pin" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="ms-2 text-sm text-gray-700">{{ __('Remove my PIN (I will sign in with my password)') }}</span>
            </label>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (in_array(session('status'), ['pin-updated', 'pin-cleared'], true))
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ session('status') === 'pin-cleared' ? __('PIN removed.') : __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
