<?php

namespace App\Support\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class VisiMisiSanitizer
{
    public static function sanitizeTitle(?string $title): string
    {
        $plain = trim(strip_tags((string) $title));

        return preg_replace('/\s+/u', ' ', $plain) ?? '';
    }

    public static function sanitizeContent(?string $content): string
    {
        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
        );

        return trim($sanitizer->sanitize((string) $content));
    }
}
