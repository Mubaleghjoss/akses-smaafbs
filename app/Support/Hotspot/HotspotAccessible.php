<?php

namespace App\Support\Hotspot;

use App\Models\User;

/**
 * Gerbang akses menu MikroTik: hanya admin penuh (admin/guru_admin/super_admin)
 * atau user yang di-beri akses lewat kolom allowed_navigation_items di UserResource.
 */
trait HotspotAccessible
{
    public static function hotspotAccessGranted(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }
        if ($user->hasFullAdminAccess()) {
            return true;
        }

        return in_array(static::class, (array) ($user->allowed_navigation_items ?? []), true);
    }
}