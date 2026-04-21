@extends('layouts.app')

@section('content')
    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Informasi dan berita kegiatan</h1>
                <p class="mt-1 text-sm text-slate-500">Kumpulan informasi resmi sekolah dan perkembangan kegiatan yang telah dipublikasikan.</p>
            </div>
            <form method="get" class="grid w-full max-w-md gap-2 sm:flex sm:items-center">
                <input name="q" value="{{ $q }}" class="input min-w-0" placeholder="Cari judul informasi atau kegiatan..." />
                <button class="btn btn-primary w-full sm:w-auto" type="submit">Cari</button>
            </form>
        </div>
    </div>

    <div class="mt-6 space-y-3">
        @forelse($items as $news)
            <a class="card flex flex-col gap-2 p-5 transition hover:-translate-y-0.5 hover:shadow-[0_24px_60px_-35px_rgba(15,23,42,0.35)]" href="{{ route('news.show', $news) }}">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Informasi kegiatan</div>
                <div class="text-lg font-semibold text-slate-900">{{ $news->judul }}</div>
                <div class="text-sm text-slate-500">{{ optional($news->tanggal_berita)->format('d/m/Y') ?? optional($news->created_at)->format('d/m/Y') }}</div>
            </a>
        @empty
            <div class="card p-5 text-sm text-slate-500">Informasi kegiatan belum tersedia saat ini.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $items->withQueryString()->links() }}</div>
@endsection
