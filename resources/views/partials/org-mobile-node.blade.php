@php
    $children = collect($node->children ?? []);
    $imageOptimizer = app(\App\Support\Media\PublicImageOptimizer::class);
    $fotoUrl = filled($node->foto) ? $imageOptimizer->url($node->foto, 'thumbnail') : null;
    $fotoDisplayUrl = filled($node->foto) ? $imageOptimizer->url($node->foto, 'display') : null;
    $depth = $depth ?? 0;
    $siblingCount = $siblingCount ?? 1;
    $showProfileLinks = $showProfileLinks ?? true;
    $profileUrl = $showProfileLinks && filled($node->guru_tendik_id) ? route('guru-tendik.profile', $node->guru_tendik_id) : null;
@endphp

<div class="org-mobile__item">
    @if($children->isNotEmpty())
        <details class="org-mobile__details" open>
            <summary class="org-mobile__summary">
                <div class="org-mobile__node">
                    @if($fotoUrl)
                        <button
                            type="button"
                            class="org-mobile__avatar cursor-pointer border-0 bg-transparent p-0"
                            data-org-image-trigger
                            data-org-image-src="{{ $fotoDisplayUrl }}"
                            data-org-image-name="{{ $node->nama }}"
                            data-org-image-role="{{ $node->jabatan }}"
                            aria-label="Lihat foto {{ $node->nama }}"
                        >
                            <img
                                class="h-full w-full object-cover"
                                data-public-lazy-image
                                data-src="{{ $fotoUrl }}"
                                width="240"
                                height="300"
                                loading="lazy"
                                decoding="async"
                                alt="Foto {{ $node->nama }}"
                            >
                        </button>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="org-mobile__role">{{ $node->jabatan }}</div>
                        <div class="org-mobile__name">{{ $node->nama }}</div>

                        @if($profileUrl)
                            <a href="{{ $profileUrl }}" class="mt-2 inline-flex text-xs font-semibold text-sky-700 transition hover:text-sky-900">
                                Lihat profil
                            </a>
                        @endif
                    </div>
                </div>
            </summary>

            <div class="org-mobile__children">
                @foreach($children as $child)
                    @include('partials.org-mobile-node', [
                        'node' => $child,
                        'depth' => $depth + 1,
                        'siblingCount' => $children->count(),
                        'showProfileLinks' => $showProfileLinks,
                    ])
                @endforeach
            </div>
        </details>
    @else
        <div class="org-mobile__node">
            @if($fotoUrl)
                <button
                    type="button"
                    class="org-mobile__avatar cursor-pointer border-0 bg-transparent p-0"
                    data-org-image-trigger
                    data-org-image-src="{{ $fotoDisplayUrl }}"
                    data-org-image-name="{{ $node->nama }}"
                    data-org-image-role="{{ $node->jabatan }}"
                    aria-label="Lihat foto {{ $node->nama }}"
                >
                    <img
                        class="h-full w-full object-cover"
                        data-public-lazy-image
                        data-src="{{ $fotoUrl }}"
                        width="240"
                        height="300"
                        loading="lazy"
                        decoding="async"
                        alt="Foto {{ $node->nama }}"
                    >
                </button>
            @endif

            <div class="min-w-0 flex-1">
                <div class="org-mobile__role">{{ $node->jabatan }}</div>
                <div class="org-mobile__name">{{ $node->nama }}</div>

                @if($profileUrl)
                    <a href="{{ $profileUrl }}" class="mt-2 inline-flex text-xs font-semibold text-sky-700 transition hover:text-sky-900">
                        Lihat profil
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
