@php
    $nodes = collect($nodes ?? []);
    $showProfileLinks = $showProfileLinks ?? true;
@endphp

@if($nodes->isNotEmpty())
    @php
        $leadNode = $nodes->first(function ($node) {
            $jabatan = \Illuminate\Support\Str::lower(trim((string) $node->jabatan));

            return \Illuminate\Support\Str::startsWith($jabatan, 'kepala sekolah');
        });
        $siblingNodes = $leadNode
            ? $nodes->reject(fn ($node) => $node->id === $leadNode->id)->values()
            : collect();
        $shouldRenderLeadershipBoard = $leadNode !== null
            && $siblingNodes->isNotEmpty()
            && collect($leadNode?->children ?? [])->isEmpty();
        $mobileNodes = $shouldRenderLeadershipBoard
            ? collect([$leadNode])->concat($siblingNodes)
            : $nodes;
    @endphp

    <div class="hidden rounded-3xl border border-slate-200 bg-white p-6 lg:block">
        <div class="org-tree-frame">
            <ul class="org-tree">
                @foreach($nodes as $node)
                    @include('partials.org-chart-node', [
                        'node' => $node,
                        'depth' => 0,
                        'siblingCount' => $nodes->count(),
                        'showProfileLinks' => $showProfileLinks,
                    ])
                @endforeach
            </ul>
        </div>
    </div>

    <div class="space-y-3 lg:hidden">
        @foreach($mobileNodes as $node)
            @include('partials.org-mobile-node', [
                'node' => $node,
                'depth' => 0,
                'siblingCount' => $mobileNodes->count(),
                'showProfileLinks' => $showProfileLinks,
            ])
        @endforeach
    </div>
@endif
