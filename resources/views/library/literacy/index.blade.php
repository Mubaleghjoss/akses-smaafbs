@extends('layouts.app')

@section('content')
    @include('library._nav')

    <div class="card p-6 reveal">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Perpustakaan</div>
                <h1 class="mt-2 text-2xl font-semibold">Literacy Habituation Program</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Pilih materi bacaan aktif, baca instruksi, lalu kirim jawaban sesuai pertanyaan yang tersedia.
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

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($materials as $material)
            @php($readingPreview = $material->readingContentPreview())
            <article class="card flex h-full flex-col overflow-hidden">
                @if($material->imageUrl())
                    <img src="{{ $material->imageUrl() }}" alt="" class="h-44 w-full object-cover">
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex flex-wrap items-center gap-2">
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
                Belum ada materi Literacy Habituation Program yang aktif.
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $materials->links() }}</div>
@endsection

@push('scripts')
    @include('library.literacy._mathjax')
@endpush
