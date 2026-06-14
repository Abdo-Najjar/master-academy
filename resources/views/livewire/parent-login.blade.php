<div class="pa-root" style="--pa-a1:#f59e0b;--pa-a2:#d97706;">
    @include('livewire.partials.portal-auth-style')

    <span class="pa-blob pa-blob--1"></span>
    <span class="pa-blob pa-blob--2"></span>

    <div class="pa-card">
        <img src="{{ \App\Support\AppBranding::logoUrl() }}" alt="" class="pa-logo" onerror="this.style.display='none'">

        <div class="pa-icon">
            <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
            </svg>
        </div>

        <div class="pa-head">
            <h1 class="pa-title">{{ __('Parent Login') }}</h1>
            <p class="pa-sub">{{ __('Sign in to your parent account') }}</p>
        </div>

        <form wire:submit="login" class="pa-form">
            <div>
                <label for="phone" class="pa-label">{{ __('Phone Number') }}</label>
                <input wire:model="phone" type="text" id="phone" autocomplete="username" inputmode="tel" class="pa-input" dir="ltr" style="text-align: end;">
                @error('phone') <p class="pa-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="pa-label">{{ __('Password') }}</label>
                <input wire:model="password" type="password" id="password" autocomplete="current-password" class="pa-input">
                @error('password') <p class="pa-error">{{ $message }}</p> @enderror
            </div>
            <label class="pa-remember">
                <input wire:model="remember" type="checkbox">
                {{ __('Remember me') }}
            </label>
            <button type="submit" class="pa-btn" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">{{ __('Login') }}</span>
                <span wire:loading wire:target="login">{{ __('Signing in...') }}</span>
            </button>
        </form>

        <a href="{{ route('portal') }}" wire:navigate class="pa-back">← {{ __('Back to Portal') }}</a>
    </div>

    <div class="pa-footer">© {{ now()->year }} {{ __('Manba Al-Tamayoz Center') }}</div>
</div>
