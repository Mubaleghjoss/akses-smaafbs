@php
    $children = collect($node->children ?? []);
    $fotoUrl = filled($node->foto) ? asset('storage/'.$node->foto) : null;
    $depth = $depth ?? 0;
    $siblingCount = $siblingCount ?? 1;
    $renderChildren = $renderChildren ?? true;
    $showProfileLinks = $showProfileLinks ?? true;
    $profileUrl = $showProfileLinks && filled($node->guru_tendik_id) ? route('guru-tendik.profile', $node->guru_tendik_id) : null;
    $branchDrop = match (true) {
        $depth >= 2 => '6.5rem',
        $depth === 1 => '5.5rem',
        default => '4rem',
    };
    $childrenByRow = $children
        ->sort(fn ($left, $right) => [
            $left->homepageRowNumber(),
            $left->homepageOrderNumber(),
            (int) ($left->urutan ?? 0),
            (int) $left->id,
        ] <=> [
            $right->homepageRowNumber(),
            $right->homepageOrderNumber(),
            (int) ($right->urutan ?? 0),
            (int) $right->id,
        ])
        ->groupBy(fn ($child) => $child->homepageRowNumber())
        ->values();
    $depthClass = match (true) {
        $depth === 0 => 'org-tree__node--root',
        $depth === 1 => 'org-tree__node--branch',
        default => 'org-tree__node--leaf',
    };
@endphp

<li class="org-tree__item {{ $depth === 0 ? 'org-tree__item--root' : '' }}">
    <div class="org-tree__node-wrap {{ $children->isNotEmpty() ? 'org-tree__node-wrap--branch' : '' }}">
        <div class="org-tree__node {{ $depthClass }}">
            @if($fotoUrl)
                <button
                    type="button"
                    class="org-tree__avatar cursor-pointer border-0 bg-transparent p-0"
                    data-org-image-trigger
                    data-org-image-src="{{ $fotoUrl }}"
                    data-org-image-name="{{ $node->nama }}"
                    data-org-image-role="{{ $node->jabatan }}"
                    aria-label="Lihat foto {{ $node->nama }}"
                >
                    <img class="h-full w-full object-cover" src="{{ $fotoUrl }}" alt="Foto {{ $node->nama }}">
                </button>
            @endif

            <div class="org-tree__meta {{ $fotoUrl ? '' : 'org-tree__meta--no-avatar' }}">
                <div class="org-tree__role">{{ $node->jabatan }}</div>
                <div class="org-tree__name">{{ $node->nama }}</div>

                @if($profileUrl)
                    <a href="{{ $profileUrl }}" class="mt-2 inline-flex text-[11px] font-semibold text-sky-700 transition hover:text-sky-900">
                        Lihat profil
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($renderChildren && $children->isNotEmpty())
        <div class="org-tree__rows">
            @foreach($childrenByRow as $rowIndex => $rowChildren)
                @php
                    $rowColumns = min(10, max(1, $rowChildren->count()));
                    $rowDrop = $rowIndex === 0
                        ? $branchDrop
                        : ($depth >= 2 ? '4.5rem' : '3.75rem');
                    $rowRailInset = match (true) {
                        $rowChildren->count() >= 8 => '0.35rem',
                        $rowChildren->count() >= 5 => '0.75rem',
                        default => '1.25rem',
                    };
                @endphp

                <div
                    class="org-tree__children-shell {{ $depth === 0 ? 'org-tree__children-shell--root' : '' }} {{ $rowChildren->count() > 1 ? 'org-tree__children-shell--branch' : '' }}"
                    style="--org-branch-drop: {{ $rowDrop }}; --org-branch-rail-inset: {{ $rowRailInset }}; --org-sibling-columns: {{ $rowColumns }};"
                >
                    <ul
                        class="org-tree__children {{ $depth === 0 ? 'org-tree__children--root' : '' }} {{ $rowChildren->count() > 1 ? 'org-tree__children--branch' : '' }}"
                        @if($rowChildren->count() > 1)
                            style="--org-sibling-columns: {{ $rowColumns }};"
                        @endif
                    >
                        @foreach($rowChildren as $child)
                            @include('partials.org-chart-node', [
                                'node' => $child,
                                'depth' => $depth + 1,
                                'siblingCount' => $rowChildren->count(),
                                'showProfileLinks' => $showProfileLinks,
                            ])
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</li>
