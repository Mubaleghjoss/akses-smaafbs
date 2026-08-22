@extends('layouts.app')

@section('content')
    <div class="card p-6 reveal">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Program</div>
                <h1 class="mt-2 text-2xl font-semibold">Literasi Numerasi</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Pilih soal aktif, baca instruksi, lalu kirim jawaban sesuai pertanyaan yang tersedia.
                </p>
            </div>

            <form method="get" action="{{ route('library.literacy.edit.lookup') }}" class="rounded-2xl border border-slate-200 bg-white p-4">
                <label class="text-sm font-semibold text-slate-700" for="code">Edit jawaban dengan kode unik</label>
                <div class="mt-3 grid gap-2 sm:flex">
                    <input id="code" name="code" value="{{ old('code') }}" class="input min-w-0 uppercase" placeholder="Contoh: ABC123">
                    <button class="btn btn-secondary w-full sm:w-auto" type="submit">Buka</button>
                </div>
                @error('code')
                    <div class="mt-2 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </form>
        </div>
    </div>

    <details class="card mt-6 p-5" open>
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Arahan dan tata tertib pengerjaan</summary>
        <div class="mt-3 space-y-2 text-sm leading-6 text-slate-600">
            {!! $instructionsHtml !!}
        </div>
    </details>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($materials as $material)
            @php
                $readingPreview = $material->readingContentPreview();
                $materialImageUrl = $material->imageUrl();
                $materialThumbnailUrl = $material->imageUrl('thumbnail');
            @endphp
            <article class="card flex h-full flex-col overflow-hidden">
                @if($materialImageUrl)
                    <img
                        src="{{ $materialThumbnailUrl }}"
                        srcset="{{ $materialThumbnailUrl }} 640w, {{ $materialImageUrl }} 1280w"
                        sizes="(min-width: 1280px) 33vw, (min-width: 768px) 50vw, 100vw"
                        alt=""
                        class="h-44 w-full object-cover"
                        width="640"
                        height="360"
                        loading="lazy"
                        decoding="async"
                    >
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip {{ $material->programCategoryBadgeClasses() }}">{{ $material->programCategoryLabel() }}</span>
                        <span class="chip">{{ number_format($material->questions_count ?? 0, 0, ',', '.') }} pertanyaan</span>
                        @if($material->closes_at)
                            <span class="chip">Tutup {{ $material->closes_at->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    <h2 class="mt-3 break-words text-lg font-semibold">{{ $material->title }}</h2>
                    @if(filled($readingPreview))
                        <div class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">{{ $readingPreview }}</div>
                    @endif
                    <div class="mt-auto pt-5">
                        <a class="btn btn-primary w-full" href="{{ route('library.literacy.show', $material->slug) }}">Buka Materi</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="card p-6 text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                Belum ada soal Literasi Numerasi yang aktif.
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $materials->links() }}</div>
@endsection
