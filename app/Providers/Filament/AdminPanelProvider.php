<?php

namespace App\Providers\Filament;

use App\Support\AppBranding;
use Filament\Http\Middleware\Authenticate;
use Hexters\HexaLite\HexaLite;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Admin\Pages\EditProfile;
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
            ->brandLogo(fn (): string => AppBranding::logoUrl())
            ->brandLogoHeight('4rem')
            ->favicon(fn (): string => AppBranding::logoUrl())
            ->colors(fn (): array => AppBranding::panelColors())
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->navigationGroups([
                NavigationGroup::make()->label(__('Education'))->collapsed(false),
                NavigationGroup::make()->label(__('Finance'))->collapsed(false),
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
            ->plugins([
                HexaLite::make(),
            ])
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
            );
    }
}
