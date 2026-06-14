<div class="pa-root" style="--pa-a1:#10b981;--pa-a2:#047857;">
    @include('livewire.partials.portal-auth-style')

    <span class="pa-blob pa-blob--1"></span>
    <span class="pa-blob pa-blob--2"></span>

    <div class="pa-card">
        <img src="{{ \App\Support\AppBranding::logoUrl() }}" alt="" class="pa-logo" onerror="this.style.display='none'">

        <div class="pa-icon">
            <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
            </svg>
        </div>

        <div class="pa-head">
            <h1 class="pa-title">{{ __('Trainer Login') }}</h1>
            <p class="pa-sub">{{ __('Sign in to your trainer account') }}</p>
        </div>

        <form wire:submit="login" class="pa-form">
            <div>
                <label for="username" class="pa-label">{{ __('Username') }}</label>
                <input wire:model="username" type="text" id="username" autocomplete="username" class="pa-input">
                @error('username') <p class="pa-error">{{ $message }}</p> @enderror
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
