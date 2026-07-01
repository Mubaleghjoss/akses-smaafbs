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

        <header @class([
            'relative z-30 isolate border-b',
            'border-white/70 bg-white/70 backdrop-blur' => ! $skipDecorativeChrome,
            'border-slate-200 bg-white' => $skipDecorativeChrome,
        ])>
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-5 md:flex-row md:items-center md:justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    @if(filled($logoPath))
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
                            <img src="{{ $logoPath }}" alt="Logo {{ $siteName }}" class="h-full w-full object-contain">
                        </span>
                    @else
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-base font-semibold text-white">{{ $brandInitials }}</span>
                    @endif
                    <div>
                        <div class="text-lg font-semibold">{{ $siteName }}</div>
                        <div class="text-xs text-slate-500">{{ $siteSettings['topbar_badge'] }}</div>
                        <div class="text-xs text-slate-500">{{ $siteSettings['topbar_text'] }}</div>
                    </div>
                </a>

                <div class="relative z-40 flex flex-wrap items-center justify-end gap-2 md:hidden">
                    <a class="btn btn-secondary" href="{{ route('library.literacy.index') }}">Program Literasi</a>
                    <a class="btn btn-primary" href="/admin/login">Login</a>
                    <div data-pwa-install-root class="hidden">
                        <button type="button" data-pwa-install-trigger class="btn btn-secondary">Install App</button>
                    </div>
                </div>

                <nav class="hidden flex-wrap items-center justify-end gap-2 text-sm md:flex md:max-w-[60%]">
                    <a class="btn btn-secondary" href="{{ route('library.literacy.index') }}">Program Literasi</a>
                    <a class="btn btn-primary" href="/admin/login">Login</a>
                    <div data-pwa-install-root class="hidden">
                        <button type="button" data-pwa-install-trigger class="btn btn-secondary">Install App</button>
                    </div>
                </nav>
            </div>
        </header>

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
