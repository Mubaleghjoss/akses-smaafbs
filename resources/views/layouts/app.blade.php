<!DOCTYPE html>
<html lang="id" data-pwa-shell="public">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $siteName = trim((string) ($siteSettings['site_name'] ?? config('app.name')));
        if ($siteName === '') {
            $siteName = config('app.name');
        }

        $logoPath = $siteSettings['logo_path'] ?? null;
        $brandInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $siteName), 0, 2));
        if ($brandInitials === '') {
            $brandInitials = 'AS';
        }
    @endphp

    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">
    <meta name="theme-color" content="{{ $meta['theme_color'] }}">
    @php
        $pwaRegistrarPath = public_path('js/pwa-registration.js');
        $pwaRegistrarVersion = is_file($pwaRegistrarPath)
            ? substr((string) hash_file('sha256', $pwaRegistrarPath), 0, 12)
            : '1';
    @endphp
    <script defer src="{{ asset('js/pwa-registration.js') }}?v={{ $pwaRegistrarVersion }}"></script>
    <link rel="canonical" href="{{ $meta['canonical_url'] }}">
    <link rel="manifest" href="{{ $meta['manifest_url'] }}">
    <link rel="icon" href="{{ $meta['favicon_url'] }}">
    <link rel="shortcut icon" href="{{ $meta['favicon_url'] }}">
    @if(filled($meta['apple_touch_icon']))
        <link rel="apple-touch-icon" href="{{ $meta['apple_touch_icon'] }}">
    @endif

    <meta property="og:type" content="{{ $meta['og_type'] }}">
    <meta property="og:site_name" content="{{ $meta['og_site_name'] }}">
    <meta property="og:title" content="{{ $meta['og_title'] }}">
    <meta property="og:description" content="{{ $meta['og_description'] }}">
    <meta property="og:url" content="{{ $meta['og_url'] }}">
    <meta property="og:image" content="{{ $meta['og_image'] }}">
    @if(filled($meta['og_image_secure_url']))
        <meta property="og:image:secure_url" content="{{ $meta['og_image_secure_url'] }}">
    @endif
    @if(filled($meta['og_image_type']))
        <meta property="og:image:type" content="{{ $meta['og_image_type'] }}">
    @endif
    @if(filled($meta['og_image_width']) && filled($meta['og_image_height']))
        <meta property="og:image:width" content="{{ $meta['og_image_width'] }}">
        <meta property="og:image:height" content="{{ $meta['og_image_height'] }}">
    @endif
    @if(filled($meta['og_image_alt']))
        <meta property="og:image:alt" content="{{ $meta['og_image_alt'] }}">
    @endif

    <meta name="twitter:card" content="{{ $meta['twitter_card'] }}">
    <meta name="twitter:title" content="{{ $meta['twitter_title'] }}">
    <meta name="twitter:description" content="{{ $meta['twitter_description'] }}">
    @if(filled($meta['twitter_image']))
        <meta name="twitter:image" content="{{ $meta['twitter_image'] }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    @php
        $skipDecorativeChrome = \App\Support\Security\EndpointProtectionPolicy::shouldSkipPublicDecorativeChrome();
    @endphp

    <div class="app-shell">
        <a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-slate-900 focus:px-4 focus:py-2 focus:text-white" href="#content">
            Lewati ke konten
        </a>
        @unless ($skipDecorativeChrome)
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute -top-24 right-0 h-80 w-80 rounded-full bg-amber-200/60 blur-3xl"></div>
                <div class="absolute bottom-0 -left-16 h-72 w-72 rounded-full bg-sky-200/60 blur-3xl"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.85),_transparent_60%)]"></div>
            </div>
        @endunless

        <header id="public-navigation" @class([
            'public-navigation sticky top-0 z-50 isolate border-b',
            'border-white/70 bg-white/70 backdrop-blur' => ! $skipDecorativeChrome,
            'border-slate-200 bg-white' => $skipDecorativeChrome,
        ])>
            <div class="mx-auto flex min-h-[4.5rem] w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 md:min-h-20 md:px-6">
                <a href="{{ route('home') }}" class="public-navigation-brand flex min-w-0 items-center gap-3" aria-label="Kembali ke beranda">
                    @if(filled($logoPath))
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm sm:h-12 sm:w-12 sm:rounded-2xl">
                            <img src="{{ $logoPath }}" alt="Logo {{ $siteName }}" width="48" height="48" decoding="async" class="h-full w-full object-contain">
                        </span>
                    @else
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white sm:h-11 sm:w-11 sm:rounded-2xl sm:text-base">{{ $brandInitials }}</span>
                    @endif
                    <div class="min-w-0">
                        <div class="truncate text-base font-semibold leading-tight sm:text-lg">{{ $siteName }}</div>
                        <div class="public-navigation-copy mt-1 truncate text-[11px] leading-tight text-slate-500 sm:max-w-[20rem] sm:text-xs">
                            {{ $siteSettings['topbar_badge'] }}<span class="mx-1" aria-hidden="true">&middot;</span>{{ $siteSettings['topbar_text'] }}
                        </div>
                    </div>
                </a>

                <nav class="hidden flex-wrap items-center justify-end gap-2 text-sm md:flex md:max-w-[65%]" aria-label="Navigasi utama">
                    <a class="btn btn-secondary" href="{{ route('home') }}">Beranda</a>
                    <a class="btn btn-secondary" href="{{ route('library.literacy.index') }}">Literasi Numerasi</a>
                    <a class="btn btn-secondary" href="{{ route('library.index') }}">Akses Perpus</a>
                    <a class="btn btn-primary" href="/admin/login">Login</a>
                    <div data-pwa-install-root class="hidden">
                        <button type="button" data-pwa-install-trigger class="btn btn-secondary">Install App</button>
                    </div>
                </nav>

                <button
                    id="public-mobile-menu-toggle"
                    type="button"
                    class="public-mobile-menu-toggle md:hidden"
                    aria-expanded="false"
                    aria-controls="public-mobile-menu"
                    aria-label="Buka menu navigasi"
                >
                    <svg class="public-mobile-menu-open-icon h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg class="public-mobile-menu-close-icon h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span>Menu</span>
                </button>
            </div>
        </header>

        <button
            id="public-mobile-menu-overlay"
            type="button"
            class="public-mobile-menu-overlay md:hidden"
            aria-label="Tutup menu navigasi"
            aria-hidden="true"
            tabindex="-1"
        ></button>

        <aside
            id="public-mobile-menu"
            class="public-mobile-menu-shell md:hidden"
            aria-hidden="true"
            aria-label="Navigasi mobile"
            tabindex="-1"
            inert
        >
            <div class="public-mobile-menu-header">
                <div class="flex min-w-0 items-center gap-3">
                    @if(filled($logoPath))
                        <img src="{{ $logoPath }}" alt="" width="44" height="44" decoding="async" class="h-11 w-11 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1">
                    @else
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white">{{ $brandInitials }}</span>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Menu Utama</p>
                        <h2 class="truncate text-base font-extrabold text-slate-900">{{ $siteName }}</h2>
                    </div>
                </div>
                <button id="public-mobile-menu-close" type="button" class="public-mobile-menu-close" aria-label="Tutup menu navigasi">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="public-mobile-menu-scroll">
                <nav class="public-mobile-menu-card" aria-label="Menu utama ponsel">
                    <a href="{{ route('home') }}" @class(['public-mobile-menu-link', 'is-active' => request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>
                        <span class="public-mobile-menu-icon"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-9Z" /></svg></span>
                        <span>Beranda</span>
                    </a>
                    <a href="{{ route('library.literacy.index') }}" @class(['public-mobile-menu-link', 'is-active' => request()->routeIs('library.literacy.*')]) @if(request()->routeIs('library.literacy.*')) aria-current="page" @endif>
                        <span class="public-mobile-menu-icon"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Zm16 0A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5v-16Z" /></svg></span>
                        <span>Literasi Numerasi</span>
                    </a>
                    <a href="{{ route('library.index') }}" @class(['public-mobile-menu-link', 'is-active' => request()->routeIs('library.index')]) @if(request()->routeIs('library.index')) aria-current="page" @endif>
                        <span class="public-mobile-menu-icon"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 4h12a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2V4Zm0 12h14M8 7h8" /></svg></span>
                        <span>Akses Perpus</span>
                    </a>
                    <a href="/admin/login" class="public-mobile-menu-link">
                        <span class="public-mobile-menu-icon"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8a3 3 0 1 0-6 0 3 3 0 0 0 6 0Zm4 13a7 7 0 0 0-14 0m12-8h4m-2-2v4" /></svg></span>
                        <span>Login Admin</span>
                    </a>
                    <div data-pwa-install-root class="hidden border-t border-slate-200 pt-2">
                        <button type="button" data-pwa-install-trigger class="public-mobile-menu-link w-full text-left">
                            <span class="public-mobile-menu-icon"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" /></svg></span>
                            <span>Install Aplikasi</span>
                        </button>
                    </div>
                </nav>
            </div>
        </aside>

        <main id="content" class="mx-auto w-full max-w-6xl px-4 py-10">
            @yield('content')
        </main>

        <footer class="border-t border-white/70 bg-white/70">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-500">
                (c) {{ date('Y') }} {{ $siteSettings['footer_primary_text'] }}. {{ $siteSettings['footer_secondary_text'] }}
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
