<?php

namespace App\Providers\Filament;

use App\Support\AppBranding;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Admin\Pages\EditProfile;
use Filament\Enums\ThemeMode;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => AppBranding::appName())
            ->brandLogo(fn (): string => AppBranding::logoUrl('light'))
            ->darkModeBrandLogo(fn (): string => AppBranding::logoUrl('dark'))
            ->brandLogoHeight('4rem')
            ->favicon(fn (): string => AppBranding::faviconUrl('dark'))
            ->colors(fn (): array => AppBranding::panelColors())
            ->defaultThemeMode(ThemeMode::Dark)
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->navigationGroups([
                NavigationGroup::make()->label(__('Education'))->collapsed(false),
                NavigationGroup::make()->label(__('Operations'))->collapsed(false),
                NavigationGroup::make()->label(__('Reports'))->collapsed(false),
                NavigationGroup::make()->label(__('Finance'))->collapsed(true),
                NavigationGroup::make()->label(__('Communication'))->collapsed(true),
                NavigationGroup::make()->label(__('Administration'))->collapsed(true),
                NavigationGroup::make()->label(__('Locations'))->collapsed(true),
                NavigationGroup::make()->label(__('Settings'))->collapsed(true),
            ])
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn (): string => __('My Profile'))
                    ->url(fn (): string => EditProfile::getUrl())
                    ->icon(Heroicon::OutlinedUserCircle),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->authGuard('web')
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render(<<<'BLADE'
                    <div style="margin-top:1rem; display:flex; justify-content:center;">
                        <a href="{{ route('portal') }}"
                           class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                            ← {{ __('Back to Portal') }}
                        </a>
                    </div>
                BLADE)
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): string => Blade::render(<<<'BLADE'
                    @if (filament()->hasDarkMode() && ! filament()->hasDarkModeForced())
                        <button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('theme-changed', { detail: document.documentElement.classList.contains('dark') ? 'light' : 'dark' }))"
                            aria-label="{{ __('Toggle dark mode') }}"
                            class="fixed bottom-4 end-4 z-50 flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-gray-200 transition hover:scale-105 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700"
                        >
                            <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                            </svg>
                            <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                            </svg>
                        </button>
                    @endif
                BLADE)
            );
    }
}
