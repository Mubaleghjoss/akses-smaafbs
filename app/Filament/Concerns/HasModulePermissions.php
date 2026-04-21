<?php

namespace App\Filament\Concerns;

use App\Models\User;

trait HasModulePermissions
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::userCanModule('view');
    }

    public static function canCreate(): bool
    {
        return static::userCanModule('manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCanModule('manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCanModule('manage');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanModule('manage');
    }

    public static function canForceDelete($record): bool
    {
        return static::userCanModule('manage');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::userCanModule('manage');
    }

    public static function canRestore($record): bool
    {
        return static::userCanModule('manage');
    }

    public static function canRestoreAny(): bool
    {
        return static::userCanModule('manage');
    }

    protected static function userCanModule(string $ability): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $user->loadMissing('roles');

        $prefix = static::modulePermissionPrefix();

        if ($user->hasRole('admin')) {
            return true;
        }

        if (blank($prefix)) {
            return false;
        }

        if ($ability === 'view') {
            return $user->canViewModule($prefix);
        }

        return $user->canManageModule($prefix);
    }

    protected static function modulePermissionPrefix(): ?string
    {
        return property_exists(static::class, 'permissionPrefix')
            ? static::$permissionPrefix
            : null;
    }
}
