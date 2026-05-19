@extends('layouts.app')

@section('content')
    @include('library._nav')

    <a class="chip" href="{{ route('library.index') }}"><- Kembali ke layanan perpustakaan</a>

    @php
        $bookTypeLabel = strtolower((string) ($book->file_type ?? 'physical')) === 'ebook' ? 'E-Book' : 'Buku Fisik';
    @endphp

    <div class="card mt-5 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Informasi buku</div>
                <h1 class="mt-2 text-2xl font-semibold">{{ $book->judul_buku }}</h1>
                <div class="mt-1 text-sm text-slate-500">{{ $book->penulis ?? '-' }} - {{ $book->penerbit ?? '-' }}</div>
            </div>
            <span class="chip">{{ $bookTypeLabel }}</span>
        </div>

        @if(session('error'))
            <div class="mt-4 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ session('error') }}
            </div>
        @endif

        @if($book->deskripsi)
            <div class="mt-4 text-sm leading-relaxed text-slate-700">{{ $book->deskripsi }}</div>
        @endif

        @if($book->file_type === 'ebook' && $bookUrl)
            <div class="mt-6 grid gap-2 sm:flex sm:flex-wrap">
                <a class="btn btn-primary w-full sm:w-auto" href="{{ $bookUrl }}" target="_blank" rel="noopener">Buka e-book</a>
                <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('library.download', $book) }}">Unduh file</a>
            </div>
        @elseif($book->file_type === 'ebook')
            <div class="mt-6 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">File e-book belum tersedia untuk diakses.</div>
        @endif
    </div>
@endsection
