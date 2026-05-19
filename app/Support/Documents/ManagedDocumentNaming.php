<?php

namespace App\Support\Documents;

use Illuminate\Support\Str;

class ManagedDocumentNaming
{
    public static function readableLabel(?string $value, string $fallback = 'Berkas'): string
    {
        $normalized = (string) Str::of(Str::ascii((string) $value))
            ->replaceMatches('/[^A-Za-z0-9\-\s]/', ' ')
            ->squish()
            ->limit(80, '');

        return $normalized !== '' ? $normalized : $fallback;
    }

    public static function slugLabel(?string $value, string $fallback = 'berkas'): string
    {
        $slug = (string) Str::of(Str::ascii((string) $value))
            ->slug('-')
            ->limit(90, '');

        return $slug !== '' ? $slug : $fallback;
    }

    public static function normalizeExtension(?string $extension): string
    {
        $normalized = Str::lower(ltrim((string) $extension, '.'));

        return $normalized !== '' ? $normalized : 'bin';
    }

    public static function extensionFromPath(string $path): string
    {
        return static::normalizeExtension(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function ownerFolderName(string $ownerType, mixed $ownerId, ?string $ownerName): string
    {
        $typeLabel = static::readableLabel($ownerType, 'Data');
        $nameLabel = static::readableLabel($ownerName, 'Tanpa Nama');
        $idLabel = filled($ownerId) ? trim((string) $ownerId) : 'baru';

        return "{$typeLabel} - {$nameLabel} [{$idLabel}]";
    }

    public static function displayFileName(
        string $scopeLabel,
        ?string $documentType,
        ?string $ownerName,
        ?string $extension = null,
        mixed $recordId = null,
    ): string {
        $parts = [
            static::readableLabel($scopeLabel, 'Berkas'),
            static::readableLabel($documentType, 'Dokumen'),
            static::readableLabel($ownerName, 'Tanpa Nama'),
        ];

        if (filled($recordId)) {
            $parts[] = trim((string) $recordId);
        }

        $base = implode(' - ', array_filter($parts, fn (?string $part): bool => filled($part)));
        $normalizedExtension = static::normalizeExtension($extension);

        return $base.'.'.$normalizedExtension;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    public static function fileNameFromParts(array $parts, ?string $extension = null): string
    {
        $labels = collect($parts)
            ->map(fn (mixed $part): string => static::readableLabel(is_scalar($part) ? (string) $part : null, ''))
            ->filter()
            ->values();

        $base = $labels->isNotEmpty() ? $labels->implode(' - ') : 'Berkas';
        $base = (string) Str::of($base)->limit(180, '')->trim();

        return $base.'.'.static::normalizeExtension($extension);
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    public static function storageFileNameFromParts(array $parts, ?string $extension = null): string
    {
        return static::fileNameFromParts($parts, $extension);
    }

    public static function storageFileName(
        string $scopeSlug,
        mixed $ownerId,
        ?string $documentType,
        ?string $ownerName,
        ?string $extension,
        ?string $entropySource = null,
    ): string {
        $scope = static::slugLabel($scopeSlug, 'berkas');
        $type = static::slugLabel($documentType, 'dokumen');
        $owner = static::slugLabel($ownerName, 'tanpa-nama');
        $idLabel = filled($ownerId) ? trim((string) $ownerId) : 'baru';
        $hash = substr(md5((string) ($entropySource ?: implode('|', [$scope, $type, $owner, $idLabel]))), 0, 8);

        return implode('-', [$scope, $type, $owner, $idLabel, $hash]).'.'.static::normalizeExtension($extension);
    }
}
