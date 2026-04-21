@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasChildren = filled($childItems);
    $canToggleChildren = $hasChildren && blank($url);
@endphp

<li
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
            'fi-sidebar-item-has-children' => $hasChildren,
        ])
    }}
    @if ($hasChildren)
        x-data="{ open: @js($active || $activeChildItems) }"
    @endif
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        x-on:click="
            if (@js($canToggleChildren)) {
                $event.preventDefault()
                open = ! open
                return
            }

            window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()
        "
        @if ($canToggleChildren)
            role="button"
            x-bind:aria-expanded="open.toString()"
        @endif
        @if ($sidebarCollapsible && (! $subNavigation))
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        class="fi-sidebar-item-btn"
    >
        @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
            {{
                \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Illuminate\View\ComponentAttributeBag([
                    'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
            }}
        @endif

        @if ((blank($icon) && $grouped) || $subGrouped)
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border"
            >
                @if (! $first)
                    <div class="fi-sidebar-item-grouped-border-part-not-first"></div>
                @endif

                @if (! $last)
                    <div class="fi-sidebar-item-grouped-border-part-not-last"></div>
                @endif

                <div class="fi-sidebar-item-grouped-border-part"></div>
            </div>
        @endif

        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-item-label"
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-item-badge-ctn"
            >
                <x-filament::badge
                    :color="$badgeColor"
                    :tooltip="$badgeTooltip"
                >
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif

        @if ($hasChildren)
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="ms-auto inline-flex items-center justify-center transition duration-200"
                x-bind:class="{ 'rotate-180': open }"
            >
                {{
                    \Filament\Support\generate_icon_html(
                        'heroicon-m-chevron-down',
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-4 text-gray-400'])
                    )
                }}
            </span>
        @endif
    </a>

    @if ($hasChildren)
        <ul
            x-cloak
            x-show="open"
            x-transition:enter="fi-transition-enter"
            x-transition:enter-start="fi-transition-enter-start"
            x-transition:enter-end="fi-transition-enter-end"
            class="fi-sidebar-sub-group-items"
        >
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor();
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip();
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-filament-panels::sidebar.item
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    :child-items="$childItem->getChildItems()"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
