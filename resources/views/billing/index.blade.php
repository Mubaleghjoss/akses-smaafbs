@extends('layouts.app')

@section('content')
    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Layanan informasi tagihan</h1>
                <p class="mt-1 text-sm text-slate-500">Masukkan kode billing untuk melihat rincian tagihan siswa secara mandiri.</p>
            </div>
            <div class="chip">Layanan mandiri</div>
        </div>

        @if(session('error'))
            <div class="mt-4 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ session('error') }}
            </div>
        @endif

        <form method="get" action="{{ route('billing.show') }}" class="mt-6 grid w-full max-w-lg gap-2 sm:flex sm:items-center">
            <input name="code" class="input min-w-0" placeholder="Contoh kode billing: ABCD1234" required />
            <button class="btn btn-primary w-full sm:w-auto" type="submit">Lihat Tagihan</button>
        </form>
    </div>
@endsection
