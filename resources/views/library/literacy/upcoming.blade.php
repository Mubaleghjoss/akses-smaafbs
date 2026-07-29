@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl">
        <section class="card p-6 text-center md:p-8">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-700" aria-hidden="true">⏱</div>
            <div class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Materi Belum Dibuka</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950">{{ $material->title }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Pertanyaan dan isi bacaan baru tersedia pada
                <strong>{{ $material->opens_at?->format('d/m/Y H:i') ?? '-' }}</strong>.
                Silakan buka kembali link ini setelah waktu tersebut.
            </p>
            <a class="btn btn-secondary mt-6 inline-flex" href="{{ route('library.literacy.index') }}">Kembali ke Daftar</a>
        </section>
    </div>
@endsection
