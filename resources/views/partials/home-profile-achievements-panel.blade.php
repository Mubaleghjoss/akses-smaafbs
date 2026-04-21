@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Prestasi> $prestasiSiswa */
    $prestasiSiswa = $prestasiSiswa ?? collect();
@endphp

<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Prestasi Siswa/i</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900 md:text-2xl">Daftar prestasi terbaru siswa dan siswi</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600 md:text-base">
                    Ringkasan capaian lomba, kejuaraan, dan penghargaan siswa yang telah dipublikasikan sekolah.
                </p>
            </div>

            <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ number_format($prestasiSiswa->count()) }} data terbaru
            </div>
        </div>

        @if($prestasiSiswa->isEmpty())
            <div class="mt-4 text-sm text-slate-500">Data prestasi siswa belum dipublikasikan saat ini.</div>
        @else
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach($prestasiSiswa as $prestasi)
                    @php
                        $certificatePath = $prestasi->certificateFiles()[0] ?? null;
                        $documentationPath = $prestasi->documentationFiles()[0] ?? null;
                        $archiveUrl = $prestasi->resolvedDriveUrl();
                        $certificateUrl = filled($certificatePath) ? \Illuminate\Support\Facades\Storage::disk('public')->url($certificatePath) : null;
                        $documentationUrl = filled($documentationPath) ? \Illuminate\Support\Facades\Storage::disk('public')->url($documentationPath) : null;
                    @endphp

                    <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    {{ $prestasi->tanggal_prestasi?->format('d/m/Y') ?? '-' }}
                                </div>
                                <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $prestasi->nama_lomba ?: 'Prestasi siswa' }}</h3>
                            </div>

                            @if(filled($prestasi->juara))
                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                    {{ $prestasi->juara }}
                                </span>
                            @endif
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Siswa</dt>
                                <dd class="mt-1 font-medium text-slate-900">
                                    {{ $prestasi->siswa?->nama ?: 'Siswa tidak tersedia' }}
                                    @if(filled($prestasi->siswa?->rombel_saat_ini))
                                        <span class="font-normal text-slate-500">· {{ $prestasi->siswa->rombel_saat_ini }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Penyelenggara</dt>
                                <dd class="mt-1 text-slate-700">{{ $prestasi->penyelenggara ?: '-' }}</dd>
                            </div>
                            @if(filled($prestasi->hadiah))
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Hadiah</dt>
                                    <dd class="mt-1 text-slate-700">{{ $prestasi->hadiah }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Lampiran</dt>
                                <dd class="mt-1 text-slate-700">
                                    {{ count($prestasi->certificateFiles()) }} sertifikat · {{ count($prestasi->documentationFiles()) }} dokumentasi
                                </dd>
                            </div>
                        </dl>

                        @if(filled($prestasi->keterangan))
                            <p class="mt-4 text-sm leading-6 text-slate-600">
                                {{ \Illuminate\Support\Str::limit($prestasi->keterangan, 180) }}
                            </p>
                        @endif

                        @if(filled($archiveUrl) || filled($certificateUrl) || filled($documentationUrl))
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if(filled($archiveUrl))
                                    <a href="{{ $archiveUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">Buka Arsip</a>
                                @endif
                                @if(filled($certificateUrl))
                                    <a href="{{ $certificateUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">Lihat Sertifikat</a>
                                @endif
                                @if(filled($documentationUrl))
                                    <a href="{{ $documentationUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">Lihat Dokumentasi</a>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
