@php
    /** @var \App\Models\ProfilSekolah|null $profilSekolah */
    $profilSekolah = $profilSekolah ?? null;
    $identityRows = $profilSekolah?->identityRows() ?? [];
    $accreditationFileUrl = $profilSekolah?->resolvedAccreditationFileUrl();
    $socialLinks = $profilSekolah?->socialLinks() ?? [];
    $facilities = $profilSekolah?->facilities() ?? [];
    $scheduleItems = $profilSekolah?->scheduleItems() ?? [];
    $mealMenuItems = $profilSekolah?->mealMenuItems() ?? [];
@endphp

@if (! $profilSekolah)
    <div class="rounded-3xl border border-slate-200 bg-white p-5 text-sm text-slate-500 md:p-6">
        Identitas sekolah belum dipublikasikan saat ini.
    </div>
@else
    <div class="space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">1. Identitas Sekolah</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900 md:text-2xl">{{ $profilSekolah->title }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600 md:text-base">
                        Ringkasan identitas sekolah, kontak resmi, fasilitas utama, jadwal KBM, dan menu makan harian.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if(filled($profilSekolah->maps_url))
                        <a href="{{ $profilSekolah->maps_url }}" target="_blank" rel="noopener" class="btn btn-secondary">Buka Maps</a>
                    @endif
                    @if(filled($profilSekolah->website_url))
                        <a href="{{ $profilSekolah->website_url }}" target="_blank" rel="noopener" class="btn btn-secondary">Buka Website</a>
                    @endif
                    @if(filled($accreditationFileUrl))
                        <a href="{{ $accreditationFileUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">Lihat Dokumen Akreditasi</a>
                    @endif
                </div>
            </div>

            @if($identityRows === [])
                <div class="mt-4 text-sm text-slate-500">Identitas resmi sekolah belum diisi.</div>
            @else
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                    <div class="divide-y divide-slate-200 bg-white">
                        @foreach($identityRows as $row)
                            <div class="grid gap-2 px-4 py-3 md:grid-cols-[220px_1fr] md:gap-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $row['label'] }}</div>
                                <div class="text-sm leading-6 text-slate-700 md:text-base">
                                    @if(filled($row['url'] ?? null))
                                        <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-4 transition hover:text-sky-700">
                                            {{ $row['value'] }}
                                        </a>
                                    @else
                                        <span class="whitespace-pre-line">{{ $row['value'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($socialLinks !== [])
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($socialLinks as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">2. Fasilitas</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900 md:text-xl">Tempat dan fasilitas sekolah</h3>
                </div>
                <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ number_format(count($facilities)) }} fasilitas
                </div>
            </div>

            @if($facilities === [])
                <div class="mt-4 text-sm text-slate-500">Data fasilitas belum dipublikasikan saat ini.</div>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($facilities as $facility)
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/70">
                            @if(filled($facility['foto']))
                                <img
                                    data-public-lazy-image
                                    data-src="{{ app(\App\Support\Media\PublicImageOptimizer::class)->url($facility['foto']) }}"
                                    alt="{{ $facility['nama'] ?: 'Fasilitas sekolah' }}"
                                    class="h-48 w-full object-cover"
                                    width="640"
                                    height="360"
                                    loading="lazy"
                                    decoding="async"
                                >
                            @endif
                            <div class="p-4">
                                <h4 class="text-base font-semibold text-slate-900">{{ $facility['nama'] ?: 'Fasilitas sekolah' }}</h4>
                                @if(filled($facility['keterangan']))
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $facility['keterangan'] }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 md:p-6">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">3. Jadwal KBM</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900 md:text-xl">Waktu dan kegiatan belajar</h3>
                </div>

                @if($scheduleItems === [])
                    <div class="mt-4 text-sm text-slate-500">Jadwal KBM belum dipublikasikan saat ini.</div>
                @else
                    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Waktu</th>
                                    <th class="px-4 py-3 text-left font-semibold">Kegiatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-slate-700">
                                @foreach($scheduleItems as $item)
                                    <tr>
                                        <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $item['waktu'] ?: '-' }}</td>
                                        <td class="px-4 py-3 align-top">{{ $item['kegiatan'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 md:p-6">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">4. Menu Makan</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900 md:text-xl">Menu harian</h3>
                </div>

                @if($mealMenuItems === [])
                    <div class="mt-4 text-sm text-slate-500">Menu makan belum dipublikasikan saat ini.</div>
                @else
                    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Hari</th>
                                    <th class="px-4 py-3 text-left font-semibold">Menu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-slate-700">
                                @foreach($mealMenuItems as $item)
                                    <tr>
                                        <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $item['hari'] ?: '-' }}</td>
                                        <td class="px-4 py-3 align-top">{{ $item['menu'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endif

