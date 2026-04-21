<?php

namespace App\Support\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Collection;

class AdminNavigationSupport
{
    /**
     * @var array<string, Collection<int, array{class:string,label:string,group:string,group_label:string,parent:?string}>>
     */
    protected static array $definitionsCache = [];

    public static function availableNavigationItemOptions(?Panel $panel = null): array
    {
        return self::definitions($panel)
            ->sortBy(fn (array $definition): string => $definition['group_label'].' '.($definition['parent'] ?? '').' '.$definition['label'])
            ->mapWithKeys(fn (array $definition): array => [
                $definition['class'] => collect([
                    $definition['group_label'],
                    $definition['parent'] ?? null,
                    $definition['label'],
                ])->filter()->implode(' -> '),
            ])
            ->all();
    }

    /**
     * @return Collection<int, array{class:string,label:string,group:string,group_label:string,parent:?string}>
     */
    public static function definitions(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();

        if (! $panel) {
            return collect();
        }

        $cacheKey = $panel->getId();

        if (array_key_exists($cacheKey, self::$definitionsCache)) {
            return self::$definitionsCache[$cacheKey];
        }

        $pages = collect($panel->getPages())
            ->filter(fn (string $page): bool => blank($page::getCluster()) && $page::shouldRegisterNavigation())
            ->map(fn (string $page): array => self::definitionForClass($page));

        $resources = collect($panel->getResources())
            ->filter(fn (string $resource): bool => blank($resource::getCluster()) && $resource::shouldRegisterNavigation())
            ->map(fn (string $resource): array => self::definitionForClass($resource));

        return self::$definitionsCache[$cacheKey] = $pages->concat($resources)->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function allowedNavigationItemClasses(?User $user): Collection
    {
        return collect($user?->resolvedNavigationItems() ?? []);
    }

    /**
     * @return array{class:string,label:string,group:string,group_label:string,parent:?string}
     */
    protected static function definitionForClass(string $class): array
    {
        $group = User::normalizeNavigationGroupKey(AdminSchoolNavigation::effectiveGroupForClass($class));
        $label = method_exists($class, 'getNavigationLabel')
            ? ($class::getNavigationLabel() ?: class_basename($class))
            : class_basename($class);

        return [
            'class' => $class,
            'label' => $label,
            'group' => $group,
            'group_label' => User::navigationGroupLabel($group),
            'parent' => AdminSchoolNavigation::parentItemForClass($class),
        ];
    }
}
