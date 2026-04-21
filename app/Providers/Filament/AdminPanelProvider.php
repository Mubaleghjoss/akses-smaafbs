<?php

namespace App\Providers\Filament;

use App\Contracts\SiteSettingsAccessor;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EditAccountProfile;
use App\Filament\Pages\ManagePasskeys;
use App\Http\Middleware\EnsureGuruChangedDefaultPassword;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Admin\AdminNavigationSupport;
use App\Support\Admin\AdminSchoolNavigation;
use App\Support\Security\EndpointProtectionPolicy;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
        $shouldSkipExpensiveMenuSections = EndpointProtectionPolicy::shouldSkipExpensiveAdminMenuSections();
        $shouldSkipExpensiveDashboardWidgets = EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets();

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn (): string => app(SiteSettingsAccessor::class)->siteName())
            ->brandLogo(fn (): ?string => app(SiteSettingsAccessor::class)->logoPath())
            ->darkModeBrandLogo(fn (): ?string => app(SiteSettingsAccessor::class)->logoPath())
            ->brandLogoHeight('2.5rem')
            ->login(Login::class)
            ->profile(EditAccountProfile::class, false)
            ->colors([
                'primary' => Color::Slate,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    /** @var SiteSettingsAccessor $settings */
                    $settings = app(SiteSettingsAccessor::class);
                    $siteSettings = $settings->all();
                    $faviconUrl = $siteSettings['favicon_path']
                        ?? $siteSettings['logo_path']
                        ?? asset('favicon.ico');
                    $appleTouchIcon = $siteSettings['favicon_path']
                        ?? $siteSettings['logo_path']
                        ?? null;
                    $themeColor = (string) ($siteSettings['theme_color'] ?? '#16a34a');
                    $authCssPath = public_path('css/filament-admin-auth.css');
                    $authCssVersion = is_file($authCssPath) ? (string) filemtime($authCssPath) : '1';

                    $headSnippets = [
                        '<link rel="stylesheet" href="'.asset('css/filament-admin-responsive.css').'" data-navigate-track="reload">',
                        '<link rel="stylesheet" href="'.asset('css/filament-admin-auth.css').'?v='.e($authCssVersion).'" data-navigate-track="reload">',
                        '<script src="'.asset('js/filament-admin-fallback.js').'" data-navigate-once></script>',
                        '<link rel="manifest" href="'.e(url('/manifest.webmanifest')).'">',
                        '<meta name="theme-color" content="'.e($themeColor).'">',
                        '<link rel="icon" href="'.e((string) $faviconUrl).'">',
                        '<link rel="shortcut icon" href="'.e((string) $faviconUrl).'">',
                        '<style>:root{--admin-login-accent: '.e($themeColor).';--admin-login-primary:#0f172a;--admin-login-primary-soft:#1e293b;--admin-login-surface:#ffffff;--admin-login-surface-muted:#f8fafc;--admin-login-border:rgba(148,163,184,0.22);--admin-login-ink:#0f172a;--admin-login-muted:#475569;--admin-login-warm:rgba(251,191,36,0.22);--admin-login-cool:rgba(125,211,252,0.24);}</style>',
                    ];

                    if (filled($appleTouchIcon)) {
                        $headSnippets[] = '<link rel="apple-touch-icon" href="'.e((string) $appleTouchIcon).'">';
                    }

                    return implode('', $headSnippets);
                }
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.components.admin-access-denied-popup')->render()
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.components.force-guru-password-change-modal')->render()
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => ''
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => ''
            );

        $userMenuItems = [
            MenuItem::make()
                ->label('Kelola Passkey')
                ->icon('heroicon-o-key')
                ->url(fn (): string => ManagePasskeys::getUrl())
                ->sort(5),
        ];

        if (! $shouldSkipExpensiveMenuSections) {
            $userMenuItems[] = MenuItem::make()
                ->label('Bahasa: Indonesia')
                ->url('/admin/locale/id')
                ->sort(10);
            $userMenuItems[] = MenuItem::make()
                ->label('Language: English')
                ->url('/admin/locale/en')
                ->sort(20);
        }

        $panel = $panel->userMenuItems($userMenuItems);

        $panel = $panel
            ->navigation(function () use ($panel): NavigationBuilder {
                $builder = new NavigationBuilder;
                $user = auth()->user();

                if ($user instanceof User && ! $user->hasRole('admin')) {
                    $user->loadMissing('roles');
                }

                $allowedGroups = $user instanceof User
                    ? $user->resolvedNavigationGroups()
                    : array_keys(User::navigationGroupOptions());
                $allowedItems = $user instanceof User
                    ? AdminNavigationSupport::allowedNavigationItemClasses($user)
                    : collect();

                $pageClasses = collect($panel->getPages())
                    ->filter(fn (string $page): bool => blank($page::getCluster()) && $page::shouldRegisterNavigation())
                    ->filter(fn (string $page): bool => $allowedItems->isEmpty() || $allowedItems->contains($page))
                    ->filter(fn (string $page): bool => $page::canAccess());

                $resourceClasses = collect($panel->getResources())
                    ->filter(fn (string $resource): bool => blank($resource::getCluster()) && $resource::shouldRegisterNavigation())
                    ->filter(fn (string $resource): bool => $allowedItems->isEmpty() || $allowedItems->contains($resource))
                    ->filter(fn (string $resource): bool => $resource::canAccess());

                $pageItems = $pageClasses
                    ->flatMap(fn (string $page): array => AdminSchoolNavigation::decorateNavigationItems($page, $page::getNavigationItems()));

                $resourceItems = $resourceClasses
                    ->flatMap(fn (string $resource): array => AdminSchoolNavigation::decorateNavigationItems($resource, $resource::getNavigationItems()));

                $schoolParentItems = AdminSchoolNavigation::parentNavigationItems(
                    $pageClasses->concat($resourceClasses)->all(),
                );

                $groupedItems = $pageItems
                    ->concat($resourceItems)
                    ->concat($schoolParentItems)
                    ->filter(fn (NavigationItem $item): bool => $item->isVisible())
                    ->sortBy(fn (NavigationItem $item): int => (int) $item->getSort())
                    ->groupBy(fn (NavigationItem $item): string => User::normalizeNavigationGroupKey($item->getGroup()));

                foreach (User::navigationGroupOptions() as $groupKey => $groupLabel) {
                    if (! in_array($groupKey, $allowedGroups, true)) {
                        continue;
                    }

                    $items = $groupedItems->get($groupKey);

                    if (blank($items) || $items->isEmpty()) {
                        continue;
                    }

                     $parentItems = $items->groupBy(fn (NavigationItem $item): string => $item->getParentItem() ?? '');

                     $items = $parentItems->get('', collect())
                        ->keyBy(fn (NavigationItem $item): string => $item->getLabel());

                    $parentItems->except([''])->each(function ($childItems, string $parentItemLabel) use ($items): void {
                        if (! $items->has($parentItemLabel)) {
                            return;
                        }

                        $items->get($parentItemLabel)->childItems(
                            $childItems
                                ->sortBy(fn (NavigationItem $item): int => (int) $item->getSort())
                                ->values()
                                ->all()
                        );
                    });

                    $items = $items
                        ->filter(fn (NavigationItem $item): bool => filled($item->getChildItems()) || filled($item->getUrl()))
                        ->sortBy(fn (NavigationItem $item): int => (int) $item->getSort())
                        ->values();

                    if ($groupKey === 'Dashboard') {
                        $builder->group(NavigationGroup::make()->items($items->all()));

                        continue;
                    }

                    $builder->group(NavigationGroup::make($groupLabel)->items($items->all()));
                }

                return $builder;
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                ...(! $shouldSkipExpensiveDashboardWidgets ? [Widgets\FilamentInfoWidget::class] : []),
            ]);

        if (! $shouldSkipExpensiveDashboardWidgets) {
            $panel = $panel->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets');
        }

        return $panel
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureGuruChangedDefaultPassword::class,
            ]);
    }
}


