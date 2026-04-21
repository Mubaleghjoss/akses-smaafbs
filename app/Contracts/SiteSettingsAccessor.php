<?php

namespace App\Contracts;

interface SiteSettingsAccessor
{
    /**
     * @return array{
     *     site_name: string,
     *     topbar_badge: string,
     *     topbar_text: string,
     *     footer_primary_text: string,
     *     footer_secondary_text: string,
     *     logo_path: ?string,
     *     favicon_path: ?string,
     *     default_seo_title: string,
     *     default_seo_description: string,
     *     default_og_title: string,
     *     default_og_description: string,
     *     default_og_image: ?string,
     *     theme_color: string,
     *     pwa_app_name: string,
     *     pwa_short_name: string
     * }
     */
    public function all(): array;

    public function siteName(): string;

    public function topbarBadge(): string;

    public function topbarText(): string;

    public function footerPrimaryText(): string;

    public function footerSecondaryText(): string;

    public function logoPath(): ?string;

    public function faviconPath(): ?string;

    public function defaultSeoTitle(): string;

    public function defaultSeoDescription(): string;

    public function defaultOgTitle(): string;

    public function defaultOgDescription(): string;

    public function defaultOgImage(): ?string;

    public function themeColor(): string;

    public function pwaAppName(): string;

    public function pwaShortName(): string;
}
