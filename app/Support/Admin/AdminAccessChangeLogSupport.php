<?php

namespace App\Support\Admin;

use App\Models\AdminAccessChangeLog;
use App\Models\User;

class AdminAccessChangeLogSupport
{
    /**
     * @param  array<int, string>  $templateKeys
     * @param  array<string, string>  $beforeLevels
     * @param  array<string, string>  $afterLevels
     * @param  array{actor_user_id?:int|null,source?:string|null,note?:string|null}  $context
     */
    public static function log(User $targetUser, string $action, array $templateKeys, array $beforeLevels, array $afterLevels, array $context = []): void
    {
        $templateKeys = AdminRoleTemplateSupport::normalizeTemplateKeys($templateKeys);
        $beforeLevels = AdminModuleAccess::normalizeLevels($beforeLevels);
        $afterLevels = AdminModuleAccess::normalizeLevels($afterLevels);

        if ($templateKeys === [] || $beforeLevels === $afterLevels) {
            return;
        }

        $changedPrefixes = collect($afterLevels)
            ->filter(fn (string $level, string $prefix): bool => ($beforeLevels[$prefix] ?? AdminModuleAccess::NONE) !== $level)
            ->keys()
            ->values()
            ->all();

        AdminAccessChangeLog::query()->create([
            'target_user_id' => $targetUser->getKey(),
            'actor_user_id' => $context['actor_user_id'] ?? auth()->id(),
            'action' => $action,
            'source' => $context['source'] ?? null,
            'template_keys' => $templateKeys,
            'before_levels' => $beforeLevels,
            'after_levels' => $afterLevels,
            'changed_prefixes' => $changedPrefixes,
            'note' => $context['note'] ?? null,
        ]);
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @return array<int, string>
     */
    public static function templateLabels(array $templateKeys): array
    {
        return collect(AdminRoleTemplateSupport::normalizeTemplateKeys($templateKeys))
            ->map(fn (string $key): ?string => AdminRoleTemplateSupport::definitions()[$key]['label'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $prefixes
     * @return array<int, string>
     */
    public static function changedModuleLabels(array $prefixes): array
    {
        return collect($prefixes)
            ->map(fn (string $prefix): ?string => AdminModuleAccess::definition($prefix)['label'] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
