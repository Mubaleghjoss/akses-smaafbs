@extends('layouts.app')

@section('content')
    <div class="card mx-auto max-w-2xl p-10 text-center">
        <div class="text-xs uppercase tracking-[0.3em] text-slate-400">404</div>
        <h1 class="mt-3 text-3xl font-semibold">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-sm text-slate-500">Cek kembali tautan yang Anda buka, atau kembali ke halaman utama.</p>
        <a class="btn btn-primary mt-6" href="{{ route('home') }}">Kembali ke Beranda</a>
    </div>
@endsection
