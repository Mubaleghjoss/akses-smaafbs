@extends('layouts.app')

@section('content')
    <a class="chip" href="{{ route('news.index') }}"><- Kembali ke informasi kegiatan</a>

    <article class="card mt-5 overflow-hidden">
        <div class="p-6 md:p-8">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Informasi kegiatan</div>
            <h1 class="mt-3 text-3xl font-semibold text-balance">{{ $news->judul }}</h1>
            <div class="mt-2 text-sm text-slate-500">{{ optional($news->tanggal_berita)->format('d/m/Y') ?? optional($news->created_at)->format('d/m/Y') }}</div>

            @if($imageUrl)
                <img class="mt-6 w-full rounded-2xl border border-white/70 object-cover shadow-sm" src="{{ $imageUrl }}" alt="Gambar berita" />
            @endif

            <div class="mt-6 space-y-3 text-sm leading-relaxed text-slate-700">
                {!! nl2br(e($news->konten)) !!}
            </div>

            @if(($timelineUpdates ?? collect())->isNotEmpty() || $news->tracker_phase || filled($news->tracker_progress_percent) || filled($news->tracker_update_text) || filled($news->tracker_live_url) || count($documentationMediaUrls) > 0)
                <section class="mt-8 space-y-4 rounded-2xl border border-slate-200/80 p-4 sm:p-5">
                    <h2 class="text-base font-semibold text-slate-900">Perkembangan kegiatan</h2>

                    @if(($timelineUpdates ?? collect())->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($timelineUpdates as $update)
                                <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $update['phase_label'] }}</div>
                                            <div class="mt-1 text-sm text-slate-500">{{ optional($update['tanggal_update'])->format('d/m/Y H:i') ?? '-' }}</div>
                                        </div>
                                        <span class="chip">{{ filled($update['progress_percent']) ? $update['progress_percent'].'%' : $update['phase_label'] }}</span>
                                    </div>

                                    @if(filled($update['update_text']))
                                        <p class="mt-3 text-sm leading-relaxed text-slate-700">{!! nl2br(e($update['update_text'])) !!}</p>
                                    @endif

                                    @if(! empty($update['documentation_media_urls']))
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            @foreach($update['documentation_media_urls'] as $documentationImage)
                                                <img class="w-full rounded-xl border border-slate-100 object-cover shadow-sm" src="{{ $documentationImage }}" alt="Dokumentasi {{ $update['phase_label'] }}" />
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(filled($update['live_url']))
                                        <div class="mt-3">
                                            <a class="btn btn-secondary" href="{{ $update['live_url'] }}" target="_blank" rel="noopener noreferrer">Buka siaran langsung</a>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Tahap pelaksanaan</div>
                                <div class="mt-1 text-sm font-medium text-slate-700">{{ $news->tracker_phase_label ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Progres</div>
                                <div class="mt-1 text-sm font-medium text-slate-700">{{ filled($news->tracker_progress_percent) ? $news->tracker_progress_percent.'%' : '-' }}</div>
                            </div>
                        </div>

                        @if(filled($news->tracker_update_text))
                            <div>
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Perkembangan terkini</div>
                                <p class="mt-1 text-sm leading-relaxed text-slate-700">{!! nl2br(e($news->tracker_update_text)) !!}</p>
                            </div>
                        @endif

                        @if(filled($news->tracker_live_url))
                            <div>
                                <a class="btn btn-secondary" href="{{ $news->tracker_live_url }}" target="_blank" rel="noopener noreferrer">Buka siaran langsung</a>
                            </div>
                        @endif
                    @endif

                    @if(count($documentationMediaUrls) > 0)
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Seluruh dokumentasi kegiatan</div>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach($documentationMediaUrls as $documentationImage)
                                    <img class="w-full rounded-xl border border-slate-100 object-cover shadow-sm" src="{{ $documentationImage }}" alt="Dokumentasi kegiatan" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </article>
@endsection
