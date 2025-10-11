<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->spa()
            ->databaseTransactions()
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Cytrack')
            ->brandLogo(asset('images/logo/logo-full.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon/favicon.ico'))
            ->login(Login::class)
            ->registration()
            ->colors([
                'primary' => [
                    50 => '249, 250, 251',   // gray-50
                    100 => '243, 244, 246',  // gray-100
                    200 => '229, 231, 235',  // gray-200
                    300 => '209, 213, 219',  // gray-300
                    400 => '156, 163, 175',  // gray-400
                    500 => '107, 114, 128',  // gray-500 (base)
                    600 => '75, 85, 99',     // gray-600
                    700 => '55, 65, 81',     // gray-700
                    800 => '31, 41, 55',     // gray-800
                    900 => '17, 24, 39',     // gray-900
                    950 => '3, 7, 18',       // gray-950
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->passwordReset()
            ->emailVerification()
            ->profile();
    }
}
