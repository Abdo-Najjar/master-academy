<div class="pa-root" style="--pa-a1:#64748b;--pa-a2:#334155;">
    @include('livewire.partials.portal-auth-style')

    <span class="pa-blob pa-blob--1"></span>
    <span class="pa-blob pa-blob--2"></span>

    <div class="pa-card">
        <img src="{{ \App\Support\AppBranding::logoUrl('light') }}" alt="{{ __('Logo') }}" class="pa-logo" data-theme-asset onerror="this.style.display='none'">

        <div class="pa-head">
            <h1 class="pa-title">{{ __('Student Login') }}</h1>
            <p class="pa-sub">{{ __('Sign in to your student account') }}</p>
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
