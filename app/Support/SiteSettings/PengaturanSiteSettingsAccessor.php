<?php

namespace App\Support\SiteSettings;

use App\Contracts\SiteSettingsAccessor;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PengaturanSiteSettingsAccessor implements SiteSettingsAccessor
{
    /**
     * @var array<string, string>|null
     */
    protected ?array $resolvedSettings = null;

    /**
     * @return array<string, string>
     */
    protected function defaults(): array
    {
        return [
            SiteSettingKeys::SITE_NAME => 'SMA AFBS',
            SiteSettingKeys::TOPBAR_BADGE => 'Sekolah Islam Berasrama',
            SiteSettingKeys::TOPBAR_TEXT => 'Portal resmi informasi SMA AFBS.',
            SiteSettingKeys::FOOTER_PRIMARY_TEXT => 'SMA AFBS',
            SiteSettingKeys::FOOTER_SECONDARY_TEXT => 'Pendidikan islam terpadu untuk generasi berakhlak dan berprestasi.',
            SiteSettingKeys::LOGO_PATH => '',
            SiteSettingKeys::FAVICON_PATH => '',
            SiteSettingKeys::DEFAULT_SEO_TITLE => 'SMA AFBS',
            SiteSettingKeys::DEFAULT_SEO_DESCRIPTION => 'Website resmi SMA AFBS.',
            SiteSettingKeys::DEFAULT_OG_TITLE => 'SMA AFBS',
            SiteSettingKeys::DEFAULT_OG_DESCRIPTION => 'Website resmi SMA AFBS.',
            SiteSettingKeys::DEFAULT_OG_IMAGE => '',
            SiteSettingKeys::THEME_COLOR => '#16a34a',
            SiteSettingKeys::PWA_APP_NAME => 'SMA AFBS',
            SiteSettingKeys::PWA_SHORT_NAME => 'AFBS',
        ];
    }

    public function all(): array
    {
        $settings = $this->settings();
        $logoPath = $this->resolveConfiguredAssetPath(
            $settings[SiteSettingKeys::LOGO_PATH] ?? null,
            'site-branding/logo'
        );
        $faviconPath = $this->resolveConfiguredAssetPath(
            $settings[SiteSettingKeys::FAVICON_PATH] ?? null,
            'site-branding/favicon'
        ) ?? $logoPath;
        $defaultOgImage = $this->resolveConfiguredAssetPath(
            $settings[SiteSettingKeys::DEFAULT_OG_IMAGE] ?? null
        ) ?? $logoPath ?? $faviconPath;

        return [
            'site_name' => $settings[SiteSettingKeys::SITE_NAME],
            'topbar_badge' => $settings[SiteSettingKeys::TOPBAR_BADGE],
            'topbar_text' => $settings[SiteSettingKeys::TOPBAR_TEXT],
            'footer_primary_text' => $settings[SiteSettingKeys::FOOTER_PRIMARY_TEXT],
            'footer_secondary_text' => $settings[SiteSettingKeys::FOOTER_SECONDARY_TEXT],
            'logo_path' => $logoPath,
            'favicon_path' => $faviconPath,
            'default_seo_title' => $settings[SiteSettingKeys::DEFAULT_SEO_TITLE],
            'default_seo_description' => $settings[SiteSettingKeys::DEFAULT_SEO_DESCRIPTION],
            'default_og_title' => $settings[SiteSettingKeys::DEFAULT_OG_TITLE],
            'default_og_description' => $settings[SiteSettingKeys::DEFAULT_OG_DESCRIPTION],
            'default_og_image' => $defaultOgImage,
            'theme_color' => $settings[SiteSettingKeys::THEME_COLOR],
            'pwa_app_name' => $settings[SiteSettingKeys::PWA_APP_NAME],
            'pwa_short_name' => $settings[SiteSettingKeys::PWA_SHORT_NAME],
        ];
    }

    public function siteName(): string
    {
        return $this->setting(SiteSettingKeys::SITE_NAME);
    }

    public function topbarBadge(): string
    {
        return $this->setting(SiteSettingKeys::TOPBAR_BADGE);
    }

    public function topbarText(): string
    {
        return $this->setting(SiteSettingKeys::TOPBAR_TEXT);
    }

    public function footerPrimaryText(): string
    {
        return $this->setting(SiteSettingKeys::FOOTER_PRIMARY_TEXT);
    }

    public function footerSecondaryText(): string
    {
        return $this->setting(SiteSettingKeys::FOOTER_SECONDARY_TEXT);
    }

    public function logoPath(): ?string
    {
        return $this->all()['logo_path'] ?? null;
    }

    public function faviconPath(): ?string
    {
        return $this->all()['favicon_path'] ?? null;
    }

    public function defaultSeoTitle(): string
    {
        return $this->setting(SiteSettingKeys::DEFAULT_SEO_TITLE);
    }

    public function defaultSeoDescription(): string
    {
        return $this->setting(SiteSettingKeys::DEFAULT_SEO_DESCRIPTION);
    }

    public function defaultOgTitle(): string
    {
        return $this->setting(SiteSettingKeys::DEFAULT_OG_TITLE);
    }

    public function defaultOgDescription(): string
    {
        return $this->setting(SiteSettingKeys::DEFAULT_OG_DESCRIPTION);
    }

    public function defaultOgImage(): ?string
    {
        return $this->all()['default_og_image'] ?? null;
    }

    public function themeColor(): string
    {
        return $this->setting(SiteSettingKeys::THEME_COLOR);
    }

    public function pwaAppName(): string
    {
        return $this->setting(SiteSettingKeys::PWA_APP_NAME);
    }

    public function pwaShortName(): string
    {
        return $this->setting(SiteSettingKeys::PWA_SHORT_NAME);
    }

    protected function setting(string $key): string
    {
        return (string) ($this->settings()[$key] ?? ($this->defaults()[$key] ?? ''));
    }

    protected function assetPath(string $key): ?string
    {
        return $this->normalizeAssetPath($this->settings()[$key] ?? null);
    }

    protected function resolveConfiguredAssetPath(?string $path, ?string $fallbackDirectory = null): ?string
    {
        $resolved = $this->normalizeAssetPath($path);

        if (filled($resolved)) {
            return $resolved;
        }

        if (blank($fallbackDirectory)) {
            return null;
        }

        return $this->discoverBrandingAsset($fallbackDirectory);
    }

    /**
     * @return array<string, string>
     */
    protected function settings(): array
    {
        if ($this->resolvedSettings !== null) {
            return $this->resolvedSettings;
        }

        $defaults = $this->defaults();

        if (! Schema::hasTable('pengaturan')) {
            return $this->resolvedSettings = $defaults;
        }

        $stored = collect(Pengaturan::values(array_keys($defaults), $defaults))
            ->map(fn ($value): string => trim((string) $value))
            ->all();

        $resolved = $defaults;

        foreach ($stored as $key => $value) {
            if ($value === '') {
                continue;
            }

            $resolved[$key] = $value;
        }

        $resolved[SiteSettingKeys::DEFAULT_OG_TITLE] = $resolved[SiteSettingKeys::DEFAULT_OG_TITLE] ?: $resolved[SiteSettingKeys::DEFAULT_SEO_TITLE];
        $resolved[SiteSettingKeys::DEFAULT_OG_DESCRIPTION] = $resolved[SiteSettingKeys::DEFAULT_OG_DESCRIPTION] ?: $resolved[SiteSettingKeys::DEFAULT_SEO_DESCRIPTION];
        $resolved[SiteSettingKeys::PWA_APP_NAME] = $resolved[SiteSettingKeys::PWA_APP_NAME] ?: $resolved[SiteSettingKeys::SITE_NAME];

        return $this->resolvedSettings = $resolved;
    }

    protected function normalizeAssetPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $value = trim($path);

        if (Str::startsWith($value, ['http://', 'https://', '//', 'data:'])) {
            return $value;
        }

        if (Str::startsWith($value, '/storage/')) {
            return $value;
        }

        if (Str::startsWith($value, 'storage/')) {
            return '/'.$value;
        }

        if (Str::startsWith($value, 'public/')) {
            $value = Str::after($value, 'public/');
        }

        return Storage::url(ltrim($value, '/'));
    }

    protected function discoverBrandingAsset(string $directory): ?string
    {
        $files = collect(Storage::disk('public')->files($directory))
            ->filter(fn (string $path): bool => filled($path))
            ->sortDesc()
            ->values();

        $path = $files->first();

        return filled($path) ? Storage::url($path) : null;
    }
}
