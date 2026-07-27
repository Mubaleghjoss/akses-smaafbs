@extends('layouts.app')

@section('content')
    <section class="card p-7 md:p-8 reveal">
        <div>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="chip">Identitas sekolah</span>
                    <h1 class="mt-4 text-3xl font-semibold text-balance md:text-5xl">Identitas sekolah untuk orang tua, siswa, dan masyarakat.</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base">
                        Lihat struktur kepengurusan, komite sekolah, identitas sekolah, dan ringkasan visi misi dalam satu area agar informasi utama sekolah mudah dipahami.
                    </p>
                </div>
                <div class="flex w-full flex-wrap gap-3 sm:w-auto sm:justify-end">
                    <a class="btn btn-primary" href="#data-siswa">Cari data siswa</a>
                    <a class="btn btn-secondary" href="#tracker-kegiatan">Lihat info kegiatan</a>
                </div>
            </div>

            <div class="mt-6">
                <div class="home-profile-tabs" data-home-profile-tabs>
                    <div class="home-profile-tabs__actions" role="tablist" aria-label="Identitas sekolah">
                        <button
                            type="button"
                            class="home-profile-tab is-active"
                            id="home-profile-tab-struktur"
                            role="tab"
                            aria-selected="true"
                            aria-controls="home-profile-panel-struktur"
                            data-home-profile-tab-trigger="struktur"
                        >
                            Struktur Sekolah
                        </button>
                        <button
                            type="button"
                            class="home-profile-tab"
                            id="home-profile-tab-komite"
                            role="tab"
                            aria-selected="false"
                            aria-controls="home-profile-panel-komite"
                            data-home-profile-tab-trigger="komite"
                        >
                            Struktur Komite
                        </button>
                        <button
                            type="button"
                            class="home-profile-tab"
                            id="home-profile-tab-identitas-sekolah"
                            role="tab"
                            aria-selected="false"
                            aria-controls="home-profile-panel-identitas-sekolah"
                            data-home-profile-tab-trigger="identitas-sekolah"
                        >
                            Identitas Sekolah
                        </button>
                        <button
                            type="button"
                            class="home-profile-tab"
                            id="home-profile-tab-prestasi-siswa"
                            role="tab"
                            aria-selected="false"
                            aria-controls="home-profile-panel-prestasi-siswa"
                            data-home-profile-tab-trigger="prestasi-siswa"
                        >
                            Prestasi Siswa/i
                        </button>
                        <button
                            type="button"
                            class="home-profile-tab"
                            id="home-profile-tab-visi-misi"
                            role="tab"
                            aria-selected="false"
                            aria-controls="home-profile-panel-visi-misi"
                            data-home-profile-tab-trigger="visi-misi"
                        >
                            Visi Misi
                        </button>
                    </div>

                    <div
                        id="home-profile-panel-struktur"
                        role="tabpanel"
                        aria-labelledby="home-profile-tab-struktur"
                        data-home-profile-tab-panel="struktur"
                    >
                        @include('partials.home-profile-org-panel', [
                            'nodes' => $strukturOrganisasi,
                            'emptyMessage' => 'Struktur kepengurusan belum dipublikasikan saat ini.',
                        ])
                    </div>

                    <div
                        id="home-profile-panel-komite"
                        role="tabpanel"
                        aria-labelledby="home-profile-tab-komite"
                        data-home-profile-tab-panel="komite"
                        hidden
                    >
                        @include('partials.home-profile-org-panel', [
                            'nodes' => $komiteOrganisasi,
                            'periods' => $komitePeriods,
                            'emptyMessage' => 'Data komite sekolah belum dipublikasikan saat ini.',
                            'showProfileLinks' => false,
                        ])
                    </div>

                    <div
                        id="home-profile-panel-identitas-sekolah"
                        class="rounded-3xl border border-slate-200 bg-transparent"
                        role="tabpanel"
                        aria-labelledby="home-profile-tab-identitas-sekolah"
                        data-home-profile-tab-panel="identitas-sekolah"
                        hidden
                    >
                        @include('partials.home-profile-school-panel', [
                            'profilSekolah' => $profilSekolah,
                        ])
                    </div>

                    <div
                        id="home-profile-panel-prestasi-siswa"
                        class="rounded-3xl border border-slate-200 bg-transparent"
                        role="tabpanel"
                        aria-labelledby="home-profile-tab-prestasi-siswa"
                        data-home-profile-tab-panel="prestasi-siswa"
                        hidden
                    >
                        @include('partials.home-profile-achievements-panel', [
                            'prestasiSiswa' => $prestasiSiswa,
                        ])
                    </div>

                    <div
                        id="home-profile-panel-visi-misi"
                        class="rounded-3xl border border-slate-200 bg-white p-5 md:p-6"
                        role="tabpanel"
                        aria-labelledby="home-profile-tab-visi-misi"
                        data-home-profile-tab-panel="visi-misi"
                        hidden
                    >
                        @if($visiMisi)
                            <h2 class="text-xl font-semibold text-slate-900 md:text-2xl">{{ $visiMisi->title }}</h2>
                            <div class="home-vision-content mt-3 text-sm leading-relaxed text-slate-600 md:text-base">{!! $visiMisi->rendered_content !!}</div>
                        @else
                            <div class="text-sm text-slate-500">Konten visi misi sekolah belum dipublikasikan saat ini.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @php
        $studentActiveGenderTotal = $stats['student_active_male'] + $stats['student_active_female'];
        $studentActiveMalePercent = $studentActiveGenderTotal > 0 ? round(($stats['student_active_male'] / $studentActiveGenderTotal) * 100, 2) : 0;

        $guruGenderTotal = $stats['guru_male'] + $stats['guru_female'];
        $guruMalePercent = $guruGenderTotal > 0 ? round(($stats['guru_male'] / $guruGenderTotal) * 100, 2) : 0;

        $tendikGenderTotal = $stats['tendik_male'] + $stats['tendik_female'];
        $tendikMalePercent = $tendikGenderTotal > 0 ? round(($stats['tendik_male'] / $tendikGenderTotal) * 100, 2) : 0;

        $pamongGenderTotal = $stats['pamong_male'] + $stats['pamong_female'];
        $pamongMalePercent = $pamongGenderTotal > 0 ? round(($stats['pamong_male'] / $pamongGenderTotal) * 100, 2) : 0;

        $rombelItems = collect($stats['rombel_items'] ?? []);
        $rombelMaxStudents = max(1, (int) $rombelItems->max('students'));
        $rombelStudentTotal = (int) $rombelItems->sum('students');

        $prokerStatusTotal = $stats['proker_status_selesai'] + $stats['proker_status_berjalan'] + $stats['proker_status_draft'];
        $prokerSelesaiPercent = $prokerStatusTotal > 0 ? round(($stats['proker_status_selesai'] / $prokerStatusTotal) * 100, 2) : 0;
        $prokerBerjalanPercent = $prokerStatusTotal > 0 ? round(($stats['proker_status_berjalan'] / $prokerStatusTotal) * 100, 2) : 0;
        $prokerSegmentEnd1 = $prokerSelesaiPercent;
        $prokerSegmentEnd2 = $prokerSelesaiPercent + $prokerBerjalanPercent;
    @endphp

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3 reveal reveal-delay-1" data-home-mini-charts="overview">
        <article class="card hero-mini-card p-4" data-home-mini-chart-card="student-active-gender" style="--mini-chart-fill: {{ $studentActiveMalePercent }}%; --mini-chart-primary: #2563eb; --mini-chart-secondary: #f97316;">
            <div class="hero-mini-card__head">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Siswa Aktif (L/P)</div>
                <div class="text-xs text-slate-500">{{ number_format($stats['student_active_male']) }} / {{ number_format($stats['student_active_female']) }}</div>
            </div>
            <div class="hero-mini-card__total">
                <span>Total</span>
                <strong>{{ number_format($stats['active_students']) }}</strong>
                <span>siswa aktif</span>
            </div>
            <div class="hero-mini-card__body">
                <div class="hero-mini-card__chart" data-home-mini-chart-visual="donut"></div>
                <dl class="hero-mini-card__legend">
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--primary"></span>Laki-laki</dt>
                        <dd>{{ number_format($stats['student_active_male']) }}</dd>
                    </div>
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--muted"></span>Perempuan</dt>
                        <dd>{{ number_format($stats['student_active_female']) }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        <article class="card hero-mini-card p-4" data-home-mini-chart-card="guru-gender" style="--mini-chart-fill: {{ $guruMalePercent }}%; --mini-chart-primary: #16a34a; --mini-chart-secondary: #ec4899;">
            <div class="hero-mini-card__head">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Guru (L/P)</div>
                <div class="text-xs text-slate-500">{{ number_format($stats['guru_male']) }} / {{ number_format($stats['guru_female']) }}</div>
            </div>
            <div class="hero-mini-card__total">
                <span>Total</span>
                <strong>{{ number_format($stats['guru_count']) }}</strong>
                <span>guru</span>
            </div>
            <div class="hero-mini-card__body">
                <div class="hero-mini-card__chart" data-home-mini-chart-visual="donut"></div>
                <dl class="hero-mini-card__legend">
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--primary"></span>Laki-laki</dt>
                        <dd>{{ number_format($stats['guru_male']) }}</dd>
                    </div>
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--muted"></span>Perempuan</dt>
                        <dd>{{ number_format($stats['guru_female']) }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        <article class="card hero-mini-card p-4" data-home-mini-chart-card="tendik-gender" style="--mini-chart-fill: {{ $tendikMalePercent }}%; --mini-chart-primary: #0f766e; --mini-chart-secondary: #f59e0b;">
            <div class="hero-mini-card__head">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Tendik (L/P)</div>
                <div class="text-xs text-slate-500">{{ number_format($stats['tendik_male']) }} / {{ number_format($stats['tendik_female']) }}</div>
            </div>
            <div class="hero-mini-card__total">
                <span>Total</span>
                <strong>{{ number_format($stats['tendik_count']) }}</strong>
                <span>tendik</span>
            </div>
            <div class="hero-mini-card__body">
                <div class="hero-mini-card__chart" data-home-mini-chart-visual="donut"></div>
                <dl class="hero-mini-card__legend">
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--primary"></span>Laki-laki</dt>
                        <dd>{{ number_format($stats['tendik_male']) }}</dd>
                    </div>
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--muted"></span>Perempuan</dt>
                        <dd>{{ number_format($stats['tendik_female']) }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        <article class="card hero-mini-card p-4" data-home-mini-chart-card="pamong-gender" style="--mini-chart-fill: {{ $pamongMalePercent }}%; --mini-chart-primary: #059669; --mini-chart-secondary: #a855f7;">
            <div class="hero-mini-card__head">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Pamong (L/P)</div>
                <div class="text-xs text-slate-500">{{ number_format($stats['pamong_male']) }} / {{ number_format($stats['pamong_female']) }}</div>
            </div>
            <div class="hero-mini-card__total">
                <span>Total</span>
                <strong>{{ number_format($stats['pamong_count']) }}</strong>
                <span>pamong</span>
            </div>
            <div class="hero-mini-card__body">
                <div class="hero-mini-card__chart" data-home-mini-chart-visual="donut"></div>
                <dl class="hero-mini-card__legend">
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--primary"></span>Laki-laki</dt>
                        <dd>{{ number_format($stats['pamong_male']) }}</dd>
                    </div>
                    <div>
                        <dt><span class="hero-mini-card__dot hero-mini-card__dot--muted"></span>Perempuan</dt>
                        <dd>{{ number_format($stats['pamong_female']) }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        <article class="card hero-mini-card p-4" data-home-mini-chart-card="rombel">
            <div class="hero-mini-card__head">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Rombel</div>
                <div class="text-xs text-slate-500">Total {{ number_format($stats['rombel_count']) }}</div>
            </div>
            <div class="hero-mini-card__total">
                <span>Total</span>
                <strong>{{ number_format($stats['rombel_count']) }}</strong>
                <span>rombel / {{ number_format($rombelStudentTotal) }} siswa</span>
            </div>
            <ul class="hero-mini-card__bars" data-home-mini-chart-visual="rombel-bars">
                @forelse($rombelItems as $rombel)
                    @php
                        $barWidth = min(100, round(((int) $rombel['students'] / $rombelMaxStudents) * 100, 2));
                    @endphp
                    <li>
                        <div class="hero-mini-card__bars-head">
                            <span>{{ $rombel['name'] }}</span>
                            <span>{{ number_format((int) $rombel['students']) }} siswa</span>
                        </div>
                        <div class="hero-mini-card__bars-track">
                            <span class="hero-mini-card__bars-fill" style="width: {{ $barWidth }}%;"></span>
                        </div>
                    </li>
                @empty
                    <li class="text-xs text-slate-500">Data rombel aktif belum tersedia.</li>
                @endforelse
            </ul>
        </article>

        <article class="card hero-mini-card p-4" data-home-mini-chart-card="proker-status">
            <div class="hero-mini-card__head">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Proker</div>
                <div class="text-xs text-slate-500">Bidang: {{ number_format($stats['proker_bidang_count']) }}</div>
            </div>
            <div class="hero-mini-card__total">
                <span>Total</span>
                <strong>{{ number_format($stats['proker_count']) }}</strong>
                <span>proker / {{ number_format($stats['proker_bidang_count']) }} bidang</span>
            </div>
            <div class="hero-mini-card__body">
                <div
                    class="hero-mini-card__chart hero-mini-card__chart--segments"
                    data-home-mini-chart-visual="donut-segments"
                    style="background: conic-gradient(#22c55e 0 {{ $prokerSegmentEnd1 }}%, #3b82f6 {{ $prokerSegmentEnd1 }}% {{ $prokerSegmentEnd2 }}%, #94a3b8 {{ $prokerSegmentEnd2 }}% 100%);"
                ></div>
                <dl class="hero-mini-card__legend">
                    <div>
                        <dt><span class="hero-mini-card__dot" style="background-color: #22c55e;"></span>Selesai</dt>
                        <dd>{{ number_format($stats['proker_status_selesai']) }}</dd>
                    </div>
                    <div>
                        <dt><span class="hero-mini-card__dot" style="background-color: #3b82f6;"></span>Berjalan</dt>
                        <dd>{{ number_format($stats['proker_status_berjalan']) }}</dd>
                    </div>
                    <div>
                        <dt><span class="hero-mini-card__dot" style="background-color: #94a3b8;"></span>Draft</dt>
                        <dd>{{ number_format($stats['proker_status_draft']) }}</dd>
                    </div>
                </dl>
            </div>
        </article>
    </section>

    <section id="data-siswa" class="mt-12 card p-6 reveal">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="chip">Data siswa</div>
                <h2 class="mt-3 text-2xl font-semibold">Pencarian data siswa aktif dan alumni</h2>
                <p class="mt-2 text-sm text-slate-500">Telusuri data siswa melalui nama atau NISN, lalu lihat informasi dasar seperti status dan tanggal lahir.</p>
            </div>
            <form id="student-search-form" method="get" action="{{ route('home') }}" data-live-search-url="{{ route('home.student-search') }}" class="grid w-full max-w-xl gap-2 sm:grid-cols-[1fr_auto]">
                <input id="student-search-input" name="q" value="{{ $q }}" class="input min-w-0" placeholder="Ketik nama atau NISN siswa / alumni..." autocomplete="off" />
                <button class="btn btn-primary w-full sm:w-auto" type="submit">Cari</button>
            </form>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-700">Siswa Terdata</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($stats['students']) }}</div>
            </div>
            <div class="rounded-2xl border border-green-200 bg-green-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-green-700">Guru</div>
                <div class="mt-2 text-2xl font-semibold text-green-900">{{ number_format($stats['guru_count']) }}</div>
            </div>
            <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-teal-700">Pamong</div>
                <div class="mt-2 text-2xl font-semibold text-teal-900">{{ number_format($stats['pamong_count']) }}</div>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-indigo-700">Prestasi Siswa</div>
                <div class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format($stats['achievements']) }}</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-emerald-700">Siswa Aktif</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format($stats['active_students']) }}</div>
            </div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-sky-700">Alumni</div>
                <div class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format($stats['alumni_students']) }}</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-amber-700">Pencarian</div>
                <div id="student-search-summary" class="mt-2 text-sm font-medium text-amber-900">{{ $q !== '' && mb_strlen($q) >= 2 ? 'Menampilkan hasil pencarian untuk: '.$q : 'Hasil pencarian akan muncul setelah Anda mengetik minimal 2 huruf atau angka.' }}</div>
            </div>
        </div>

        <div id="student-search-results" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @include('partials.home-student-search-results', ['q' => $q, 'studentResults' => $studentResults])
        </div>
    </section>

    <section id="agenda-kegiatan" class="mt-12 card p-6 reveal">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="chip">Agenda kegiatan</div>
                <h2 class="mt-3 text-2xl font-semibold">Agenda kegiatan yang dijadwalkan</h2>
                <p class="mt-2 text-sm text-slate-500">Pilih salah satu agenda untuk melihat rincian kegiatan yang telah dicatat oleh admin.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('agenda.index') }}">Lihat kalender agenda</a>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 sm:p-4">
            @include('partials.public-agenda-calendar', [
                'calendarId' => 'home-agenda-calendar',
                'modalId' => 'home-agenda-modal',
                'titleId' => 'home-agenda-title',
                'dateId' => 'home-agenda-date',
                'descId' => 'home-agenda-desc',
                'closeId' => 'home-agenda-close',
            ])
        </div>
    </section>

    <section id="tracker-kegiatan" class="mt-12 card p-6 reveal">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="chip">Informasi kegiatan</div>
                <h2 class="mt-3 text-2xl font-semibold">Perkembangan kegiatan terbaru</h2>
                <p class="mt-2 text-sm text-slate-500">Bagian ini menampilkan perkembangan kegiatan yang diperbarui admin secara berkala, lengkap dengan dokumentasi dan tautan siaran langsung bila tersedia.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('news.index') }}">Lihat seluruh informasi</a>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            @forelse($trackerNews as $news)
                @php
                    $latestUpdate = $news->latestUpdate;
                    $trackerPhaseLabel = $news->tracker_phase_label ?: $latestUpdate?->phase_label;
                    $trackerProgress = filled($news->tracker_progress_percent) ? $news->tracker_progress_percent : $latestUpdate?->progress_percent;
                    $trackerExcerpt = $news->tracker_update_text ?: $latestUpdate?->update_text ?: str(strip_tags((string) $news->konten))->limit(160);
                    $documentation = collect($news->tracker_documentation_media ?: ($latestUpdate?->documentation_media ?? []))->filter()->take(3);
                    $trackerLiveUrl = $news->tracker_live_url ?: $latestUpdate?->live_url;
                @endphp
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $trackerPhaseLabel ?? 'Informasi kegiatan' }}</div>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">{{ $news->judul }}</h3>
                        </div>
                        <span class="chip">{{ filled($trackerProgress) ? $trackerProgress.'%' : ($trackerPhaseLabel ?? 'Update') }}</span>
                    </div>

                    <p class="mt-4 text-sm leading-relaxed text-slate-600">{{ $trackerExcerpt }}</p>

                    @if($documentation->isNotEmpty())
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            @foreach($documentation as $imagePath)
                                @php
                                    $documentationStoragePath = str_contains((string) $imagePath, '/')
                                        ? (string) $imagePath
                                        : 'news/documentation/'.$imagePath;
                                    $documentationUrl = str_starts_with((string) $imagePath, 'http://') || str_starts_with((string) $imagePath, 'https://')
                                        ? $imagePath
                                        : app(\App\Support\Media\PublicImageOptimizer::class)->url($documentationStoragePath);
                                @endphp
                                <img
                                    class="h-24 w-full rounded-2xl border border-slate-100 object-cover"
                                    src="{{ $documentationUrl }}"
                                    alt="Dokumentasi {{ $news->judul }}"
                                    width="320"
                                    height="192"
                                    loading="lazy"
                                    decoding="async"
                                />
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a class="btn btn-primary" href="{{ route('news.show', $news) }}">Baca informasi lengkap</a>
                        @if(filled($trackerLiveUrl))
                            <a class="btn btn-secondary" href="{{ $trackerLiveUrl }}" target="_blank" rel="noopener noreferrer">Buka siaran langsung</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="card p-5 text-sm text-slate-500 lg:col-span-2">Informasi perkembangan kegiatan belum tersedia saat ini.</div>
            @endforelse
        </div>
    </section>

    <div id="org-photo-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" aria-hidden="true" role="dialog">
        <div class="absolute inset-0 bg-slate-900/70"></div>
        <div class="relative w-full max-w-3xl rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div id="org-photo-role" class="text-xs uppercase tracking-[0.2em] text-slate-400"></div>
                    <h3 id="org-photo-name" class="mt-1 text-xl font-semibold text-slate-900"></h3>
                </div>
                <button id="org-photo-close" type="button" class="text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <img id="org-photo-image" class="max-h-[70vh] w-full object-contain" alt="Foto struktur organisasi">
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const studentSearchForm = document.getElementById('student-search-form');
            const studentSearchInput = document.getElementById('student-search-input');
            const studentSearchResults = document.getElementById('student-search-results');
            const studentSearchSummary = document.getElementById('student-search-summary');

            if (studentSearchForm && studentSearchInput && studentSearchResults && studentSearchSummary) {
                const endpoint = studentSearchForm.dataset.liveSearchUrl;
                let activeRequestController = null;
                let debounceTimer = null;
                const searchResultLimit = 12;
                const queryCache = new Map();

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const normalizeSearchValue = (value) => String(value ?? '')
                    .toLocaleLowerCase('id')
                    .replace(/\s+/g, ' ')
                    .trim();

                const renderStudentInfo = (message) => {
                    studentSearchResults.innerHTML = `<div class="card p-5 text-sm text-slate-500 md:col-span-2 xl:col-span-3">${escapeHtml(message)}</div>`;
                };

                const renderStudentCards = (items) => {
                    studentSearchResults.innerHTML = items.map((student) => `
                        <a class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-lg" href="${escapeHtml(student.profile_url)}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-lg font-semibold text-slate-900">${escapeHtml(student.nama)}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">${escapeHtml(student.status_short)}</div>
                                </div>
                                <span class="chip">${escapeHtml(student.status_label)}</span>
                            </div>
                            <dl class="mt-4 space-y-2 text-sm text-slate-600">
                                <div class="flex items-center justify-between gap-3">
                                    <dt>NISN</dt>
                                    <dd class="font-medium text-slate-900">${escapeHtml(student.nisn || '-')}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt>Tanggal Lahir</dt>
                                    <dd class="font-medium text-slate-900">${escapeHtml(student.tanggal_lahir_label || '-')}</dd>
                                </div>
                            </dl>
                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Data Tes Siswa</div>
                                ${(() => {
                                    const testStyles = {
                                        'Kepribadian': 'border-sky-200 bg-sky-50 text-sky-900',
                                        'Gaya Belajar': 'border-emerald-200 bg-emerald-50 text-emerald-900',
                                        'Profiling': 'border-amber-200 bg-amber-50 text-amber-900',
                                        'MBTI': 'border-violet-200 bg-violet-50 text-violet-900',
                                    };
                                    const testEntries = Object.entries(student.test_data || {})
                                        .filter(([, value]) => String(value || '').trim() !== '');

                                    if (testEntries.length === 0) {
                                        return '<div class="mt-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-500">Data tes siswa belum tersedia.</div>';
                                    }

                                    return `
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            ${testEntries.map(([label, value]) => `
                                                <span class="inline-flex max-w-full items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium leading-tight ${escapeHtml(testStyles[label] || 'border-slate-200 bg-slate-50 text-slate-900')}">
                                                    <span class="uppercase tracking-[0.14em] text-slate-500">${escapeHtml(label)}</span>
                                                    <span class="truncate font-semibold">${escapeHtml(value)}</span>
                                                </span>
                                            `).join('')}
                                        </div>
                                    `;
                                })()}
                            </div>
                        </a>
                    `).join('');
                };

                const fetchStudentResults = async () => {
                    const query = studentSearchInput.value.trim();
                    const normalizedQuery = normalizeSearchValue(query);

                    if (normalizedQuery.length < 2) {
                        studentSearchSummary.textContent = 'Hasil pencarian akan muncul setelah Anda mengetik minimal 2 huruf atau angka.';
                        renderStudentInfo('Silakan ketik minimal 2 huruf atau angka untuk mencari nama atau NISN siswa.');

                        return;
                    }

                    if (!endpoint) {
                        studentSearchSummary.textContent = `Menampilkan hasil pencarian untuk: ${query}`;

                        return;
                    }

                    if (queryCache.has(normalizedQuery)) {
                        const cachedMatches = queryCache.get(normalizedQuery);

                        studentSearchSummary.textContent = cachedMatches.length > 0
                            ? `Menampilkan maksimal ${searchResultLimit} hasil untuk: ${query}`
                            : `Data siswa untuk "${query}" belum ditemukan.`;

                        if (cachedMatches.length === 0) {
                            renderStudentInfo('Data siswa yang Anda cari belum ditemukan. Silakan periksa kembali nama atau NISN yang dimasukkan.');

                            return;
                        }

                        renderStudentCards(cachedMatches);

                        return;
                    }

                    if (activeRequestController) {
                        activeRequestController.abort();
                    }

                    activeRequestController = new AbortController();

                    try {
                        const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                Accept: 'application/json',
                            },
                            signal: activeRequestController.signal,
                        });

                        if (!response.ok) {
                            renderStudentInfo('Pencarian data siswa sedang mengalami gangguan. Silakan coba lagi sesaat lagi.');

                            return;
                        }

                        const payload = await response.json();
                        const matches = Array.isArray(payload.results) ? payload.results : [];
                        queryCache.set(normalizedQuery, matches);

                        studentSearchSummary.textContent = matches.length > 0
                            ? `Menampilkan maksimal ${searchResultLimit} hasil untuk: ${query}`
                            : `Data siswa untuk "${query}" belum ditemukan.`;

                        if (matches.length === 0) {
                            renderStudentInfo('Data siswa yang Anda cari belum ditemukan. Silakan periksa kembali nama atau NISN yang dimasukkan.');

                            return;
                        }

                        renderStudentCards(matches);
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            studentSearchSummary.textContent = 'Pencarian data siswa sedang mengalami gangguan.';
                            renderStudentInfo('Pencarian data siswa sedang mengalami gangguan. Silakan coba lagi sesaat lagi.');
                        }
                    }
                };

                studentSearchForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    fetchStudentResults();
                });

                studentSearchInput.addEventListener('input', () => {
                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(fetchStudentResults, 450);
                });
            }

            const photoModal = document.getElementById('org-photo-modal');
            const photoClose = document.getElementById('org-photo-close');
            const photoImage = document.getElementById('org-photo-image');
            const photoName = document.getElementById('org-photo-name');
            const photoRole = document.getElementById('org-photo-role');

            const loadVisibleDeferredImages = (root = document) => {
                window.requestAnimationFrame(() => {
                    root.querySelectorAll('img[data-public-lazy-image][data-src]').forEach((image) => {
                        if (image.offsetParent === null || image.hasAttribute('src')) {
                            return;
                        }

                        image.src = image.dataset.src;
                        image.removeAttribute('data-src');
                    });
                });
            };

            const profileTabsContainer = document.querySelector('[data-home-profile-tabs]');
            if (profileTabsContainer) {
                const profileTabButtons = profileTabsContainer.querySelectorAll('[data-home-profile-tab-trigger]');
                const profileTabPanels = profileTabsContainer.querySelectorAll('[data-home-profile-tab-panel]');

                const activateProfileTab = (tabName) => {
                    profileTabButtons.forEach((button) => {
                        const isActive = button.dataset.homeProfileTabTrigger === tabName;
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        button.classList.toggle('is-active', isActive);
                    });

                    profileTabPanels.forEach((panel) => {
                        panel.hidden = panel.dataset.homeProfileTabPanel !== tabName;
                    });

                    const activePanel = profileTabsContainer.querySelector(
                        `[data-home-profile-tab-panel="${tabName}"]`,
                    );

                    if (activePanel) {
                        loadVisibleDeferredImages(activePanel);
                    }
                };

                profileTabButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        activateProfileTab(button.dataset.homeProfileTabTrigger);
                    });
                });

                activateProfileTab('struktur');

                let imageResizeTimer = null;
                window.addEventListener('resize', () => {
                    window.clearTimeout(imageResizeTimer);
                    imageResizeTimer = window.setTimeout(() => {
                        const activePanel = profileTabsContainer.querySelector(
                            '[data-home-profile-tab-panel]:not([hidden])',
                        );

                        if (activePanel) {
                            loadVisibleDeferredImages(activePanel);
                        }
                    }, 150);
                });
            }

            document.querySelectorAll('details').forEach((details) => {
                details.addEventListener('toggle', () => {
                    if (details.open) {
                        loadVisibleDeferredImages(details);
                    }
                });
            });

            document.querySelectorAll('[data-achievement-filter-root]').forEach((achievementRoot) => {
                const filterButtons = achievementRoot.querySelectorAll('[data-achievement-filter-trigger]');
                const achievementCards = achievementRoot.querySelectorAll('[data-achievement-category]');
                const emptyMessage = achievementRoot.querySelector('[data-achievement-filter-empty]');
                let activeCategory = null;

                const applyAchievementFilter = (category) => {
                    activeCategory = activeCategory === category ? null : category;
                    let visibleCount = 0;

                    achievementCards.forEach((card) => {
                        const shouldShow = !activeCategory || card.dataset.achievementCategory === activeCategory;
                        card.hidden = !shouldShow;

                        if (shouldShow) {
                            visibleCount += 1;
                        }
                    });

                    filterButtons.forEach((button) => {
                        const isActive = button.dataset.achievementFilterTrigger === activeCategory;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });

                    if (emptyMessage) {
                        emptyMessage.classList.toggle('hidden', visibleCount > 0 || !activeCategory);
                    }
                };

                filterButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        applyAchievementFilter(button.dataset.achievementFilterTrigger);
                    });
                });
            });

            if (!photoModal || !photoClose || !photoImage || !photoName || !photoRole) {
                return;
            }

            const openPhotoModal = (button) => {
                photoImage.src = button.dataset.orgImageSrc || '';
                photoName.textContent = button.dataset.orgImageName || '';
                photoRole.textContent = button.dataset.orgImageRole || '';
                photoModal.classList.remove('hidden');
                photoModal.classList.add('flex');
                photoModal.setAttribute('aria-hidden', 'false');
            };

            const closePhotoModal = () => {
                photoModal.classList.add('hidden');
                photoModal.classList.remove('flex');
                photoModal.setAttribute('aria-hidden', 'true');
                photoImage.removeAttribute('src');
            };

            document.querySelectorAll('[data-org-image-trigger]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openPhotoModal(button);
                });
            });

            photoClose.addEventListener('click', closePhotoModal);

            photoModal.addEventListener('click', (event) => {
                if (event.target === photoModal || event.target.classList.contains('bg-slate-900/70')) {
                    closePhotoModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closePhotoModal();
                }
            });
        });
    </script>
@endpush


